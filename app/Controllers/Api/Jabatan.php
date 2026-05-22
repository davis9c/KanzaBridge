<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\JabatanModel;
use App\Models\PetugasModel;

class Jabatan extends BaseController
{
    protected JabatanModel $jabatanModel;
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->jabatanModel = new JabatanModel();
        $this->petugasModel = new PetugasModel();
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

    public function withPetugas()
    {
        $loginUser = $this->request->user ?? null;

        if (! $loginUser) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Unauthorized',
                ]);
        }

        $jabatan = $this->jabatanModel->findAll();
        $petugas = $this->petugasModel
            ->select('petugas.nip, petugas.nama, petugas.kd_jbtn')
            ->findAll();

        $petugasByJbtn = [];
        foreach ($petugas as $item) {
            $petugasByJbtn[$item['kd_jbtn']][] = $item;
        }

        foreach ($jabatan as &$item) {
            $item['petugas'] = $petugasByJbtn[$item['kd_jbtn']] ?? [];
        }

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $jabatan,
        ]);
    }
}

