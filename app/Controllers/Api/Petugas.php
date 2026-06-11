<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;
use App\Models\PetugasModel;

class Petugas extends BaseApiController
{
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->petugasModel = new PetugasModel();
    }

    /**
     * Cek akses / info user login.
     */
    public function index()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        return $this->respondSuccess([
            'user' => $loginUser,
        ], 'Informasi user login');
    }

    public function getByJbtn()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $input = $this->getJsonInput();

        if (! isset($input['kd_jbtn'])) {
            return $this->respondError('kd_jbtn wajib array', 400);
        }

        $kd_jbtn = $input['kd_jbtn'];
        if (! is_array($kd_jbtn)) {
            $kd_jbtn = [$kd_jbtn];
        }

        $data = $this->petugasModel->danJabatanByJabatan($kd_jbtn);

        return $this->respondSuccess([
            'data' => $data,
        ], 'Data petugas berdasarkan kd_jbtn');
    }

    /**
     * List petugas + jabatan.
     */
    public function DanJabatan()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $kd_jbtn = $this->request->getVar('jbtn');

        if (! empty($kd_jbtn) && ! is_array($kd_jbtn)) {
            $kd_jbtn = [$kd_jbtn];
        }

        $data = ! empty($kd_jbtn)
            ? $this->petugasModel->danJabatanByJabatan($kd_jbtn)
            : $this->petugasModel->danJabatan();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Daftar petugas dan jabatan');
    }

    public function getByNips()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $input = $this->getJsonInput();

        if (! isset($input['nips']) || ! is_array($input['nips'])) {
            return $this->respondError('NIPS wajib array', 400);
        }

        $pegawai = $this->petugasModel
            ->select('petugas.nip, petugas.nama, petugas.kd_jbtn, jabatan.nm_jbtn')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->whereIn('petugas.nip', $input['nips'])
            ->findAll();

        return $this->respondSuccess([
            'data' => $pegawai,
        ], 'Data petugas berdasarkan nips');
    }

    public function getByNip()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        $input = $this->getJsonInput();

        if (! isset($input['nip'])) {
            return $this->respondError('Parameter nip wajib diisi', 400);
        }

        $pegawai = $this->petugasModel
            ->select('petugas.nip, petugas.nama, petugas.kd_jbtn, jabatan.nm_jbtn')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('petugas.nip', $input['nip'])
            ->first();

        if (! $pegawai) {
            return $this->respondError('Data petugas tidak ditemukan', 404);
        }

        return $this->respondSuccess([
            'data' => $pegawai,
        ], 'Data petugas berdasarkan nip');
    }
}
