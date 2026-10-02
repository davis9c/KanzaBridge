<?php

namespace App\Controllers\Api;

use App\Libraries\Api\TokenService;
use App\Models\UserModel;
use App\Models\PegawaiModel;
use App\Models\PetugasModel;

class Auth extends BaseApiController
{
    protected UserModel $userModel;
    protected PegawaiModel $pegawaiModel;
    protected PetugasModel $petugasModel;
    protected TokenService $tokenService;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->pegawaiModel = new PegawaiModel();
        $this->petugasModel = new PetugasModel();
        $this->tokenService = new TokenService();
    }

    /**
     * POST api/auth/login
     *
     * Body JSON:
     * {
     *   "user_id": "...",
     *   "password": "..."
     * }
     */
    public function login()
    {
        $input = $this->getJsonInput();

        if (empty($input)) {
            return $this->respondError('Request body kosong atau JSON tidak valid', 400);
        }

        $userId   = $input['user_id']  ?? null;
        $password = $input['password'] ?? null;

        if (! $userId || ! $password) {
            return $this->respondError('user_id dan password wajib diisi', 400);
        }

        $valid = $this->userModel->validateUser($userId, $password);

        if (! $valid || ($valid['total'] ?? 0) < 1) {
            return $this->respondError('User ID atau password salah', 401);
        }

        $pegawai = $this->pegawaiModel->getByNik($userId);

        if (! $pegawai) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        $petugas = $this->petugasModel->getPetugasAuth($pegawai['nik']);

        $apiConfig = config('Api');

        if ($petugas) {
            $role        = 'petugas';
            $jabatanData = $petugas;
        } else {
            $role        = $apiConfig->defaultRole;
            $jabatanData = [
                'kd_jbtn' => $apiConfig->defaultKdJbtn,
                'nm_jbtn' => $apiConfig->defaultNmJbtn,
            ];
        }

        $issued = $this->tokenService->issue([
            'user_id'    => $userId,
            'pegawai_id' => $pegawai['id'],
            'nik'        => $pegawai['nik'],
            'nama'       => $pegawai['nama'],
            'role'       => $role,
            'kd_jabatan' => $jabatanData['kd_jbtn'],
            'jabatan'    => $jabatanData['nm_jbtn'],
        ], $pegawai['nik']);

        return $this->respondSuccess([
            'token'   => $issued['token'],
            'expires' => $issued['expires'],
            'data'    => $issued['payload']['user'],
        ], 'Login berhasil');
    }

    /**
     * POST api/auth/refresh
     */
    public function refresh()
    {
        $input        = $this->getJsonInput();
        $refreshToken = $input['refresh_token'] ?? null;

        if (! $refreshToken) {
            return $this->respondError('Refresh token wajib', 400);
        }

        $user = $this->userModel->getByRefreshToken($refreshToken);

        if (! $user) {
            return $this->respondError('Refresh token tidak valid', 401);
        }

        if (strtotime($user['refresh_expired_at'] ?? '') < time()) {
            return $this->respondError('Refresh token expired', 401);
        }

        $issued = $this->tokenService->issue($user, $user['nik']);

        return $this->respondSuccess([
            'token' => $issued['token'],
        ], 'Token berhasil diperbarui');
    }
}
