<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

class User extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * GET api/users
     */
    public function index()
    {
        $users = $this->userModel->getAllUsers();

        $data = $users;

        return $this->response->setJSON([
            'status'  => 200,
            'message' => 'Data user',
            'data'    => $data,
        ]);
    }
}
