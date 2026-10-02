<?php

namespace App\Filters;

use App\Models\Access\AccessService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autentikasi halaman web berbasis session.
 *
 * Peran filter ini:
 *   1. memastikan user sudah login,
 *   2. memastikan access token UserGate masih valid — memperbaruinya
 *      lewat refresh token bila sudah/mau kedaluwarsa,
 *   3. memaksa login ulang bila sesi sudah tidak bisa dipulihkan.
 *
 * HANYA berlaku untuk halaman web. Lapisan API memakai alias `jwt`
 * (App\Filters\Api\JwtAuthFilter) dan tidak tersentuh di sini.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            return redirect()->to(base_url('login'));
        }

        $service = new AccessService();

        // Sesi login sudah ada, tapi token UserGate-nya tidak valid lagi
        // dan tidak bisa di-refresh (kedaluwarsa atau dicabut).
        if (! $service->ensureFreshToken()) {
            service('session')->destroy();

            return redirect()->to(base_url('login'))
                ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
