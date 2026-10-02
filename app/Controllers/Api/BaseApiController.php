<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base controller untuk seluruh endpoint API.
 *
 * Sengaja TIDAK extends App\Controllers\BaseController supaya lapisan
 * API tidak bergantung pada base class program inti (web). Semua
 * kebutuhan bersama API — koneksi DB, parsing input, format respons —
 * didefinisikan di sini.
 */
abstract class BaseApiController extends Controller
{
    protected $db;

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);

        $this->db = \Config\Database::connect(config('Api')->dbGroup);
    }

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
     * Mengembalikan data user yang diletakkan oleh JwtAuthFilter,
     * atau objek Response 401 bila tidak terautentikasi.
     *
     * Pemanggil wajib memeriksa hasilnya:
     *   if ($user instanceof ResponseInterface) { return $user; }
     *
     * @return array<string,mixed>|ResponseInterface
     */
    protected function requireAuth(): array|ResponseInterface
    {
        $loginUser = $this->request->user ?? null;

        if (! $loginUser) {
            return $this->respondError('Unauthorized', 401);
        }

        return (array) $loginUser;
    }
}
