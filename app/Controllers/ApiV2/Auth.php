<?php

namespace App\Controllers\ApiV2;

use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;

class Auth extends BaseController
{
    public function login()
    {
        $input = $this->getJsonInput();

        if (empty($input)) {
            return $this->respondError('Request body kosong atau JSON tidak valid', 400);
        }

        $username = $input['username'] ?? $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (! $username || ! $password) {
            return $this->respondError('username/email dan password wajib diisi', 400);
        }

        $hasSuperAdmin = $this->userModel->hasSuperAdmin();

        if (! $hasSuperAdmin) {
            return $this->respondError('Super admin belum dibuat. Silakan buat akun super admin terlebih dahulu.', 409, [
                'setup_url' => '/api/v2/setup/super-admin',
            ]);
        }

        $user = $this->userModel->findByUsernameOrEmail($username);

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return $this->respondError('Kredensial tidak valid', 401);
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return $this->respondError('Akun belum aktif', 403);
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + 60 * 60 * 24 * 30;
        $tokenValue = JWT::encode([
            'iss' => base_url(),
            'sub' => (int) $user['id'],
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'user_id' => (int) $user['id'],
            'role' => ($this->roleModel->find($user['role_id']))['slug'] ?? 'user',
            'jti' => bin2hex(random_bytes(16)),
        ], env('JWT_SECRET'), 'HS256');

        $this->tokenModel->createToken((int) $user['id'], $tokenValue, date('Y-m-d H:i:s', $expiresAt));

        return $this->respondSuccess([
            'token' => $tokenValue,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'user' => $this->normalizeUserPayload($user),
        ], 'Login berhasil');
    }

    public function me()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        return $this->respondSuccess([
            'user' => $this->normalizeUserPayload($auth['user']),
            'role' => $auth['role'],
        ], 'OK');
    }

    public function logout()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        if (! empty($auth['token']['id'])) {
            $this->tokenModel->revokeToken($auth['token']['id']);
        }

        return $this->respondSuccess([], 'Logout berhasil');
    }
}
