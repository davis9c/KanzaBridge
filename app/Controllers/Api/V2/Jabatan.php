<?php

namespace App\Controllers\Api\V2;

use App\Models\Api\JabatanModel;
use App\Models\PetugasModel;

/**
 * Endpoint jabatan API V2.
 *
 * Sumber data: tabel `jabatan` dan `petugas` di sik_beta, dibaca saja.
 */
class Jabatan extends BaseApiV2Controller
{
    protected JabatanModel $jabatanModel;

    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->jabatanModel  = new JabatanModel();
        $this->petugasModel = new PetugasModel();
    }

    /**
     * GET api/v2/jabatan
     */
    public function index()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->jabatanModel->findAll(),
        ], 'Data jabatan');
    }

    /**
     * GET api/v2/jabatan/with-petugas
     *
     * Setiap jabatan dikembalikan bersama array petugas di bawahnya.
     * Pengelompokan dilakukan di PHP, bukan lewat GROUP_CONCAT, supaya
     * urutannya deterministik dan tidak bergantung mode SQL server.
     */
    public function withPetugas()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
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
        unset($item);

        return $this->respondSuccess([
            'data' => $jabatan,
        ], 'Data jabatan dengan petugas');
    }
}