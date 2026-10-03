<?php

namespace App\Controllers\Api\V2;

use App\Models\Api\DokterModel;

/**
 * Endpoint dokter API V2.
 *
 * Sumber data: tabel `dokter` di sik_beta, dibaca saja.
 */
class Dokter extends BaseApiV2Controller
{
    protected DokterModel $dokterModel;

    public function __construct()
    {
        $this->dokterModel = new DokterModel();
    }

    /**
     * POST api/v2/dokter
     *
     * POST dipakai hanya karena bentuk request; tidak ada data yang
     * diubah, sama seperti API V1.
     */
    public function index()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->dokterModel->findAll(),
        ], 'Data dokter');
    }

    /**
     * POST api/v2/dokter/dan-spesialis
     *
     * Bergabung ke tabel `spesialis`. Tabel itu tidak ada di seluruh
     * database sik_beta yang pernah dipakai, jadi endpoint ini memang
     * akan gagal sampai tabel spesialis tersedia. Perilaku ini sengaja
     * dipertahankan agar V1 dan V2 jujur sama: yang bermasalah
     * diperbaiki di kedua sisi, bukan disembunyikan di satu sisi saja.
     */
    public function danSpesialis()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->dokterModel->danSpesialis(),
        ], 'Data dokter dan spesialis');
    }
}