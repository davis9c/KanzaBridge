<?php

namespace App\Controllers\Api\V2;

use App\Models\PetugasModel;

/**
 * Endpoint petugas API V2.
 *
 * Sumber data: tabel `petugas` (+ `jabatan`) di sik_beta, dibaca saja.
 *
 * Di API V1 endpoint `petugas/DanJabatan` juga menjadi tempat integrator
 * mengecek apakah key-nya hidup. Di V2 peran itu pindah ke
 * `GET api/v2/me`, sehingga semua endpoint di sini murni data.
 */
class Petugas extends BaseApiV2Controller
{
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->petugasModel = new PetugasModel();
    }

    /**
     * POST api/v2/petugas/dan-jabatan
     *
     * Bisa disaring dengan `?jbtn=D1010` (boleh lebih dari satu).
     */
    public function danJabatan()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        $kdJbtn = $this->request->getVar('jbtn');

        if (! empty($kdJbtn) && ! is_array($kdJbtn)) {
            $kdJbtn = [$kdJbtn];
        }

        $data = ! empty($kdJbtn)
            ? $this->petugasModel->danJabatanByJabatan($kdJbtn)
            : $this->petugasModel->danJabatan();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Daftar petugas dan jabatan');
    }

    /**
     * POST api/v2/petugas/by-jbtn
     *
     * Body: { "kd_jbtn": "D1010" } atau { "kd_jbtn": ["D1010", "D1020"] }
     */
    public function getByJbtn()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        $input = $this->getJsonInput();

        if (! isset($input['kd_jbtn'])) {
            return $this->respondError('kd_jbtn wajib diisi', 400);
        }

        $kdJbtn = $input['kd_jbtn'];

        if (! is_array($kdJbtn)) {
            $kdJbtn = [$kdJbtn];
        }

        return $this->respondSuccess([
            'data' => $this->petugasModel->danJabatanByJabatan($kdJbtn),
        ], 'Data petugas berdasarkan kd_jbtn');
    }

    /**
     * POST api/v2/petugas/by-nips
     *
     * Body: { "nips": ["...", "..."] }
     */
    public function getByNips()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        $input = $this->getJsonInput();

        if (! isset($input['nips']) || ! is_array($input['nips'])) {
            return $this->respondError('NIPS wajib array', 400);
        }

        $data = $this->petugasModel
            ->select('petugas.nip, petugas.nama, petugas.kd_jbtn, jabatan.nm_jbtn')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->whereIn('petugas.nip', $input['nips'])
            ->findAll();

        return $this->respondSuccess([
            'data' => $data,
        ], 'Data petugas berdasarkan nips');
    }

    /**
     * POST api/v2/petugas/by-nip
     *
     * Body: { "nip": "..." }
     */
    public function getByNip()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        $input = $this->getJsonInput();

        if (! isset($input['nip'])) {
            return $this->respondError('Parameter nip wajib diisi', 400);
        }

        $data = $this->petugasModel
            ->select('petugas.nip, petugas.nama, petugas.kd_jbtn, jabatan.nm_jbtn')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('petugas.nip', $input['nip'])
            ->first();

        if (! $data) {
            return $this->respondError('Data petugas tidak ditemukan', 404);
        }

        return $this->respondSuccess([
            'data' => $data,
        ], 'Data petugas berdasarkan nip');
    }
}