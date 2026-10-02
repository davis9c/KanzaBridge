<?php

namespace App\Models\Access;

use CodeIgniter\Model;

/**
 * Role lokal.
 *
 * Sengaja di namespace App\Models\Access — BUKAN digabung ke
 * App\Models\RoleModel / App\Models\UserModel yang dipakai lapisan API
 * yang sudah live.
 */
class RoleModel extends Model
{
    protected $DBGroup = 'default';

    protected $table = 'roles';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $allowedFields = [
        'name',
        'description',
        'is_super',
    ];

    protected $useSoftDeletes = false;

    /**
     * Semua role, urut dari super admin.
     */
    public function listRoles(): array
    {
        return $this->orderBy('is_super', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }

    /**
     * Pastikan role ada; kalau belum, buat. Dipakai saat bootstrap
     * SuperAdmin agar tidak bergantung pada RoleSeeder.
     */
    public function ensure(string $name, string $description = '', int $isSuper = 0): int
    {
        $role = $this->findByName($name);

        if ($role !== null) {
            return (int) $role['id'];
        }

        $this->insert([
            'name'        => $name,
            'description' => $description,
            'is_super'    => $isSuper,
        ]);

        return (int) $this->getInsertID();
    }
}
