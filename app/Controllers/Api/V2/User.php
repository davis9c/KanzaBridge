<?php

namespace App\Controllers\Api\V2;

use App\Models\UserModel;

/**
 * Endpoint user API V2.
 *
 * Sumber data: tabel `user` di sik_beta, dibaca saja.
 *
 * CATATAN SENSITITIF: tabel `user` SIMRS mengembalikan kolom setelan
 * dan kredensial dalam bentuk terenkripsi AES. Endpoint ini ada supaya
 * parity dengan API V1, tapi scope `users.read` sebaiknya hanya diberikan
 * kepada integrasi yang benar-benar membutuhkannya.
 */
class User extends BaseApiV2Controller
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * GET api/v2/users
     */
    public function index()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        return $this->respondSuccess([
            'data' => $this->userModel->getAllUsers(),
        ], 'Data user');
    }
}