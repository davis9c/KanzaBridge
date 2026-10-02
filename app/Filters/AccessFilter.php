<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Access as AccessConfig;

/**
 * Route guard untuk halaman web.
 *
 * AuthFilter sudah memastikan user login. Filter ini menambahkan satu
 * aturan lagi: user yang tidak punya role TIDAK boleh membuka halaman
 * mana pun, hanya halaman "no-access" dan logout.
 *
 * Registered sebagai alias `access` di Config\Filters. Tidak dipakai
 * sama sekali oleh lapisan API (`/api/*` memakai alias `jwt`).
 */
class AccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Belum login? biarkan AuthFilter yang menangani.
        if (! session()->get('logged_in')) {
            return;
        }

        $config = config(AccessConfig::class);
        $path   = ltrim(service('uri')->getPath(), '/');

        // `Config\Access::$alwaysAllowed` berisi daftar route yang tetap
        // boleh diakses meski user tidak punya role.
        if (array_key_exists($path, $config->alwaysAllowed)) {
            return;
        }

        if (has_any_role()) {
            return;
        }

        return redirect()->to(base_url('no-access'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
