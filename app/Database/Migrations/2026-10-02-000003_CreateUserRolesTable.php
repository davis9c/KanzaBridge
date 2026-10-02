<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel penghubung user <-> role.
 *
 * Mengganti pola ENUM pada tabel `users` supaya role bisa bertambah
 * tanpa perlu migrasi ulang, dan supaya satu user secara teori bisa punya
 * lebih dari satu role.
 */
class CreateUserRolesTable extends Migration
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
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Satu user hanya boleh punya satu jenis role yang sama.
        // Argumen kedua addKey() berarti PRIMARY KEY — unique lewat addUniqueKey().
        $this->forge->addUniqueKey(['user_id', 'role_id']);
        $this->forge->addKey('user_id');
        $this->forge->addKey('role_id');

        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('user_roles', true);
    }

    public function down()
    {
        // Drop child table lebih dulu sebelum parent.
        $this->forge->dropTable('user_roles', true);
    }
}
