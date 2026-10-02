<?php

namespace App\Libraries\UserGate;

use Config\UserGate as UserGateConfig;

/**
 * Penyimpanan token UserGate pada session.
 *
 * Token TIDAK disimpan di database: refresh token hanya berlaku 30 hari
 * dan selalu berpasangan dengan sesi login, jadi cukup di session. Kalau
 * session hilang, user cukup login ulang.
 *
 * Password juga tidak pernah disimpan di sini.
 */
class TokenStore
{
    private const KEY_ACCESS       = 'ug_access_token';
    private const KEY_REFRESH       = 'ug_refresh_token';
    private const KEY_ACCESS_EXP    = 'ug_access_expires_at';
    private const KEY_REFRESH_EXP   = 'ug_refresh_expires_at';

    private UserGateConfig $config;

    public function __construct(?UserGateConfig $config = null)
    {
        $this->config = $config ?? config(UserGateConfig::class);
    }

    /**
     * Simpan hasil login / refresh.
     *
     * @param array{access_token:string, expires_in:int, refresh_token:string, refresh_expires_in:int} $auth
     */
    public function put(array $auth): void
    {
        $now = time();

        session()->set([
            self::KEY_ACCESS     => $auth['access_token'],
            self::KEY_ACCESS_EXP => $now + max(1, (int) $auth['expires_in']),
        ]);

        // Refresh token berganti setiap kali dipakai (one-time use), dan
        // UserGate tidak selalu mengirimnya kembali — pertahankan yang lama
        // bila respons tidak menyertakan yang baru.
        if (! empty($auth['refresh_token'])) {
            session()->set([
                self::KEY_REFRESH     => $auth['refresh_token'],
                self::KEY_REFRESH_EXP => $now + max(1, (int) $auth['refresh_expires_in']),
            ]);
        }
    }

    public function accessToken(): ?string
    {
        $token = session()->get(self::KEY_ACCESS);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function refreshToken(): ?string
    {
        $token = session()->get(self::KEY_REFRESH);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function clear(): void
    {
        session()->remove([
            self::KEY_ACCESS,
            self::KEY_REFRESH,
            self::KEY_ACCESS_EXP,
            self::KEY_REFRESH_EXP,
        ]);
    }

    public function hasAccessToken(): bool
    {
        return $this->accessToken() !== null;
    }

    /**
     * Access token dianggap kedaluwarsa sedikit lebih awal
     * (refreshSkew detik) supaya request tidak gagal di tengah jalan.
     */
    public function accessTokenExpired(): bool
    {
        $exp = (int) session()->get(self::KEY_ACCESS_EXP);

        if ($exp === 0) {
            return true;
        }

        return ($exp - $this->config->refreshSkew) <= time();
    }

    public function refreshTokenExpired(): bool
    {
        $exp = (int) session()->get(self::KEY_REFRESH_EXP);

        if ($exp === 0) {
            return true;
        }

        return $exp <= time();
    }
}
