<?php

namespace App\Models;

use CodeIgniter\Model;

class PetugasModel extends Model
{
    protected $table = 'petugas';
    protected $primaryKey = 'nip';
    protected $returnType = 'array';

    /**
     * Untuk API Auth
     */
    public function getPetugasAuth(string $nik)
    {
        return $this->select('
                petugas.kd_jbtn,
                jabatan.nm_jbtn
            ')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('petugas.nip', $nik)
            ->first();
    }

    /**
     * untuk Browser
     * 
     */
    public function getPegawaiLengkap(string $nik)
    {
        return $this->select('
                pegawai.*,
                petugas.kd_jbtn,
                jabatan.nm_jbtn
            ')
            ->join('petugas', 'petugas.nip = pegawai.nik', 'left')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('pegawai.nik', $nik)
            ->first();
    }
    /**
     * Ambil data pegawai + kode jabatan + nama jabatan
     * Basis utama: pegawai
     */
    public function getPegawaiWithJabatan(string $nik): ?array
    {
        return $this->db->table('pegawai')
            ->select('
                pegawai.nik,
                pegawai.nama,
                pegawai.departemen,
                petugas.kd_jbtn,
                jabatan.nm_jbtn
            ')
            ->join('petugas', 'petugas.nip = pegawai.nik', 'left')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('pegawai.nik', $nik)
            ->get()
            ->getRowArray();
    }
    public function danJabatan(): ?array
    {
        return $this->select('
                petugas.*,
                jabatan.nm_jbtn
            ')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->get()
            ->getResultArray();
    }
    public function danJabatanByJabatan($kd_jbtn)
    {
        return $this->select('
                petugas.nip,
                petugas.nama,
                petugas.kd_jbtn,
                jabatan.nm_jbtn
            ')
            ->join('jabatan', 'jabatan.kd_jbtn = petugas.kd_jbtn', 'left')
            ->where('petugas.kd_jbtn', $kd_jbtn)
            ->get()
            ->getResultArray();
    }
}
