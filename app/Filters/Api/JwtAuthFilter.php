<?php

namespace App\Filters\Api;

use App\Libraries\Api\TokenService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentikasi API berbasis JWT (Authorization: Bearer <token>).
 *
 * Berada di namespace App\Filters\Api supaya tidak tercampur dengan
 * filter session milik program inti web.
 */
class JwtAuthFilter implements FilterInterface
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (! $authHeader) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Token tidak ditemukan']);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = $this->tokenService->decode($token);
            $request->user = $decoded->user; // inject ke request
        } catch (\Exception $e) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Token tidak valid']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
