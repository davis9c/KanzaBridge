<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;
use App\Models\PegawaiModel;

class Pegawai extends BaseApiController
{
    protected PegawaiModel $pegawaiModel;

    public function __construct()
    {
        $this->pegawaiModel = new PegawaiModel();
    }

    public function index()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $pegawai = $this->pegawaiModel->getAllWithJabatan();

        return $this->respondSuccess([
            'data' => $pegawai,
        ], 'Data Pegawai');
    }

    public function getByIds()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $input = $this->getJsonInput();

        if (! isset($input['ids']) || ! is_array($input['ids'])) {
            return $this->respondError('ids wajib array', 400);
        }

        $pegawai = $this->pegawaiModel
            ->select('id, nama')
            ->whereIn('id', $input['ids'])
            ->findAll();

        return $this->respondSuccess([
            'data' => $pegawai,
        ], 'Data pegawai berdasarkan ids');
    }

    public function getByNik()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $input = $this->getJsonInput();

        if (! isset($input['nik'])) {
            return $this->respondError('nik wajib diisi', 400);
        }

        $pegawai = $this->pegawaiModel
            ->select('id, nama')
            ->where('nik', $input['nik'])
            ->first();

        if (! $pegawai) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        return $this->respondSuccess([
            'data' => $pegawai,
        ], 'Data pegawai');
    }
}
