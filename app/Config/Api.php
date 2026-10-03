<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi khusus lapisan API.
 *
 * Dipisah dari program inti web agar endpoint API tidak perlu membaca
 * setting lewat env() secara scattered, dan agar secret API punya
 * satu sumber kebenaran.
 *
 * Resolusi nilai:
 *   1. `.env` dengan prefix `api.` (mis. `api.jwtSecret`) — menang
 *   2. Fallback ke env lama (`JWT_SECRET`, `JWT_TTL`) — kompatibilitas
 *
 * Contoh di .env:
 *   api.jwtSecret = "..."
 *   api.jwtTtl    = 3600
 *
 *   api.v2KeyPrefix       = "kb_live_"
 *   api.v2KeyBytes        = 32
 *   api.v2KeyPrefixLength = 16
 *   api.v2KeyHeader       = "X-API-Key"
 */
class Api extends BaseConfig
{
    /**
     * Group koneksi database yang dipakai API.
     * Group ini tetap didefinisikan di Config\Database karena framework
     * hanya membaca koneksi dari sana.
     */
    public ?string $dbGroup = null;

    public ?string $jwtSecret = null;

    public ?int $jwtTtl = null;

    public string $jwtAlgorithm = 'HS256';

    /* ------------------------------------------------------------------ *
     *  API V2 — autentikasi API key
     * ------------------------------------------------------------------ */

    /**
     * Prefix yang menandai sebuah string sebagai API key KanzaBridge.
     * Dipakai saat memvalidasi format key sebelum menyentuh database.
     */
    public string $v2KeyPrefix = 'kb_live_';

    /**
     * Jumlah byte acak di belakang prefix. 32 byte = 64 karakter heks,
     * cukup panjang sehingga tidak perlu dirotasi karena alasan tebakan.
     */
    public int $v2KeyBytes = 32;

    /**
     * Berapa karakter pertama key yang disimpan polos untuk ditampilkan
     * di daftar (sisanya disamarkan). Harus <= panjang kolom
     * `api_keys.key_prefix`.
     */
    public int $v2KeyPrefixLength = 16;

    /**
     * Header utama untuk mengirim API key.
     * Header `Authorization: Bearer <key>` tetap diterima sebagai
     * alternatif supaya integrator tidak salah kirim.
     */
    public string $v2KeyHeader = 'X-API-Key';

    /**
     * Seberapa sering `last_used_at` boleh ditulis ulang, dalam detik.
     *
     * Tanpa throttle ini setiap request API menulis ke database hanya
     * untuk mengisi satu kolom. Dengan throttle, penulisan paling sering
     * satu kali per menit per key.
     */
    public int $v2UsageWriteInterval = 60;

    /**
     * Umur entri counter rate limit, dalam detik.
     * Harus lebih besar dari 60 agar jendela per-menit tidak terpotong
     * di tengah menit.
     */
    public int $v2RateLimitWindow = 120;

    /**
     * Role default untuk user yang tidak terdaftar di tabel petugas.
     */
    public string $defaultRole = 'dokter';

    public string $defaultKdJbtn = 'D1010';

    public string $defaultNmJbtn = 'DOKTER';

    public function __construct()
    {
        parent::__construct();

        $this->dbGroup    ??= 'khanza';
        $this->jwtSecret  ??= env('JWT_SECRET');
        $this->jwtTtl     ??= (int) env('JWT_TTL', 3600);

        $this->v2KeyPrefix       = (string) env('api.v2KeyPrefix', $this->v2KeyPrefix);
        $this->v2KeyBytes        = (int) env('api.v2KeyBytes', $this->v2KeyBytes);
        $this->v2KeyPrefixLength = (int) env('api.v2KeyPrefixLength', $this->v2KeyPrefixLength);
        $this->v2KeyHeader       = (string) env('api.v2KeyHeader', $this->v2KeyHeader);
    }
}
