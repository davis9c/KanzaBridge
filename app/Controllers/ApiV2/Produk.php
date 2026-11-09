<?php

namespace App\Controllers\ApiV2;

use CodeIgniter\HTTP\ResponseInterface;

class Produk extends BaseController
{
    public function index()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        return $this->respondSuccess([
            'produk' => [
                ['id' => 1, 'name' => 'Contoh Produk A', 'price' => 15000],
                ['id' => 2, 'name' => 'Contoh Produk B', 'price' => 25000],
            ],
        ], 'Daftar produk');
    }

    public function show($id)
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        return $this->respondSuccess([
            'produk' => ['id' => (int) $id, 'name' => 'Contoh Produk', 'price' => 15000],
        ], 'Detail produk');
    }
}
