<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;
use App\Models\UserModel;

class User extends BaseApiController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * GET api/users
     *
     * NOTE: Endpoint ini dapat berisi field sensitif.
     * Jangan gunakan di production tanpa menyaring data.
     */
    public function index()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $users = $this->userModel->getAllUsers();

        return $this->respondSuccess([
            'data' => $users,
        ], 'Data user');
    }
}
