<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\DokterModel;

class Dokter extends BaseController
{
    protected $dokterModel;

    public function __construct()
    {
        $this->dokterModel = new DokterModel();
    }
    public function index()
    {
        $loginUser = $this->request->user ?? null;
        if (! $loginUser) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 401,
                'message' => 'Unauthorized'
            ]);
        }
        $users = $this->dokterModel->findAll();

        $data = $users;

        return $this->response->setJSON([
            'status'  => 200,
            'message' => 'Data user',
            'data'    => $data,
        ]);
    }
    public function danSpesialis()
    {
        $loginUser = $this->request->user ?? null;
        if (! $loginUser) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 401,
                'message' => 'Unauthorized'
            ]);
        }
        $data = $this->dokterModel->danSpesialis();

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $data,
        ]);
    }
}
