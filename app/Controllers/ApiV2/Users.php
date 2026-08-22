<?php

namespace App\Controllers\ApiV2;

use CodeIgniter\HTTP\ResponseInterface;

class Users extends BaseController
{
    public function index()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $roleSlug = $auth['role']['slug'] ?? 'user';

        if ($roleSlug === 'user') {
            $users = [$this->userModel->find($auth['user']['id'])];
        } else {
            $users = $this->userModel->listForRole($roleSlug, (int) $auth['user']['id']);
        }

        return $this->respondSuccess([
            'users' => array_map([$this, 'normalizeUserPayload'], $users),
        ], 'OK');
    }

    public function show($id)
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return $this->respondError('User tidak ditemukan', 404);
        }

        if (($auth['role']['slug'] ?? 'user') === 'user' && (int) $auth['user']['id'] !== (int) $user['id']) {
            return $this->respondError('Forbidden', 403);
        }

        return $this->respondSuccess([
            'user' => $this->normalizeUserPayload($user),
        ], 'OK');
    }

    public function create()
    {
        $auth = $this->requireRole(['super_admin', 'admin']);

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $input = $this->getJsonInput();
        $roleSlug = $input['role'] ?? 'user';
        $role = $this->roleModel->findBySlug($roleSlug);

        if (! $role) {
            return $this->respondError('Role tidak valid', 400);
        }

        if (($auth['role']['slug'] ?? 'user') === 'admin' && in_array($roleSlug, ['super_admin', 'admin'], true)) {
            return $this->respondError('Admin tidak dapat membuat akun super admin atau admin', 403);
        }

        $userId = $this->userModel->createUser([
            'username' => $input['username'] ?? null,
            'email' => $input['email'] ?? null,
            'full_name' => $input['full_name'] ?? null,
            'password_hash' => password_hash($input['password'] ?? 'Password123!', PASSWORD_BCRYPT),
            'role_id' => $role['id'],
            'status' => $input['status'] ?? 'active',
        ]);

        $user = $this->userModel->find($userId);

        return $this->respondSuccess([
            'user' => $this->normalizeUserPayload($user),
        ], 'User berhasil dibuat', 201);
    }

    public function update($id)
    {
        $auth = $this->requireRole(['super_admin', 'admin']);

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return $this->respondError('User tidak ditemukan', 404);
        }

        if (($auth['role']['slug'] ?? 'user') === 'admin' && in_array($user['role_id'], [$this->roleModel->getRoleIdBySlug('super_admin'), $this->roleModel->getRoleIdBySlug('admin')], true)) {
            return $this->respondError('Admin tidak dapat mengubah akun super admin atau admin', 403);
        }

        $input = $this->getJsonInput();
        $data = [];

        foreach (['full_name', 'email', 'status'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        if (! empty($input['password'])) {
            $data['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        if (! empty($input['role'])) {
            $role = $this->roleModel->findBySlug($input['role']);
            if (! $role) {
                return $this->respondError('Role tidak valid', 400);
            }
            $data['role_id'] = $role['id'];
        }

        $this->userModel->update($id, $data);
        $updatedUser = $this->userModel->find($id);

        return $this->respondSuccess([
            'user' => $this->normalizeUserPayload($updatedUser),
        ], 'User berhasil diperbarui');
    }

    public function delete($id)
    {
        $auth = $this->requireRole('super_admin');

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return $this->respondError('User tidak ditemukan', 404);
        }

        $this->userModel->delete($id);

        return $this->respondSuccess([], 'User berhasil dihapus');
    }

    public function roles()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        return $this->respondSuccess([
            'roles' => $this->roleModel->findAll(),
        ], 'OK');
    }
}
