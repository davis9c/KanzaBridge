<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;
use App\Models\UserModel;
use App\Models\PegawaiModel;
use App\Models\PetugasModel;
use Firebase\JWT\JWT;

class Auth extends BaseApiController
{
    protected UserModel $userModel;
    protected PegawaiModel $pegawaiModel;
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->pegawaiModel = new PegawaiModel();
        $this->petugasModel = new PetugasModel();
    }

    /**
     * Login API.
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

        if ($petugas) {
            $role = 'petugas';
            $jabatanData = $petugas;
        } else {
            $role = 'dokter';
            $jabatanData = [
                'kd_jbtn' => 'D1010',
                'nm_jbtn' => 'DOKTER',
            ];
        }

        $issuedAt = time();
        $expire = $issuedAt + (int) env('JWT_TTL');

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $pegawai['nik'],
            'user' => [
                'user_id'    => $userId,
                'pegawai_id' => $pegawai['id'],
                'nik'        => $pegawai['nik'],
                'nama'       => $pegawai['nama'],
                'role'       => $role,
                'kd_jabatan' => $jabatanData['kd_jbtn'],
                'jabatan'    => $jabatanData['nm_jbtn'],
            ],
        ];

        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        return $this->respondSuccess([
            'token'   => $token,
            'expires' => date('Y-m-d H:i:s', $expire),
            'data'    => $payload['user'],
        ], 'Login berhasil');
    }

    public function refresh()
    {
        $input = $this->getJsonInput();
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

        $payload = [
            'iat' => time(),
            'exp' => time() + (int) env('JWT_TTL'),
            'sub' => $user['nik'],
            'user' => $user,
        ];

        $newToken = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        return $this->respondSuccess([
            'token' => $newToken,
        ], 'Token berhasil diperbarui');
    }
}
