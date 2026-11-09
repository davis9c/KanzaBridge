<?php

namespace App\Models\ApiV2;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table = 'tb_kb_role';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = ['name', 'slug', 'description'];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    public function ensureDefaultRoles(): void
    {
        $defaultRoles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Super administrator'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrator'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'User biasa'],
        ];

        foreach ($defaultRoles as $role) {
            $existing = $this->where('slug', $role['slug'])->first();
            if (! $existing) {
                $this->insert($role);
            }
        }
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    public function getRoleIdBySlug(string $slug): ?int
    {
        $role = $this->where('slug', $slug)->first();

        return $role['id'] ?? null;
    }
}
