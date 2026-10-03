<?php

namespace App\Controllers\Api\V2;

use App\Models\Api\DokterModel;
use App\Models\PegawaiModel;

/**
 * Endpoint pegawai API V2.
 *
 * Sumber data: tabel `pegawai` (+ `petugas`, `jabatan` untuk lookup
 * jabatan) di sik_beta, dibaca saja.
 *
 * Seluruh method memakai model yang sama dengan API V1 supaya bentuk
 * respons keduanya bisa dibandingkan byte per byte saat migrasi klien.
 */
class Pegawai extends BaseApiV2Controller
{
    protected PegawaiModel $pegawaiModel;

    protected DokterModel $dokterModel;

    public function __construct()
    {
        $this->pegawaiModel = new PegawaiModel();
        $this->dokterModel  = new DokterModel();
    }

    /**
     * GET api/v2/pegawai
     */
    public function index()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->pegawaiModel->getAllWithJabatan(),
        ], 'Data Pegawai');
    }

    /**
     * POST api/v2/pegawai/by-ids
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function getByIds()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
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

    /**
     * POST api/v2/pegawai/by-nik
     *
     * Body: { "nik": "..." }
     */
    public function getByNik()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
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

    /**
     * POST api/v2/pegawai/dokter
     *
     * Pakar dijejakkan di API V1: path-nya berada di bawah /pegawai tapi
     * controller yang melayani adalah Api\Dokter. Di V2 jalur ini
     * sengaja dipisah supaya setiap path punya satu controller,
     * sementara isi responsnya tetap sama persis.
     */
    public function dokter()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->dokterModel->findAll(),
        ], 'Data dokter');
    }
}