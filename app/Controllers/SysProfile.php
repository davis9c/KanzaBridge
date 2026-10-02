<?php

namespace App\Controllers;

use App\Models\Access\UserModel;

/**
 * Profil user yang sedang login.
 *
 * Sumbernya adalah tabel `users` lokal — identitas yang disalin dari
 * UserGate saat login. Data kepegawaian khanza tidak ditampilkan di sini.
 */
class SysProfile extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function index()
    {
        $userId = current_user_id();

        if ($userId <= 0) {
            return redirect()->to(base_url('login'));
        }

        $user = $this->users->find($userId);

        if ($user === null) {
            session()->destroy();

            return redirect()->to(base_url('login'))
                ->with('error', 'Data user tidak ditemukan. Silakan login kembali.');
        }

        return view('sys-profile', [
            'title' => 'Profile Saya',
            'user'  => $user,
            'roles' => session('access_roles') ?: [],
        ]);
    }
}
