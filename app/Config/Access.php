<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi otorisasi lokal (role & permission).
 *
 * Prinsip yang dipakai di KanzaBridge:
 *
 *   - UserGate = sumber kebenaran IDENTITAS (akun, username, password).
 *   - DB lokal = sumber kebenaran OTORISASI (role, status akses, link NIK).
 *     Field `roles` dari UserGate SENGAJA diabaikan.
 *
 * Tiga tingkat akses:
 *
 *   SUPER_ADMIN  -> semua menu, boleh menetapkan SuperAdmin, boleh hapus user
 *   ADMIN        -> semua menu, TIDAK boleh menetapkan SuperAdmin
 *   tanpa role   -> boleh login, tetapi tidak punya menu sama sekali
 */
class Access extends BaseConfig
{
    /**
     * Nama role yang dianggap super admin.
     * Dipakai untuk pengecekan cepat tanpa JOIN tabel roles.
     */
    public array $superAdminRoles = ['SUPER_ADMIN'];

    /**
     * Role yang mendapat akses menu biasa.
     * Dipakai untuk menentukan apakah user punya setidaknya satu menu.
     */
    public array $menuRoles = ['SUPER_ADMIN', 'ADMIN'];

    /**
     * Route yang TIDAK memerlukan role apa pun.
     *
     * Peta ini dibaca oleh App\Filters\AccessFilter. Format key adalah
     * URI path relatif terhadap baseURL, tanpa leading slash.
     * Nilai = daftar role yang boleh; `[]` berarti tanpa role juga boleh.
     *
     * Route yang tidak terdaftar di sini dan tidak ada di
     * $alwaysAllowed akan memblokir user tanpa role.
     */
    public array $alwaysAllowed = [
        'no-access' => [],
        'logout'    => [],
    ];

    /**
     * Aksi yang HANYA boleh dilakukan SuperAdmin.
     *
     * Dicek di server (App\Models\Access\AccessService::assertSuperAdmin)
     * dan dipakai view untuk menyembunyikan kontrol yang relevan.
     */
    public array $superAdminOnly = [
        'user.promote',   // menetapkan/menaikkan user menjadi SuperAdmin
        'user.delete',    // menghapus user
    ];

    /**
     * Label peran untuk ditampilkan di UI.
     */
    public array $roleLabels = [
        'SUPER_ADMIN' => 'SuperAdmin',
        'ADMIN'       => 'Admin',
    ];
}
