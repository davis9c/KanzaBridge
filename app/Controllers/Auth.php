<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\PegawaiModel;
use App\Models\PetugasModel;

class Auth extends BaseController
{
    protected $userModel;
    protected $pegawaiModel;
    protected $petugasModel;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->pegawaiModel = new PegawaiModel();
        $this->petugasModel = new PetugasModel();
    }

    public function login()
    {
        return view('auth/login');
    }

    public function attempt()
    {
        $userId   = $this->request->getPost('user_id');
        $password = $this->request->getPost('password');

        if (! $userId || ! $password) {
            return redirect()->back()
                ->with('error', 'User ID dan password wajib diisi')
                ->withInput();
        }

        // ======================
        // 1. VALIDASI USER
        // ======================
        $check = $this->userModel->validateUser($userId, $password);

        if ($check['total'] < 1) {
            return redirect()->back()
                ->with('error', 'User ID atau password salah')
                ->withInput();
        }

        // ======================
        // 2. DATA PEGAWAI
        // ======================
        $pegawai = $this->pegawaiModel
            ->where('nik', $userId)
            ->first();

        if (! $pegawai) {
            return redirect()->back()
                ->with('error', 'Data pegawai tidak ditemukan')
                ->withInput();
        }

        // ======================
        // 3. DATA PETUGAS + JABATAN
        // ======================
        $pegawai = $this->petugasModel->getPegawaiWithJabatan($userId);

        if (! $pegawai) {
            return redirect()->back()
                ->with('error', 'Data pegawai tidak ditemukan')
                ->withInput();
        }

        session()->set([
            'user_id'    => $userId,
            'nik'        => $pegawai['nik'],
            'nama'       => $pegawai['nama'],
            'kd_jabatan' => $pegawai['kd_jbtn'] ?? null,
            'jabatan'    => $pegawai['nm_jbtn'] ?? '-',
            'departemen' => $pegawai['departemen'],
            'logged_in'  => true,
        ]);



        return redirect()->to('/dashboard')
            ->with('success', 'Login berhasil, selamat datang ' . $pegawai['nama']);
    }

    public function logout()
    {
        //session()->destroy();
        session()->remove(['token']);
        session()->setFlashdata('success', 'Berhasil logout');
        return redirect()->to('/login');
    }
}
