<?php

namespace App\Libraries\UserGate;

use Config\UserGate as UserGateConfig;
use Throwable;

/**
 * HTTP client untuk UserGate API.
 *
 * Seluruh request mengirim header wajib:
 *   X-API-Key: <api_key>
 *   Content-Type: application/json
 *   Accept: application/json
 *
 * Respons UserGate mengikuti amplop:
 *   sukses  { "status": true,  "message": "...", "data": ..., "meta": ... }
 *   gagal   { "status": false, "message": "...", "errors": {...} }
 *
 * Client ini tidak melempar exception untuk respons 4xx/5xx; ia melempar
 * UserGateException yang sudah membawa status + pesan + detail validasi.
 * Exception dengan status 0 berarti masalah jaringan/timeout.
 *
 * @see plan/API_REFERENCE_COMPLETE.md
 */
class UserGateClient
{
    private UserGateConfig $config;

    public function __construct(?UserGateConfig $config = null)
    {
        $this->config = $config ?? config(UserGateConfig::class);
    }

    /* ------------------------------------------------------------------ *
     *  AUTH
     * ------------------------------------------------------------------ */

    /**
     * POST /api/v1/auth/login
     *
     * @param  array{password: string} $credentials Berisi `username` & `password`.
     * @return array{access_token:string, token_type:string, expires_in:int, refresh_token:string, refresh_expires_in:int, user:array<string,mixed>}
     */
    public function login(string $username, string $password): array
    {
        $data = $this->post('/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);

        return $this->authPayload($data);
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * Refresh token bersifat one-time-use dan dirotasi setiap dipakai,
     * jadi pemanggil WAJIB menyimpan hasil baru (token + user).
     *
     * @return array{access_token:string, token_type:string, expires_in:int, refresh_token:string, refresh_expires_in:int, user:array<string,mixed>}
     */
    public function refresh(string $refreshToken): array
    {
        $data = $this->post('/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        return $this->authPayload($data);
    }

    /**
     * GET /api/v1/auth/me
     *
     * @return array<string,mixed>
     */
    public function me(string $accessToken): array
    {
        $data = $this->get('/auth/me', [], $accessToken);

        return is_array($data) ? $data : [];
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Best-effort: kegagalan tidak boleh mengganggu alur logout lokal.
     */
    public function logout(string $accessToken): void
    {
        try {
            $this->post('/auth/logout', [], $accessToken);
        } catch (Throwable) {
            // Sengaja diabaikan — sesi lokal tetap harus bisa diakhiri.
        }
    }

    /* ------------------------------------------------------------------ *
     *  USERS
     * ------------------------------------------------------------------ */

    /**
     * GET /api/v1/users
     *
     * @param  array{page?:int, per_page?:int, search?:string} $query
     * @return array{items: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listUsers(string $accessToken, array $query = []): array
    {
        $query = array_filter([
            'page'     => $query['page']     ?? null,
            'per_page' => $query['per_page'] ?? null,
            'search'   => $query['search']   ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->get('/users', $query, $accessToken);

        $items = $response['data'] ?? [];
        $meta  = $response['meta']  ?? [];

        return [
            'items' => is_array($items) ? $items : [],
            'meta'  => is_array($meta) ? $meta : [],
        ];
    }

    /**
     * GET /api/v1/users/{id}
     *
     * @return array<string,mixed>
     */
    public function getUser(string $accessToken, string $id): array
    {
        $data = $this->get('/users/' . rawurlencode($id), [], $accessToken);

        return is_array($data) ? $data : [];
    }

    /**
     * POST /api/v1/users
     *
     * Password disimpan sebagai hash oleh UserGate dan TIDAK pernah
     * dikembalikan lagi — jadi caller harus menyimpan password di tempat
     * aman bila masih dibutuhkan.
     *
     * @param  array{username:string, email:string, full_name:string, password:string} $payload
     * @return array<string,mixed>
     */
    public function createUser(string $accessToken, array $payload): array
    {
        $data = $this->post('/users', $payload, $accessToken);

        return is_array($data) ? $data : [];
    }

    /**
     * PUT /api/v1/users/{id}
     *
     * Catatan: UserGate tidak menyediakan endpoint ubah password —
     * body hanya menerima username, email, full_name, dan status.
     *
     * @param  array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function updateUser(string $accessToken, string $id, array $payload): array
    {
        $data = $this->put('/users/' . rawurlencode($id), $payload, $accessToken);

        return is_array($data) ? $data : [];
    }

    /**
     * DELETE /api/v1/users/{id}
     *
     * @return array<string,mixed>
     */
    public function deleteUser(string $accessToken, string $id): array
    {
        $data = $this->delete('/users/' . rawurlencode($id), $accessToken);

        return is_array($data) ? $data : [];
    }

    /* ------------------------------------------------------------------ *
     *  TRANSPORT
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string,mixed> $payload
     * @return array<string,mixed> Bagian `data` dari amplop sukses.
     */
    private function post(string $path, array $payload = [], ?string $accessToken = null): array
    {
        return $this->request('POST', $path, $payload, $accessToken, []);
    }

    /**
     * @param  array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function put(string $path, array $payload, ?string $accessToken = null): array
    {
        return $this->request('PUT', $path, $payload, $accessToken, []);
    }

    /**
     * @return array<string,mixed>
     */
    private function delete(string $path, ?string $accessToken = null): array
    {
        return $this->request('DELETE', $path, null, $accessToken, []);
    }

    /**
     * @param  array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function get(string $path, array $query = [], ?string $accessToken = null): array
    {
        return $this->request('GET', $path, null, $accessToken, $query);
    }

    /**
     * @param  array<string,mixed>|null $payload
     * @param  array<string,mixed>      $query
     * @return array<string,mixed> Bagian `data` dari amplop sukses.
     *
     * @throws UserGateException Bila request gagal / respons tidak valid.
     */
    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        ?string $accessToken = null,
        array $query = []
    ): array {
        if (! $this->config->isConfigured()) {
            throw new UserGateException(
                'API key UserGate belum dikonfigurasi. Set usergate.apiKey di .env.',
                0,
                [],
                UserGateException::KIND_CONFIG
            );
        }

        $url = $this->config->baseUrl . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'X-API-Key: ' . $this->config->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (! empty($accessToken)) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        [$status, $raw] = $this->send($method, $url, $headers, $body);

        return $this->unwrap($status, $raw);
    }

    /**
     * Eksekusi HTTP lewat cURL native.
     *
     * Sengaja memakai cURL langsung, bukan `IncomingRequest` sebagai HTTP
     * client: vendor yang terpasang (CI4 4.6.5) tidak mengekspos verb
     * client pada `IncomingRequest`, dan cURL memberi kontrol penuh atas
     * header `X-API-Key`, timeout, serta deteksi status HTTP.
     *
     * @param  list<string> $headers
     * @return array{0:int, 1:string} Status HTTP dan body mentah.
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        if (! function_exists('curl_init')) {
            throw new UserGateException('Ekstensi cURL tidak tersedia di server.', 0);
        }

        $handle = curl_init();

        if ($handle === false) {
            throw new UserGateException('Gagal menyiapkan koneksi ke UserGate.', 0);
        }

        // Header=cURL akan menambahkan "Expect: 100-Continue" untuk body
        // > 1KB yang bisa menggantungkan beberapa proxy.
        $headers[] = 'Expect:';

        curl_setopt_array($handle, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->config->timeout,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);
        $errno  = curl_errno($handle);

        curl_close($handle);

        if ($raw === false || $errno !== 0) {
            throw new UserGateException(
                'Tidak dapat menghubungi UserGate: ' . ($error !== '' ? $error : 'error cURL #' . $errno),
                0
            );
        }

        return [$status, (string) $raw];
    }

    /**
     * Membuka amplop respons UserGate.
     *
     * @return array<string,mixed> Bagian `data`.
     *
     * @throws UserGateException
     */
    private function unwrap(int $status, string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new UserGateException(
                'Respons UserGate tidak valid (HTTP ' . $status . ').',
                $status
            );
        }

        $message = (string) ($decoded['message'] ?? '');
        $errors  = $decoded['errors'] ?? [];
        $errors  = is_array($errors) ? $errors : [];

        // Sukses: `status` true. Status HTTP dipakai sebagai jaga-jaga
        // kalau UserGate suatu saat tidak lagi mengirim field `status`.
        $isSuccess = array_key_exists('status', $decoded)
            ? (bool) $decoded['status']
            : ($status >= 200 && $status < 300);

        if (! $isSuccess || $status < 200 || $status >= 300) {
            throw new UserGateException(
                $message !== '' ? $message : 'Permintaan ke UserGate gagal (HTTP ' . $status . ').',
                $status,
                $errors
            );
        }

        $data = $decoded['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Membentuk payload auth yang seragam dari respons login/refresh.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function authPayload(array $data): array
    {
        if (empty($data['access_token'])) {
            throw new UserGateException(
                'UserGate tidak mengembalikan access token.',
                0
            );
        }

        return [
            'access_token'       => (string) $data['access_token'],
            'token_type'         => (string) ($data['token_type'] ?? 'Bearer'),
            'expires_in'         => (int) ($data['expires_in'] ?? 900),
            'refresh_token'      => (string) ($data['refresh_token'] ?? ''),
            'refresh_expires_in' => (int) ($data['refresh_expires_in'] ?? 2592000),
            'user'               => is_array($data['user'] ?? null) ? $data['user'] : [],
        ];
    }
}
