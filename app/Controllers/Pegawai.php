<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\PegawaiModel;
use CodeIgniter\HTTP\ResponseInterface;

class Pegawai extends BaseController
{
    protected $pegawaiModel;
    public function __construct()
    {
        $this->pegawaiModel = new PegawaiModel();
    }
    public function user()
    {
        $userModel = new UserModel();

        $data['users'] = $userModel->getDecryptedUsers();
        dd($data['users']);
        return response()->setJSON([
            'status' => true,
            'data'   => $data['users']
        ]);
    }
}
