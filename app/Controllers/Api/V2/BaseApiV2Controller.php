<?php

namespace App\Controllers\Api\V2;

use App\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base controller untuk endpoint API V2 (API key).
 *
 * extends BaseApiController supaya format respons, parsing JSON, dan
 * koneksi database ke sik_beta sama persis dengan API V1. Yang berbeda
 * hanya cara mengenali pemanggil: V2 tidak punya user yang login, melainkan
 * application + API key yang diletakkan oleh App\Filters\Api\ApiKeyAuthFilter.
 *
 * Karena tidak ada user, tidak ada permission per-pegawai di sini:
 * seluruh yang boleh diakses sudah ditentukan oleh scope API key, dan
 * filter sudah memeriksanya sebelum controller berjalan.
 */
abstract class BaseApiV2Controller extends BaseApiController
{
    /**
     * Data API key yang sedang dipakai request ini.
     *
     * @return array{key_id:int, application_id:int, label:string, key_prefix:string, scopes:list<string>}|null
     */
    protected function apiKey(): ?array
    {
        $apiKey = $this->request->apiKey ?? null;

        return is_array($apiKey) ? $apiKey : null;
    }

    /**
     * Pastikan request benar-benar membawa API key yang terautentikasi.
     *
     * Filter sudah menolak key yang tidak hidup, jadi kondisi di sini
     * hanya mungkin terjadi kalau ada route V2 yang lupa memasang filter.
     * Pengecekan sengaja diulang supaya controller diam-diam tidak bisa
     * bocorkan data kalau route-nya salah set.
     *
     * @return array<string,mixed>|ResponseInterface
     */
    protected function requireKey(): array|ResponseInterface
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return $this->respondError('API key tidak ditemukan.', 401);
        }

        return $apiKey;
    }
}