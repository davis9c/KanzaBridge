<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menghapus kolom `users.nik`.
 *
 * Kolom ini awalnya dipakai untuk menyambungkan akun lokal ke tabel
 * `pegawai` di khanza, sehingga kartu "Data Pegawai" bisa tampil di
 * halaman Profil. Fitur tersebut sudah dihapus, jadi kolomnya ikut
 * dibuang agar skema tidak menyisakan kolom mati.
 *
 * Migrasi 2026-10-02-000002 yang membuat tabel `users` SENGAJA TIDAK
 * disentuh — riwayat migrasi harus tetap jujur terhadap apa yang
 * benar-benar dijalankan di database.
 */
class DropUsersNikColumn extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('users', 'nik', true);
    }

    public function down()
    {
        $fields = [
            'nik' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'status',
            ],
        ];

        $this->forge->addColumn('users', $fields);
    }
}
