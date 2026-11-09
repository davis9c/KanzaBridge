<?php

namespace App\Controllers\ApiV2;

use CodeIgniter\HTTP\ResponseInterface;

class Tokens extends BaseController
{
    public function index()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $tokens = $this->tokenModel->listForUser((int) $auth['user']['id']);

        if (($auth['role']['slug'] ?? 'user') !== 'user') {
            $tokens = $this->tokenModel->listAll();
        }

        return $this->respondSuccess([
            'tokens' => $tokens,
        ], 'OK');
    }

    public function create()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $input = $this->getJsonInput();
        $userId = (int) ($input['user_id'] ?? $auth['user']['id']);

        if (($auth['role']['slug'] ?? 'user') === 'user' && $userId !== (int) $auth['user']['id']) {
            return $this->respondError('Forbidden', 403);
        }

        $issuedAt = time();
        $expiresAtValue = ! empty($input['expires_at']) ? strtotime($input['expires_at']) : strtotime('+30 days');
        $expiresAt = date('Y-m-d H:i:s', $expiresAtValue);
        $tokenValue = \Firebase\JWT\JWT::encode([
            'iss' => base_url(),
            'sub' => $userId,
            'iat' => $issuedAt,
            'exp' => $expiresAtValue,
            'user_id' => $userId,
            'role' => ($this->roleModel->find($this->userModel->find($userId)['role_id'] ?? 0))['slug'] ?? 'user',
            'jti' => bin2hex(random_bytes(16)),
        ], env('JWT_SECRET'), 'HS256');
        $tokenId = $this->tokenModel->createToken($userId, $tokenValue, $expiresAt);

        return $this->respondSuccess([
            'token_id' => $tokenId,
            'token' => $tokenValue,
            'expires_at' => $expiresAt,
        ], 'Token berhasil dibuat', 201);
    }

    public function delete($id)
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $token = $this->tokenModel->find($id);

        if (! $token) {
            return $this->respondError('Token tidak ditemukan', 404);
        }

        if (($auth['role']['slug'] ?? 'user') === 'user' && (int) $token['user_id'] !== (int) $auth['user']['id']) {
            return $this->respondError('Forbidden', 403);
        }

        $this->tokenModel->revokeToken($id);

        return $this->respondSuccess([], 'Token berhasil dicabut');
    }
}
