<?php

namespace Tests\Support\Libraries;

use App\Libraries\UserGate\UserGateClient;
use App\Libraries\UserGate\UserGateException;

/**
 * UserGateClient tiruan untuk pengujian.
 *
 * Hanya diperlukan agar aturan lokal (bootstrap SuperAdmin, ROLE
 * user tanpa role) bisa diuji tanpa memanggil UserGate sungguhan.
 *
 * Respons dikembalikan apa adanya sesuai yang dikonfigurasi; tidak ada
 * HTTP request yang pernah dibuat.
 */
class FakeUserGateClient extends UserGateClient
{
    /** @var array<string,mixed>|null Respons untuk auth/login & auth/refresh. */
    public ?array $authResponse = null;

    /** @var list<array<string,mixed>> Panggilan login yang tercatat. */
    public array $loginCalls = [];

    /** @var list<array<string,mixed>> Panggilan logout yang tercatat. */
    public array $logoutCalls = [];

    /** @var list<array{id:string,payload:array<string,mixed>}> Panggilan updateUser. */
    public array $updateCalls = [];

    /** @var list<string> Id yang diteruskan ke deleteUser. */
    public array $deleteCalls = [];

    /** @var array<string,mixed>|null Respons createUser. */
    public ?array $createResponse = null;

    /** @var UserGateException|null Kalau diisi, operasi berikutnya gagal. */
    public ?UserGateException $failWith = null;

    /**
     * Semua operasi bermuara ke sini — tidak pernah ada HTTP request.
     *
     * Tanpa ini, test yang memanggil SysUser akan benar-benar menghubungi
     * UserGate produksi.
     */
    private function guard(): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
    }

    public function createUser(string $accessToken, array $payload): array
    {
        $this->guard();

        return $this->createResponse ?? $payload + ['id' => 'uuid-dari-fake'];
    }

    public function updateUser(string $accessToken, string $id, array $payload): array
    {
        $this->guard();

        $this->updateCalls[] = ['id' => $id, 'payload' => $payload];

        return $payload;
    }

    public function deleteUser(string $accessToken, string $id): array
    {
        $this->guard();

        $this->deleteCalls[] = $id;

        return [];
    }

    public function __construct(?array $authResponse = null)
    {
        parent::__construct();

        $this->authResponse = $authResponse;
    }

    public function login(string $username, string $password): array
    {
        $this->loginCalls[] = ['username' => $username, 'password' => $password];

        return $this->authResponse ?? [
            'access_token'       => 'fake-access',
            'token_type'         => 'Bearer',
            'expires_in'         => 900,
            'refresh_token'      => 'fake-refresh',
            'refresh_expires_in' => 2592000,
            'user'               => [],
        ];
    }

    public function logout(string $accessToken): void
    {
        $this->logoutCalls[] = $accessToken;
    }

    /**
     * Susun respons auth yang realistis untuk pengujian.
     *
     * @return array<string,mixed>
     */
    public static function authFor(
        string $id,
        string $username,
        string $email,
        string $fullName,
        string $status = 'ACTIVE'
    ): array {
        return [
            'access_token'       => 'fake-access',
            'token_type'         => 'Bearer',
            'expires_in'         => 900,
            'refresh_token'      => 'fake-refresh',
            'refresh_expires_in' => 2592000,
            'user'               => [
                'id'            => $id,
                'username'      => $username,
                'email'         => $email,
                'full_name'     => $fullName,
                'status'        => $status,
                // Field ini SENGAJA tidak dipakai KanzaBridge; disisipkan
                // untuk membuktikan role lokal yang dipakai, bukan ini.
                'roles'         => ['ADMIN'],
                'is_super_admin' => false,
            ],
        ];
    }
}
