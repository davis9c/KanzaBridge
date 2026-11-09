<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKbApiV2Tables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tb_kb_role');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'unique' => true, 'null' => true],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('role_id', 'tb_kb_role', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tb_kb_user');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token' => ['type' => 'VARCHAR', 'constraint' => 255, 'unique' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'tb_kb_user', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tb_kb_token');
    }

    public function down()
    {
        $this->forge->dropTable('tb_kb_token', true);
        $this->forge->dropTable('tb_kb_user', true);
        $this->forge->dropTable('tb_kb_role', true);
    }
}
