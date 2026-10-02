<?php

namespace App\Controllers\Api;

use App\Models\Api\JabatanModel;
use App\Models\PetugasModel;

class Jabatan extends BaseApiController
{
    protected JabatanModel $jabatanModel;
    protected PetugasModel $petugasModel;

    public function __construct()
    {
        $this->jabatanModel = new JabatanModel();
        $this->petugasModel = new PetugasModel();
    }

    /**
     * GET api/jabatan
     *
     * menampilkan jabatan dan kode jabatan
     */
    public function index()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
        }

        return $this->respondSuccess([
            'data' => $this->jabatanModel->findAll(),
        ], 'Data jabatan');
    }

    /**
     * GET api/jabatan/with-petugas
     *
     * menampilkan jabatan beserta petugas di bawahnya
     */
    public function withPetugas()
    {
        $loginUser = $this->requireAuth();
        if ($loginUser instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $loginUser;
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
