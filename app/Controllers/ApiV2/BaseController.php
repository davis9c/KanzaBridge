<?php

namespace App\Controllers\ApiV2;

use App\Controllers\Api\BaseApiController;
use App\Models\ApiV2\RoleModel;
use App\Models\ApiV2\TokenModel;
use App\Models\ApiV2\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

class BaseController extends BaseApiController
{
    protected RoleModel $roleModel;
    protected UserModel $userModel;
    protected TokenModel $tokenModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->roleModel = new RoleModel();
        $this->userModel = new UserModel();
        $this->tokenModel = new TokenModel();
    }

    protected function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');

        if ($header === '') {
            $header = (string) $this->request->getServer('HTTP_AUTHORIZATION');
        }

        if ($header === '') {
            $header = (string) $this->request->getServer('REDIRECT_HTTP_AUTHORIZATION');
        }

        if ($header === '') {
            $header = (string) $this->request->getServer('Authorization');
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }

        if ($header !== '' && ! str_contains($header, ' ')) {
            return trim($header);
        }

        return null;
    }

    protected function authenticate(): mixed
    {
        $tokenValue = $this->getBearerToken();

        if (! $tokenValue) {
            return null;
        }

        try {
            $decoded = JWT::decode($tokenValue, new Key(env('JWT_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            return null;
        }

        $userId = $decoded->user_id ?? null;
        $user = $this->userModel->find($userId);

        if (! $user || ($user['status'] ?? 'active') !== 'active') {
            return null;
        }

        $role = $this->roleModel->find($user['role_id']);

        $token = $this->tokenModel->findActiveByToken($tokenValue);
        if (! $token && ! empty($decoded->jti)) {
            $token = $this->tokenModel->where('token', $tokenValue)->first();
        }

        return [
            'user'  => $user,
            'role'  => $role,
            'token' => $token,
            'claims' => (array) $decoded,
        ];
    }

    protected function requireAuth(): mixed
    {
        $auth = $this->authenticate();

        if (! $auth) {
            return $this->respondError('Unauthorized', 401);
        }

        return $auth;
    }

    protected function requireRole(array|string $roles): mixed
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $roleSlug = $auth['role']['slug'] ?? 'user';

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        if (! in_array($roleSlug, $roles, true)) {
            return $this->respondError('Forbidden', 403);
        }

        return $auth;
    }

    protected function normalizeUserPayload(array $user): array
    {
        unset($user['password_hash'], $user['password']);

        return $user;
    }
}
