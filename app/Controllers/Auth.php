<?php

namespace App\Controllers;

use App\Libraries\UserGate\UserGateException;
use App\Models\Access\AccessService;
use RuntimeException;

/**
 * Autentikasi halaman web.
 *
 * Kredensial divalidasi oleh UserGate API; role dan status akses
 * ditentukan oleh tabel lokal di database `default`.
 *
 * PENTING: ini hanya untuk halaman web. Endpoint `/api/*` punya
 * controller sendiri (App\Controllers\Api\Auth) dan tidak tersentuh.
 */
class Auth extends BaseController
{
    private AccessService $access;

    public function __construct()
    {
        $this->access = new AccessService();
    }

    public function login()
    {
        // Sudah login? tidak perlu melihat form lagi.
        if (session()->get('logged_in')) {
            return redirect()->to($this->landingPage());
        }

        return view('auth/login');
    }

    /**
     * POST /auth/attempt
     *
     * Input: `username` + `password` (divalidasi UserGate).
     */
    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        if ($username === '' || $password === '') {
            return redirect()->back()
                ->with('error', 'Username dan password wajib diisi')
                ->withInput();
        }

        try {
            $result = $this->access->authenticate($username, $password);
        } catch (UserGateException $e) {
            return redirect()->back()
                ->with('error', $this->authErrorMessage($e))
                ->withInput();
        } catch (RuntimeException $e) {
            // Status akun tidak valid / dinonaktifkan / gagal simpan lokal.
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }

        $user    = $result['user'];
        $message = $result['is_first_user']
            ? 'Login berhasil. Selamat datang ' . $user['full_name']
                . ' — Anda terdaftar sebagai SuperAdmin karena Anda user pertama.'
            : 'Login berhasil, selamat datang ' . $user['full_name'];

        // User tanpa role tetap boleh login, tapi tidak punya menu.
        if (! $result['is_super_admin'] && ! $result['roles']) {
            $message .= ' Akun Anda belum memiliki role, jadi belum ada menu yang dapat diakses.';
        }

        return redirect()->to($this->landingPage())->with('success', $message);
    }

    public function logout()
    {
        $this->access->logout();

        return redirect()->to(base_url('login'))
            ->with('success', 'Berhasil logout');
    }

    /**
     * Halaman yang dituju setelah login.
     */
    private function landingPage(): string
    {
        return has_any_role() ? base_url('dashboard') : base_url('no-access');
    }

    /**
     * Terjemahkan kegagalan UserGate menjadi pesan yang aman ditampilkan.
     * Pesan mentah UserGate tidak pernah dibiarkan bocor ke pengguna.
     */
    private function authErrorMessage(UserGateException $e): string
    {
        if ($e->isConfigProblem() || $e->isApiKeyProblem()) {
            log_message('error', 'UserGate API key bermasalah: ' . $e->getMessage());

            return 'Konfigurasi UserGate belum benar. Hubungi administrator sistem.';
        }

        if ($e->isRateLimited()) {
            return 'Terlalu banyak percobaan login. Silakan coba beberapa saat lagi.';
        }

        if ($e->isServerProblem()) {
            log_message('error', 'Gagal menghubungi UserGate: ' . $e->getMessage());

            return 'Layanan autentikasi sedang tidak tersedia. Silakan coba beberapa saat lagi.';
        }

        return 'Username atau password salah.';
    }
}
