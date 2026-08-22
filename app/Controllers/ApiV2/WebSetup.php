<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;

class WebSetup extends BaseController
{
    public function index()
    {
        return view('api_v2/setup');
    }

    public function save()
    {
        $model = new \App\Models\ApiV2\RoleModel();
        $model->ensureDefaultRoles();

        $userModel = new \App\Models\ApiV2\UserModel();
        if ($userModel->hasSuperAdmin()) {
            return redirect()->to('/kb-admin/setup')->with('error', 'Super admin sudah dibuat.');
        }

        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'full_name' => $this->request->getPost('full_name'),
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'role_id' => $model->getRoleIdBySlug('super_admin'),
            'status' => 'active',
        ];

        $userModel->createUser($data);

        return redirect()->to('/kb-admin/setup')->with('success', 'Super admin berhasil dibuat.');
    }
}
