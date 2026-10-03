<?php

namespace App\Libraries\Api;

use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use Config\Api as ApiConfig;

/**
 * Pembuatan dan verifikasi API key untuk API V2.
 *
 * Berpasangan dengan TokenService (API V1 / JWT): TokenService
 * menerbitkan JWT dari kredensial user, sedangkan service ini
 * menerbitkan key milik application lewat halaman "Application".
 *
 * Prinsip yang dipegang di sini:
 *
 *   - Key plaintext hanya pernah dibuat, tidak pernah dibaca dari DB.
 *     Yang disimpan hanya SHA-256-nya.
 *   - Pesan error untuk klien sengaja tidak mengungkap alasan kegagalan
 *     (key tidak dikenal / dicabut / kedaluwarsa). Klien hanya perlu
 *     tahu "perbaiki key-nya".
 *   - Setiap motif kegagalan punya kelasnya sendiri supaya bisa
 *     dipetakan ke kode status HTTP yang tepat oleh filter.
 */
class ApiKeyService
{
    /**
     * Key tidak ditemukan / tidak cocok dengan hash mana pun.
     */
    public const FAILURE_UNKNOWN = 'unknown_key';

    /**
     * Key sengaja dicabut (status REVOKED).
     */
    public const FAILURE_REVOKED = 'revoked';

    /**
     * Key sudah melewati masa berlaku.
     */
    public const FAILURE_EXPIRED = 'expired';

    /**
     * Batas request per menit terlampaui.
     */
    public const FAILURE_RATE_LIMITED = 'rate_limited';

    private ApiConfig $config;

    public function __construct()
    {
        $this->config = config(ApiConfig::class);
    }

    /* ------------------------------------------------------------------ *
     *  PEMBUATAN KEY
     * ------------------------------------------------------------------ */

    /**
     * Buat pasangan key + hash.
     *
     * @return array{plain:string, hash:string, prefix:string}
     */
    public function generate(): array
    {
        $plain = $this->prefix() . bin2hex(random_bytes($this->byteLength()));

        return [
            'plain'  => $plain,
            'hash'   => $this->hash($plain),
            'prefix' => substr($plain, 0, $this->prefixLength()),
        ];
    }

    public function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public function prefix(): string
    {
        return $this->config->v2KeyPrefix;
    }

    public function prefixLength(): int
    {
        return $this->config->v2KeyPrefixLength;
    }

    /* ------------------------------------------------------------------ *
     *  AUTENTIKASI
     * ------------------------------------------------------------------ */

    /**
     * Cocokkan key mentah dengan database.
     *
     * @return array{ok:bool, failure:?string, key:array<string,mixed>|null}
     */
    public function findByPlainKey(string $plainKey): array
    {
        $plainKey = trim($plainKey);

        if (! $this->looksLikeKey($plainKey)) {
            // Format salah. Tetap dijawab 401, tapi tidak menyentuh DB.
            return $this->fail(self::FAILURE_UNKNOWN);
        }

        $row = (new ApiKeyModel())->findByHash($this->hash($plainKey));

        if ($row === null) {
            return $this->fail(self::FAILURE_UNKNOWN);
        }

        if ((string) $row['status'] !== ApiKeyModel::STATUS_ACTIVE) {
            return $this->fail(self::FAILURE_REVOKED, $row);
        }

        if ($this->isExpired($row)) {
            return $this->fail(self::FAILURE_EXPIRED, $row);
        }

        return ['ok' => true, 'failure' => null, 'key' => $row];
    }

    /**
     * Scope milik sebuah key.
     *
     * @return list<string>
     */
    public function scopesFor(int $keyId): array
    {
        return (new ApiKeyScopeModel())->forKey($keyId);
    }

    public function isExpired(array $key): bool
    {
        $expiresAt = trim((string) ($key['expires_at'] ?? ''));

        if ($expiresAt === '') {
            return false;
        }

        $time = strtotime($expiresAt);

        // Tanggal yang tidak terbaca diperlakukan sebagai kedaluwarsa,
        // bukan sebagai "selamanya" — lebih aman untuk data.
        return $time === false || $time < time();
    }

    /* ------------------------------------------------------------------ *
     *  RATE LIMIT
     * ------------------------------------------------------------------ */

