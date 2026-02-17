<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\PetugasModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class SysApiAuth extends BaseController
{
    protected $userModel;
    protected $petugasModel;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->petugasModel = new PetugasModel();
    }


    public function login()
    {
        /**
         * 0. AMBIL RAW BODY
         */
        $rawBody = $this->request->getBody();
        //dd(env('JWT_SECRET'));

        if (empty($rawBody)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'Request body kosong'
            ]);
        }

        /**
         * 1. PARSE JSON (AMAN)
         */
        $input = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'JSON tidak valid'
            ]);
        }

        /**
         * 2. VALIDASI INPUT
         */
        $userId   = $input['user_id']  ?? null;
        $password = $input['password'] ?? null;

        if (! $userId || ! $password) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'user_id dan password wajib diisi'
            ]);
        }

        /**
         * 3. VALIDASI USER
         */
        $valid = $this->userModel->validateUser($userId, $password);

        if (! $valid || $valid['total'] < 1) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 401,
                'message' => 'User ID atau password salah'
            ]);
        }

        /**
         * 4. DATA PEGAWAI + JABATAN
         */
        $pegawai = $this->petugasModel->getPegawaiWithJabatan($userId);

        if (! $pegawai) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'message' => 'Data pegawai tidak ditemukan'
            ]);
        }

        /**
         * 5. GENERATE JWT
         */
        $issuedAt = time();
        $expire   = $issuedAt + (int) env('JWT_TTL');

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $pegawai['nik'],
            'user' => [
                'user_id'    => $userId,
                'nik'        => $pegawai['nik'],
                'nama'       => $pegawai['nama'],
                'kd_jabatan' => $pegawai['kd_jbtn'],
                'jabatan'    => $pegawai['nm_jbtn'],
                'departemen' => $pegawai['departemen']
            ]
        ];

        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        /**
         * 6. RESPONSE SUKSES
         */
        return $this->response->setJSON([
            'status'  => 200,
            'message' => 'Login berhasil',
            'token'   => $token,
            'expires' => date('Y-m-d H:i:s', $expire),
            'data'    => $payload['user']
        ]);
    }

    public function refresh()
    {
        $input = $this->request->getJSON(true);
        $refreshToken = $input['refresh_token'] ?? null;

        if (! $refreshToken) {
            return $this->response->setStatusCode(400)
                ->setJSON(['message' => 'Refresh token wajib']);
        }

        $user = $this->userModel->getByRefreshToken($refreshToken);

        if (! $user) {
            return $this->response->setStatusCode(401)
                ->setJSON(['message' => 'Refresh token tidak valid']);
        }

        if (strtotime($user['refresh_expired_at']) < time()) {
            return $this->response->setStatusCode(401)
                ->setJSON(['message' => 'Refresh token expired']);
        }

        // generate JWT baru
        $payload = [
            'iat' => time(),
            'exp' => time() + env('JWT_TTL'),
            'sub' => $user['nik'],
            'user' => $user
        ];

        $newToken = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        return $this->response->setJSON([
            'token' => $newToken
        ]);
    }
}
