<?php

namespace Tests\Support\Database;

use App\Models\Access\RoleModel;
use App\Models\Access\UserRoleModel;

/**
 * Memberi role lokal sungguhan kepada seorang user uji.
 *
 * Kenapa ini perlu: `AccessService::isSuperAdmin()` membaca DB, bukan
 * `access_is_super` di session. Test yang dulu menalsukan SuperAdmin hanya
 * lewat session berarti ia tidak pernah menguji jalur produksi — dan begitu
 * session diabaikan, semua test SuperAdmin ikut gagal karena memang tidak ada
 * role-nya di DB.
 *
 * Jadi setiap helper `loginAs()` harus memasang role sungguhan lewat sini,
 * supaya session dan DB benar-benar sama. Itu membuat test menguji aturan
 * yang benar-benar berlaku di aplikasi.
 */
trait RoleAssignmentTrait
{
    /**
     * @param list<string> $roles Nama role, mis. ['SUPER_ADMIN'] atau ['ADMIN'].
     *
     * @return list<int> Id role yang terpasang.
     */
    protected function assignRoles(int $userId, array $roles): array
    {
        $roleModel = new RoleModel();
        $userRoles = new UserRoleModel();

        // Role bawaan dibuat dulu supaya id-nya stabil dan bisa dirujuk
        // user_roles — attach() hanya menerima id.
        $roleModel->ensureDefaults();

        $assigned = [];

        foreach ($roles as $name) {
            $role = $roleModel->findByName($name);

            if ($role === null) {
                $roleModel->ensure($name, '', $name === 'SUPER_ADMIN' ? 1 : 0);
                $role = $roleModel->findByName($name);
            }

            $this->assertNotNull($role, 'Role "' . $name . '" tidak bisa disiapkan.');

            $userRoles->attach($userId, (int) $role['id']);

            $assigned[] = (int) $role['id'];
        }

        return $assigned;
    }
}