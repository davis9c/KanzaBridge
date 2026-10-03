<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Access as AccessConfig;

/**
 * Route guard untuk halaman web.
 *
 * AuthFilter sudah memastikan user login. Filter ini menambahkan dua aturan
 * lagi:
 *
 *   1. User yang tidak punya role TIDAK boleh membuka halaman mana pun,
 *      hanya halaman "no-access" dan logout.
 *   2. Route yang disebut di `Config\Access::$restrictedRoutes` hanya boleh
 *      dibuka role yang disebut di sana. Inilah yang membuat Supervisor dan
 *      Petugas tidak bisa menyentuh Manajemen User.
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

        if (! has_any_role()) {
            return redirect()->to(base_url('no-access'));
        }

        $rule = $this->restrictedRuleFor($path, $config);

        if ($rule === null) {
            return;
        }

        if (has_any_of_roles((array) $rule['roles'])) {
            return;
        }

        // Sengaja TIDAK ke `no-access`: halaman itu berbunyi "Akun Anda Belum
        // Memiliki Role", padahal user ini punya role — ia hanya tidak berhak
        // di satu bagian aplikasi. Diarahkan ke dashboard dengan penjelasan
        // supaya tidak menyesatkan.
        return redirect()
            ->to(base_url('dashboard'))
            ->with('error', 'Anda tidak punya akses ke ' . $rule['label'] . '.');
    }

    /**
     * Cari aturan pembatas yang cocok untuk sebuah path.
     *
     * Cocok per PREFIX segmen, bukan persis, supaya `user/data`,
     * `user/create`, dan `user/edit/1` ikut tertutup tanpa daftar terpisah.
     * Syarat slash di akhir itu penting: tanpa itu prefix `user` juga akan
     * mencocoki `/username`.
     *
     * @return array{roles:list<string>, label:string}|null
     */
    private function restrictedRuleFor(string $path, AccessConfig $config): ?array
    {
        foreach ($config->restrictedRoutes as $prefix => $rule) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $rule;
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}