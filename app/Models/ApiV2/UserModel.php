<?php

namespace App\Models\ApiV2;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'tb_kb_user';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = ['username', 'email', 'full_name', 'password_hash', 'role_id', 'status'];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    public function createUser(array $data): int
    {
        $this->insert($data);

        return (int) $this->insertID();
    }

    public function hasSuperAdmin(): bool
    {
        $roleModel = new RoleModel();
        $roleId = $roleModel->getRoleIdBySlug('super_admin');

        if (! $roleId) {
            return false;
        }

        return (bool) $this->where('role_id', $roleId)->first();
    }

    public function findByUsernameOrEmail(string $credential): ?array
    {
        return $this->where('username', $credential)
            ->orWhere('email', $credential)
            ->first();
    }

    public function listForRole(string $roleSlug, int $userId): array
    {
        $roleModel = new RoleModel();
        $role = $roleModel->findBySlug($roleSlug);

        if (! $role) {
            return [];
        }

        if ($roleSlug === 'admin') {
            return $this->where('role_id', $role['id'])
                ->orWhere('id', $userId)
                ->findAll();
        }

        return $this->findAll();
    }

    public function listWithRoles(): array
    {
        return $this->select('tb_kb_user.*, tb_kb_role.name as role_name, tb_kb_role.slug as role_slug')
            ->join('tb_kb_role', 'tb_kb_role.id = tb_kb_user.role_id', 'left')
            ->orderBy('tb_kb_user.id', 'ASC')
            ->findAll();
    }

    public function findWithRole(int $id): ?array
    {
        return $this->select('tb_kb_user.*, tb_kb_role.name as role_name, tb_kb_role.slug as role_slug')
            ->join('tb_kb_role', 'tb_kb_role.id = tb_kb_user.role_id', 'left')
            ->where('tb_kb_user.id', $id)
            ->first();
    }
}
