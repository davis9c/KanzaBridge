<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\PegawaiModel;
use CodeIgniter\HTTP\ResponseInterface;

class Pegawai extends BaseController
{
    protected $pegawaiModel;
    public function __construct()
    {
        $this->db           = \Config\Database::connect('khanza');
        $this->pegawaiModel = new PegawaiModel();
    }
    public function user()
    {
        $data = [
            'title' => 'Pegawai'
        ];
        return view('pegawai', $data);
    }
}
