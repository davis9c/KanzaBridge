<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\JabatanModel;

class Jabatan extends BaseController
{
    protected JabatanModel $jabatanModel;
    public function __construct()
    {
        $this->jabatanModel = new JabatanModel();
    }
    public function index()
    {
        /**
         * menampilkan jabatan dan kode jabatan
         */
        $loginUser = $this->request->user ?? null;

        if (! $loginUser) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Unauthorized',
                ]);
        }
        $data = $this->jabatanModel->findAll();
        return $this->response->setJSON([
            'status' => 200,
            'data'   => $data,
        ]);
    }
}