    /**
     * Tambah penghitung request untuk key pada jendela menit berjalan.
     *
     * Sengaja TIDAK mengandalkan nilai balik `increment()`: sebagian
     * handler mengembalikan jumlah baru, sebagian hanya boolean. Yang
     * dipakai di sini adalah "increment dulu kalau ada, lalu baca
     * kembali" supaya hasilnya sama di semua handler.
     *
     * @return array{allowed:bool, count:int, limit:int, retryAfter:int}
     */
    public function hitRateLimit(array $key): array
    {
        $limit = (int) ($key['rate_limit_per_minute'] ?? 0);

        if ($limit <= 0) {
            return ['allowed' => true, 'count' => 0, 'limit' => 0, 'retryAfter' => 0];
        }

        $cacheKey = $this->rateLimitCacheKey((int) $key['id']);
        $cache    = cache();

        if ($cache->get($cacheKey) === null) {
            $count = 1;
        } else {
            $cache->increment($cacheKey, 1);
            $count = (int) $cache->get($cacheKey);
        }

        // Penghitung hilang atau bukan angka (mis. cache dikosongkan
        // di tengah menit) — mulai dari 1 lagi, bukan dari angka sisa.
        if ($count <= 0) {
            $count = 1;
        }

        // Disimpan ulang sekaligus memperpanjang umur entri, karena
        // sebagian handler tidak mempertahankan TTL saat increment.
        $cache->save($cacheKey, $count, $this->config->v2RateLimitWindow);

        return [
            'allowed'    => $count <= $limit,
            'count'      => $count,
            'limit'      => $limit,
            'retryAfter' => $count <= $limit ? 0 : max(1, 60 - (time() % 60)),
        ];
    }

    /**
     * Cache key untuk jendela rate limit satu key.
     *
     * Cache CI4 melarang karakter reserved termasuk ':' pada nama key,
     * jadi pemisah memakai '_'.
     */
    private function rateLimitCacheKey(int $keyId): string
    {
        return sprintf('apikey_rl_%d_%s', $keyId, date('YmdHi'));
    }

    /* ------------------------------------------------------------------ *
     *  PENCATATAN PENGGUNAAN
     * ------------------------------------------------------------------ */

    /**
     * Tulis `last_used_at` — paling sering satu kali per
     * Config\Api::$v2UsageWriteInterval detik per key.
     *
     * Tanpa throttle ini setiap request API menulis ke database hanya
     * untuk mengisi satu kolom.
     */
    public function touchUsage(int $keyId): void
    {
        $flagKey = sprintf('apikey_used_%d', $keyId);
        $cache   = cache();

        if ($cache->get($flagKey) !== null) {
            return;
        }

        $cache->save($flagKey, 1, $this->config->v2UsageWriteInterval);

        (new ApiKeyModel())->touchLastUsed($keyId);
    }

    /* ------------------------------------------------------------------ *
     *  BANTUAN
     * ------------------------------------------------------------------ */

    /**
     * Apakah string ini berbentuk API key KanzaBridge?
     *
     * Pemeriksaan bentuk (bukan isi) supaya request dengan header aneh
     * tidak sampai ke query database.
     */
    public function looksLikeKey(string $plainKey): bool
    {
        if ($plainKey === '') {
            return false;
        }

        $prefix = $this->prefix();

        if ($prefix !== '' && ! str_starts_with($plainKey, $prefix)) {
            return false;
        }

        $body = substr($plainKey, strlen($prefix));

        return strlen($body) === $this->byteLength() * 2
            && ctype_xdigit($body);
    }

    /**
     * Ambil key dari header HTTP.
     *
     * Header utama `X-API-Key`; `Authorization: Bearer <key>` diterima
     * sebagai alternatif supaya integrator yang sudah terbiasa memakai
     * Authorization tidak perlu bereksperimen.
     */
    public function extractFromRequest($request): string
    {
        $header = $this->config->v2KeyHeader;

        $plain = trim((string) $request->getHeaderLine($header));

        if ($plain !== '') {
            return $plain;
        }

        $authorization = trim((string) $request->getHeaderLine('Authorization'));

        if ($authorization !== '') {
            // "Bearer <key>" dan juga "<key>" polos, supaya key yang
            // salah diformat tidak langsung ditolak tanpa petunjuk.
            $token = preg_replace('/^\s*Bearer\s+/i', '', $authorization) ?? '';

            return trim($token);
        }

        return '';
    }

    /**
     * @param  array<string,mixed>|null $key
     * @return array{ok:bool, failure:?string, key:array<string,mixed>|null}
     */
    private function fail(string $failure, ?array $key = null): array
    {
        return ['ok' => false, 'failure' => $failure, 'key' => $key];
    }

    private function byteLength(): int
    {
        return max(16, $this->config->v2KeyBytes);
    }
}