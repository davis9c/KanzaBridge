<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PegawaiModel;

class Pegawai extends BaseController
{
    protected $pegawaiModel;

    public function __construct()
    {
        $this->pegawaiModel = new PegawaiModel();
    }

    public function index()
    {
        /**
         * USER LOGIN (DARI JWT)
         * Aman: cek dulu apakah diset oleh JwtAuthFilter
         */
        $loginUser = $this->request->user ?? null;

        if (! $loginUser) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 401,
                'message' => 'Unauthorized'
            ]);
        }
        $pegawai = $this->pegawaiModel->getAllWithJabatan();

        $data = array_map(function ($row) {
            //$row['nik'] = hashid_encode($row['nik']);
            return $row;
        }, $pegawai);

        return $this->response->setJSON([
            'status'     => 200,
            'message'    => 'Data Pegawai',
            // 'login_user' => $loginUser, // ❌ sebaiknya jangan expose di production
            'data'       => $data,
        ]);
    }
    public function getByIds()
    {
        $input = $this->request->getJSON(true);

        if (! isset($input['ids']) || ! is_array($input['ids'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'ids wajib array'
            ]);
        }

        $pegawai = $this->pegawaiModel
            ->select('id, nama')
            /**
             * tambahkan tampilkan jabatan untuk petugas dan dokter spesialis jika dokter
             */
            ->whereIn('id', $input['ids'])
            ->findAll();

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $pegawai
        ]);
    }
    public function getByNik()
    {
        $input = $this->request->getJSON(true);

        if (! isset($input['nik'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 400,
                'message' => 'nik wajib array'
            ]);
        }

        $pegawai = $this->pegawaiModel
            ->select('id, nama')
            ->where('nik', $input['nik'])
            ->first();

        return $this->response->setJSON([
            'status' => 200,
            'data'   => $pegawai
        ]);
    }
}
