<?php

namespace App\Libraries\Api;

use Config\Api as ApiConfig;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Pembuatan dan verifikasi token JWT untuk lapisan API.
 *
 * Semua pembacaan secret / TTL dilakukan lewat Config\Api sehingga
 * tidak ada lagi pemanggilan env() langsung di controller atau filter.
 */
class TokenService
{
    private ApiConfig $config;

    public function __construct()
    {
        $this->config = config(ApiConfig::class);
    }

    /**
     * Buat token JWT dari data user.
     *
     * @param array<string,mixed> $user
     * @return array{token:string, expires:string, payload:array<string,mixed>}
     */
    public function issue(array $user, ?string $subject = null): array
    {
        $issuedAt = time();
        $expire   = $issuedAt + $this->ttl();

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expire,
            'sub'  => $subject ?? ($user['nik'] ?? $user['user_id'] ?? null),
            'user' => $user,
        ];

        return [
            'token'   => JWT::encode($payload, $this->secret(), $this->config->jwtAlgorithm),
            'expires' => date('Y-m-d H:i:s', $expire),
            'payload' => $payload,
        ];
    }

    /**
     * Decode dan verifikasi token JWT.
     *
     * @throws \Exception Bila signature tidak cocok atau token expired.
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), $this->config->jwtAlgorithm));
    }

    public function ttl(): int
    {
        return $this->config->jwtTtl ?: 3600;
    }

    private function secret(): string
    {
        if (empty($this->config->jwtSecret)) {
            throw new \RuntimeException(
                'API JWT secret belum dikonfigurasi. Set api.jwtSecret atau JWT_SECRET di .env.'
            );
        }

        return $this->config->jwtSecret;
    }
}
