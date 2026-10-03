<?php

namespace App\Models\Access;

use CodeIgniter\Model;

/**
 * Penghubung user <-> role.
 *
 * Tabel ini adalah sumber kebenaran role yang dipakai seluruh aplikasi.
 * Field `roles` yang dikirim UserGate SENGAJA tidak ikut disimpan.
 */
class UserRoleModel extends Model
{
    protected $DBGroup = 'default';

    protected $table = 'user_roles';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    // Tabel `user_roles` hanya punya `created_at` — baris role dicatat
    // sekali dan tidak pernah diubah. Kosongkan updatedField agar CI4
    // tidak ikut menulis kolom yang tidak ada.
    protected $updatedField = '';

    protected $allowedFields = [
        'user_id',
        'role_id',
    ];

    protected $useSoftDeletes = false;

    /**
     * Nama-nama role milik seorang user.
     *
     * @return list<string>
     */
    public function roleNamesFor(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->table('user_roles')
            ->select('roles.name')
            ->join('roles', 'roles.id = user_roles.role_id', 'inner')
            ->where('user_roles.user_id', $userId)
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            $rows
        ));
    }

    /**
     * Role milik seorang user sebagai pasangan id + nama.
     *
     * Dipakai untuk mengisi checkbox role di UI. Pencocokan HARUS lewat
     * `role_id`, bukan nama: nama di tabel (`SUPER_ADMIN`) berbeda dari label
     * tampilan (`SuperAdmin`) yang dikeluarkan Config\Access::$roleLabels, jadi
     * mencocokkan nama dengan label selalu gagal.
     *
     * Id dan nama diambil dalam satu query supaya tidak menambah round-trip
     * per baris tabel.
     *
     * @return list<array{role_id:int, name:string, is_super:int}>
     */
    public function rolesFor(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->table('user_roles')
            ->select('user_roles.role_id, roles.name, roles.is_super')
            ->join('roles', 'roles.id = user_roles.role_id', 'inner')
            ->where('user_roles.user_id', $userId)
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $row): array => [
                'role_id'  => (int) $row['role_id'],
                'name'     => (string) $row['name'],
                'is_super' => (int) $row['is_super'],
            ],
            $rows
        );
    }

    /**
     * @return list<array<string,mixed>> Baris user_roles lengkap dengan nama role.
     */
    public function assignmentsFor(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->db->table('user_roles')
            ->select('user_roles.id, user_roles.role_id, roles.name, roles.is_super')
            ->join('roles', 'roles.id = user_roles.role_id', 'inner')
            ->where('user_roles.user_id', $userId)
            ->get()
            ->getResultArray();
    }

    /**
     * Pasang satu role ke user. Idempotent.
     */
    public function attach(int $userId, int $roleId): void
    {
        if ($userId <= 0 || $roleId <= 0) {
            return;
        }

        $exists = $this->where(['user_id' => $userId, 'role_id' => $roleId])->first();

        if ($exists !== null) {
            return;
        }

        $this->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /**
     * Ganti seluruh role seorang user dengan daftar role_id yang diberikan.
     *
     * @param list<int> $roleIds
     */
    public function sync(int $userId, array $roleIds): void
    {
        if ($userId <= 0) {
            return;
        }

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $this->db->transStart();
        $this->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            if ($roleId > 0) {
                $this->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }
        }

        $this->db->transComplete();
    }

    public function detachAll(int $userId): void
    {
        if ($userId > 0) {
            $this->where('user_id', $userId)->delete();
        }
    }
}
