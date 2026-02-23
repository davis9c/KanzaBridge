<?php

namespace App\Models;

use CodeIgniter\Model;

class PegawaiModel extends Model
{
    protected $table = 'pegawai';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [];
    protected $protectFields = false;
    public function getByNik(string $nik)
    {
        return $this->select('id, nik, nama')
            ->where('nik', $nik)
            ->first();
    }
    public function getPegawaiLengkap(string $nik)
    {
        return $this->db->table('pegawai')
            ->select('pegawai.*, petugas.kd_jbtn, jabatan.nm_jbtn')
            ->join('petugas', 'petugas.nip = pegawai.nik', 'left')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('pegawai.nik', $nik)
            ->get()
            ->getRowArray();
    }
    /**
     * Ambil pegawai + jabatan
     */
    public function getAllWithJabatan(): array
    {
        return $this->db->table('pegawai')
            ->select('
                pegawai.*,
                petugas.kd_jbtn,
                jabatan.nm_jbtn
            ')
            ->join('petugas', 'petugas.nip = pegawai.nik', 'left')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->get()
            ->getResultArray();
    }
}
