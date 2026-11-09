<?php

namespace App\Controllers\ApiV2;

class Setup extends BaseController
{
    public function createSuperAdmin()
    {
        $input = $this->getJsonInput();

        if ($this->userModel->hasSuperAdmin()) {
            return $this->respondError('Super admin sudah dibuat sebelumnya', 409);
        }

        $username = $input['username'] ?? 'superadmin';
        $email = $input['email'] ?? 'superadmin@kb.local';
        $fullName = $input['full_name'] ?? 'Super Admin';
        $password = $input['password'] ?? 'SuperAdmin123!';

        if (! $username || ! $email || ! $password) {
            return $this->respondError('username, email, dan password wajib diisi', 400);
        }

        $this->roleModel->ensureDefaultRoles();

        $roleId = $this->roleModel->getRoleIdBySlug('super_admin');

        if (! $roleId) {
            return $this->respondError('Gagal membuat role default', 500);
        }

        $userId = $this->userModel->createUser([
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $user = $this->userModel->find($userId);

        return $this->respondSuccess([
            'user' => $this->normalizeUserPayload($user),
        ], 'Super admin berhasil dibuat', 201);
    }
}
