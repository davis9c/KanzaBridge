<?php

use App\Models\Access\AccessService;
use Config\Access as AccessConfig;

/**
 * Helper akses & role untuk dipakai di controller dan view.
 *
 * Di-autoload lewat Config\Autoload::$helpers.
 *
 * Catatan: tidak ada satupun helper di sini yang menyentuh lapisan API
 * (`/api/*`) — file ini hanya berlaku untuk halaman web.
 */

if (! function_exists('access_service')) {
    /**
     * Instance AccessConfig.
     */
    function access_service(): AccessConfig
    {
        return config(AccessConfig::class);
    }
}

if (! function_exists('current_user')) {
    /**
     * Ringkasan user yang sedang login, dibaca dari session.
     *
     * @return array<string,mixed>
     */
    function current_user(): array
    {
        return [
            'id'            => (int) session('access_user_id'),
            'usergate_id'   => (string) session('access_usergate_id'),
            'username'      => (string) session('access_username'),
            'email'         => (string) session('access_email'),
            'full_name'     => (string) session('access_full_name'),
            'roles'         => session('access_roles') ?: [],
            'is_super'      => (bool) session('access_is_super'),
        ];
    }
}

if (! function_exists('current_user_id')) {
    /**
     * ID lokal user yang sedang login. 0 bila belum login.
     */
    function current_user_id(): int
    {
        return (int) session('access_user_id');
    }
}

if (! function_exists('has_role')) {
    /**
     * Apakah user punya role tertentu?
     */
    function has_role(string $role): bool
    {
        $roles = session('access_roles');

        return is_array($roles) && in_array($role, $roles, true);
    }
}

if (! function_exists('has_any_role')) {
    /**
     * Apakah user punya setidaknya satu role?
     *
     * Ini yang menentukan apakah menu ditampilkan. User tanpa role
     * tetap login, tapi tidak melihat menu apa pun.
     */
    function has_any_role(): bool
    {
        $roles = session('access_roles');

        return is_array($roles) && $roles !== [];
    }
}

if (! function_exists('has_any_of_roles')) {
    /**
     * Apakah user punya salah satu dari role yang diberikan?
     *
     * Berbeda dengan has_any_role() yang hanya menanyakan "punya role apa
     * pun". Yang ini dipakai untuk membatasi route: `Config\Access::$restrictedRoutes`
     * menyebut role yang boleh membuka sebuah route, dan AccessFilter serta
     * topbar membacanya lewat helper ini supaya keduanya memakai satu sumber
     * kebenaran.
     *
     * @param list<string> $roles
     */
    function has_any_of_roles(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        $mine = session('access_roles');

        return is_array($mine) && array_intersect($mine, $roles) !== [];
    }
}

if (! function_exists('is_super_admin')) {
    /**
     * Apakah user adalah SuperAdmin?
     *
     * Nilai di session bisa saja sudah tua, jadi tetap diverifikasi ke DB.
     */
    function is_super_admin(): bool
    {
        static $service = null;

        if ($service === null) {
            $service = new AccessService();
        }

        return $service->isSuperAdmin();
    }
}

if (! function_exists('can')) {
    /**
     * Cek hak akses server-side untuk aksi tertentu.
     *
     * Daftar aksi SuperAdmin ada di Config\Access::$superAdminOnly.
     */
    function can(string $action): bool
    {
        $config = access_service();

        if (in_array($action, $config->superAdminOnly, true)) {
            return is_super_admin();
        }

        // Aksi lain butuh setidaknya satu role.
        return has_any_role();
    }
}

if (! function_exists('role_label')) {
    /**
     * Label ramah untuk ditampilkan di UI.
     */
    function role_label(string $role): string
    {
        $labels = access_service()->roleLabels;

        return $labels[$role] ?? ucfirst(strtolower(str_replace('_', ' ', $role)));
    }
}
