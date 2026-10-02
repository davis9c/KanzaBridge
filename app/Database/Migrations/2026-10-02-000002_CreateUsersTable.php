<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel user lokal.
 *
 * Akun & password tetap hidup di UserGate; tabel ini hanya menyimpan
 * identitas yang sudah disalin (username/email/nama) plus keputusan
 * otorisasi lokal (status akses, role, link NIK ke data pegawai).
 *
 * Kolom `usergate_id` adalah UUID dari UserGate dan menjadi kunci
 * pencocokan saat user login.
 */
class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usergate_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'ACTIVE',
            ],
            // Opsional: NIK untuk menyambung ke sik_beta.pegawai.
            // Diisi manual dari UI Manajemen User.
            'nik' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Catatan: argumen kedua addKey() berarti PRIMARY KEY, bukan unique.
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('usergate_id');
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('status');

        $this->forge->createTable('users', true);
    }

    public function down()
    {
        $this->forge->dropTable('users', true);
    }
}
