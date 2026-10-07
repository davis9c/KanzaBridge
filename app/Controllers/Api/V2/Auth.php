<?php

namespace App\Controllers\Api\V2;

use App\Models\UserModel;
use App\Models\PegawaiModel;
use App\Models\PetugasModel;

/**
 * Autentikasi aplikasi V2 — `POST api/v2/auth/login`.
 *
 * Menggantikan `POST /api/auth/login` (V1). Perbedaan mendasar dengan
 * V1: endpoint ini **tidak menerbitkan token**. Yang dikembalikan murni
 * profil pegawai; sesi di sisi klien menjaga keadaan "sudah login".
 *
 * Spesifikasi lengkapnya ada di `plan/v2-auth-endpoint.md`. Perilaku yang
 * sengaja BEDA dari V1: bila pegawai tidak punya baris di `petugas`,
 * `kd_jabatan`/`jabatan` diisi `null`, TANPA fallback ke nilai default —
 * fallback akan menutupi masalah hak akses pengguna.
 */
class Auth extends BaseApiV2Controller
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
     * POST api/v2/auth/login
     *
     * Body: { "user_id": "...", "password": "..." }
     */
    public function login()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

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
            // Pesan SENGAJA generik: tidak boleh membocorkan apakah
            // user_id terdaftar atau passwordnya yang salah.
            return $this->respondError('User ID atau password salah', 401);
        }

        $pegawai = $this->pegawaiModel->getByNik($userId);

        if (! $pegawai) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        $petugas = $this->petugasModel->getPetugasAuth($pegawai['nik']);

        $hasJabatan = $petugas !== null;

        return $this->respondSuccess([
            'data' => [
                // String, mengikuti bentuk `GET /api/v2/pegawai`.
                'pegawai_id' => (string) $pegawai['id'],
                'nik'        => $pegawai['nik'],
                'nama'       => $pegawai['nama'],
                'kd_jabatan' => $petugas['kd_jbtn'] ?? null,
                'jabatan'    => $petugas['nm_jbtn'] ?? null,
            ],
        ], $hasJabatan ? 'Login berhasil' : 'Login berhasil, tetapi akun Anda belum memiliki jabatan');
    }
}
