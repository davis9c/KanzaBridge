<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PetugasModel;

class Petugas extends BaseController
{
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->petugasModel = new PetugasModel();
    }

    /**
     * Cek akses / info user login
     */
    public function index()
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

        return $this->response->setJSON([
            'status' => 200,
            'user'   => $loginUser,
        ]);
    }
    public function getByJbtn()
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
        $input = $this->request->getJSON(true);

        if (! isset($input['kd_jbtn'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'kd_jbtn wajib array'
            ]);
        }

        return $this->response->setJSON([
            'status' => 200,
            'user'   => $loginUser,
        ]);
    }

    /**
     * List petugas + jabatan
     */
    public function DanJabatan()
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

        /**
         * Ambil filter jabatan
         * support: JSON body, form-data, query string
         */
        $kd_jbtn = $this->request->getVar('jbtn');

        /**
         * Ambil data dari model
         */
        if ($kd_jbtn) {
            $data = $this->petugasModel->danJabatanByJabatan($kd_jbtn);
        } else {
            $data = $this->petugasModel->danJabatan();
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status' => 200,
                'data'   => $data,
            ]);
    }
    public function getByNips()
    {
        $input = $this->request->getJSON(true);

        if (! isset($input['nips']) || ! is_array($input['nips'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'NIPS wajib array'
            ]);
        }

        $pegawai = $this->petugasModel
            ->select(
                'petugas.nip,
                petugas.nama,
                petugas.kd_jbtn,
                jabatan.nm_jbtn'
            )
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->whereIn('nip', $input['nips'])
            ->findAll();

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $pegawai
        ]);
    }
    public function getByNip()
    {
        $input = $this->request->getJSON(true);

        if (! isset($input['nip'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'Parameter nips wajib berupa array'
            ]);
        }

        $pegawai = $this->petugasModel
            ->select(
                'petugas.nip,
             petugas.nama,
             petugas.kd_jbtn,
             jabatan.nm_jbtn'
            )
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('petugas.nip', $input['nip'])
            ->findAll();

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $pegawai
        ]);
    }
}
