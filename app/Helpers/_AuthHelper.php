<?php
class AuthHelper
{
    public static function isLoggedIn(): bool
    {
        return session()->get('logged_in') === true;
    }

    public static function getNik(): ?string
    {
        return session()->get('nik');
    }

    public static function getNama(): ?string
    {
        return session()->get('nama');
    }

    public static function getJabatan(): ?string
    {
        return session()->get('jabatan');
    }

    public static function getKodeJabatan(): ?string
    {
        return session()->get('kd_jabatan');
    }

    public static function inJabatan(string $jabatan): bool
    {
        return self::isLoggedIn()
            && session()->get('jabatan') === $jabatan;
    }

    public static function inAnyJabatan(array $jabatan): bool
    {
        return self::isLoggedIn()
            && in_array(session()->get('jabatan'), $jabatan);
    }

    public static function logout(): void
    {
        session()->destroy();
    }
}
