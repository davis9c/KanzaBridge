<?php

namespace App\Libraries\UserGate;

use RuntimeException;

/**
 * Error dari UserGate API.
 *
 * Menyimpan HTTP status dari sisi server supaya controller bisa
 * membedakan "kredensial salah" (coba lagi) dari "server error"
 * (janganalahkan user).
 */
class UserGateException extends RuntimeException
{
    /**
     * Permintaan tidak pernah terkirim karena konfigurasi lokal salah
     * (mis. API key belum diisi). Berbeda dengan API key ditolak oleh
     * UserGate, yang statusnya 401 dari sisi server.
     */
    public const KIND_CONFIG = 'config';

    /**
     * Gagal menjangkau /|time out ke UserGate.
     */
    public const KIND_TRANSPORT = 'transport';

    protected int $statusCode = 0;

    protected string $kind = self::KIND_TRANSPORT;

    /** @var array<string,string> Detail validasi dari UserGate. */
    protected array $errors = [];

    public function __construct(
        string $message,
        int $statusCode = 0,
        array $errors = [],
        string $kind = self::KIND_TRANSPORT
    ) {
        parent::__construct($message, $statusCode);

        $this->statusCode = $statusCode;
        $this->errors     = $errors;
        $this->kind       = $kind;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Kredensial salah / token tidak valid.
     * Pesan aman untuk ditampilkan ke user.
     */
    public function isAuthFailure(): bool
    {
        return in_array($this->statusCode, [401, 403], true);
    }

    /**
     * API key bermasalah (bukan kredensial user).
     *
     * UserGate membalas 401 dengan pesan spesifik untuk kasus ini, jadi
     * pesan itu yang dipakai sebagai pembeda — bukan statusnya.
     */
    public function isApiKeyProblem(): bool
    {
        if ($this->statusCode !== 401) {
            return false;
        }

        return str_contains($this->getMessage(), 'API Key')
            || str_contains($this->getMessage(), 'API key');
    }

    /**
     * Permintaan tidak jadi terkirim karena konfigurasi lokal belum benar.
     */
    public function isConfigProblem(): bool
    {
        return $this->kind === self::KIND_CONFIG;
    }

    /**
     * Terlalu banyak permintaan (rate limit).
     */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Masalah di sisi server / jaringan — bukan kesalahan user.
     */
    public function isServerProblem(): bool
    {
        return $this->kind === self::KIND_TRANSPORT
            && ($this->statusCode === 0 || $this->statusCode >= 500);
    }
}
