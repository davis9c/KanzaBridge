<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;

class WebUser extends BaseController
{
    public function index()
    {
        $userModel = new \App\Models\ApiV2\UserModel();
        $users = $userModel->listWithRoles();

        return view('api_v2/users', ['users' => $users]);
    }

    public function create()
    {
        $roleModel = new \App\Models\ApiV2\RoleModel();
        $roles = $roleModel->findAll();

        return view('api_v2/user_form', ['roles' => $roles, 'user' => null]);
    }

    public function edit($id)
    {
        $userModel = new \App\Models\ApiV2\UserModel();
        $roleModel = new \App\Models\ApiV2\RoleModel();
        $user = $userModel->findWithRole($id);
        $roles = $roleModel->findAll();

        return view('api_v2/user_form', ['roles' => $roles, 'user' => $user]);
    }

    public function save()
    {
        $userModel = new \App\Models\ApiV2\UserModel();
        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'full_name' => $this->request->getPost('full_name'),
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'role_id' => $this->request->getPost('role_id'),
            'status' => $this->request->getPost('status') ?? 'active',
        ];

        $userModel->createUser($data);

        return redirect()->to('/kb-admin/users')->with('success', 'User berhasil ditambahkan.');
    }

    public function update($id)
    {
        $userModel = new \App\Models\ApiV2\UserModel();
        $data = [
            'email' => $this->request->getPost('email'),
            'full_name' => $this->request->getPost('full_name'),
            'role_id' => $this->request->getPost('role_id'),
            'status' => $this->request->getPost('status') ?? 'active',
        ];

        if ($this->request->getPost('password')) {
            $data['password_hash'] = password_hash($this->request->getPost('password'), PASSWORD_BCRYPT);
        }

        $userModel->update($id, $data);

        return redirect()->to('/kb-admin/users')->with('success', 'User berhasil diperbarui.');
    }

    public function delete($id)
    {
        $userModel = new \App\Models\ApiV2\UserModel();
        $userModel->delete($id);

        return redirect()->to('/kb-admin/users')->with('success', 'User berhasil dihapus.');
    }
}
