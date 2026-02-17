<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\PegawaiModel;

class SysProfile extends BaseController
{
    protected $userModel;
    protected $pegawaiModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->pegawaiModel = new PegawaiModel();
    }

    public function index()
    {
        $userId = session()->get('user_id');

        if (!$userId) {
            return redirect()->to('/login');
        }

        $user = $this->pegawaiModel->getPegawaiLengkap($userId);

        if (! $user) {
            return redirect()->to('/login')
                ->with('error', 'Data pegawai tidak ditemukan');
        }

        return view('sys-profile', [
            'title' => 'Profile Saya',
            'user'  => $user,
        ]);
    }
}
