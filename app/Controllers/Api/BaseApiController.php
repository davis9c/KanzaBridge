<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

abstract class BaseApiController extends BaseController
{
    /**
     * Ambil payload JSON dari body request.
     * Jika JSON invalid, kembalikan array kosong.
     *
     * @return array<string,mixed>
     */
    protected function getJsonInput(): array
    {
        $input = $this->request->getJSON(true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $input ?: [];
    }

    /**
     * Respon JSON sukses dengan struktur umum.
     *
     * @param array<string,mixed> $data
     */
    protected function respondSuccess(array $data = [], string $message = 'OK', int $status = 200)
    {
        $payload = array_merge([
            'status'  => $status,
            'message' => $message,
        ], $data);

        return $this->response->setStatusCode($status)->setJSON($payload);
    }

    /**
     * Respon JSON error dengan struktur umum.
     *
     * @param array<string,mixed> $extra
     */
    protected function respondError(string $message, int $status = 400, array $extra = [])
    {
        $payload = array_merge([
            'status'  => $status,
            'message' => $message,
        ], $extra);

        return $this->response->setStatusCode($status)->setJSON($payload);
    }

    /**
     * Pastikan request API sudah diautentikasi.
     * Mengembalikan data user yang diletakkan oleh JwtAuthFilter.
     */
    protected function requireAuth(): mixed
    {
        $loginUser = $this->request->user ?? null;

        if (! $loginUser) {
            return $this->respondError('Unauthorized', 401);
        }

        return $loginUser;
    }
}
