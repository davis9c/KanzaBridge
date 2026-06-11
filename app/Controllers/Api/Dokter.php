<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;
use App\Models\DokterModel;

class Dokter extends BaseApiController
{
    protected DokterModel $dokterModel;

    public function __construct()
    {
        $this->dokterModel = new DokterModel();
    }

    public function index()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $data = $this->dokterModel->findAll();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Data dokter');
    }

    public function danSpesialis()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $data = $this->dokterModel->danSpesialis();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Data dokter dan spesialis');
    }
}
