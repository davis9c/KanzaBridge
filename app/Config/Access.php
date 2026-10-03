<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi otorisasi lokal (role & permission).
 *
 * Prinsip yang dipakai di KanzaBridge:
 *
 *   - UserGate = sumber kebenaran IDENTITAS (akun, username, email, nama).
 *   - DB lokal = sumber kebenaran OTORISASI (role, status akses, link NIK).
 *     Field `roles` dari UserGate SENGAJA diabaikan.
 *
 * Jenjang akses, dari atas ke bawah:
 *
 *   SUPER_ADMIN  -> semua menu, boleh menetapkan role apa pun, boleh hapus user
 *   ADMIN        -> semua menu, boleh menetapkan role DI BAWAHNYA saja
 *   SUPERVISOR   -> semua menu kecuali Manajemen User
 *   PETUGAS      -> sama seperti Supervisor; dibedakan lewat.rank untuk
 *                   aturan "tidak bisa memberikan role setara"
 *   tanpa role   -> boleh login, tetapi tidak punya menu sama sekali
 *
 * "Role di bawahnya" bukan daftar yang ditulis manual, tapi diturunkan dari
 * $roleRank — lihat SysUser::assignableRoles(). Dengan begitu menambah role
 * baru cukup menambah satu angka, tanpa menulis aturan terpisah.
 */
class Access extends BaseConfig
{
    /**
     * Nama role yang dianggap super admin.
     * Dipakai untuk pengecekan cepat tanpa JOIN tabel roles.
     */
    public array $superAdminRoles = ['SUPER_ADMIN'];

    /**
     * Tingkat tiap role. Semakin besar, semakin berkuasa.
     *
     * Dipakai dua aturan sekaligus:
     *
     *   1. Actor boleh memberikan role R hanya bila rank(R) < rank(actor).
     *      SuperAdmin dikecualikan supaya bisa memberikan role-nya sendiri.
     *      Inilah yang membuat Admin TIDAK bisa memberikan role ADMIN.
     *   2. Actor hanya boleh mengubah role/status akun yang rank-nya di
     *      bawahnya, jadi role setara tidak bisa saling disentuh.
     *
     * Disimpan di config, bukan kolom DB: urutan role adalah keputusan
     * aplikasi, dan kolom baru berarti migrasi untuk sesuatu yang jarang
     * berubah. Role yang tidak terdaftar di sini dianggap rank 0, sehingga
     * tidak bisa diberikan oleh siapa pun kecuali SuperAdmin.
     */
    public array $roleRank = [
        'SUPER_ADMIN' => 40,
        'ADMIN'       => 30,
        'SUPERVISOR'  => 20,
        'PETUGAS'     => 10,
    ];

    /**
     * Route yang HANYA boleh dibuka role tertentu.
     *
     * Dibaca oleh App\Filters\AccessFilter. Key adalah prefix segmen URI,
     * tanpa leading slash, dan dicocokkan dengan:
     *
     *     $path === $prefix || str_starts_with($path, $prefix . '/')
     *
     * Pencocokan prefix disengaja: sub-route seperti `user/data`,
     * `user/create`, dan `user/edit/1` ikut tertutup tanpa perlu
     * didaftarkan satu per satu, dan route `./user/apa-pun` di masa depan
     * tidak bocor karena tidak sengaja. Syarat `/` itu penting supaya prefix
     * `user` tidak ikut mencocoki `/username`.
     *
     * Route yang tidak ada di sini TIDAK dibatasi — sama seperti sebelumnya.
     * Daftar ini berisi pengecualian, bukan allow-list lengkap: kalau
     * dijadikan allow-list, setiap route baru harus didaftarkan atau user
     * terkunci diam-diam saat aplikasinya sudah jalan.
     *
     * Nilai:
     *   roles -> role yang boleh; minimal satu harus beririsan dengan role user
     *   label -> nama yang tampil di pesan penolakan
     */
    public array $restrictedRoutes = [
        'user' => [
            'roles' => ['SUPER_ADMIN', 'ADMIN'],
            'label' => 'Manajemen User',
        ],
    ];

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

    /*
     * Catatan: tidak ada daftar aksi untuk "Application" (API key).
     *
     * Semua user yang punya role boleh membuka halaman dan mengelola
     * application miliknya sendiri; SuperAdmin mengelola semuanya. Jadi
     * aturan management API key BUKAN berbasis role, melainkan berbasis
     * kepemilikan baris `api_applications.created_by`.
     *
     * Penjaga aturan itu ada di AccessService::assertCanManageApiApplication(),
     * bukan di daftar ini.
     */

    /**
     * Label peran untuk ditampilkan di UI.
     */
    public array $roleLabels = [
        'SUPER_ADMIN' => 'SuperAdmin',
        'ADMIN'       => 'Admin',
        'SUPERVISOR'  => 'Supervisor',
        'PETUGAS'     => 'Petugas',
    ];
}