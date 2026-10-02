<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;
use Config\UserGate as UserGateConfig;

/**
 * Seed role default.
 *
 * Idempotent — aman dijalankan berkali-kali lewat
 * `php spark db:seed RoleSeeder -n App`.
 *
 * TIDAK membuat user apa pun. User pertama yang login ke UserGate
 * otomatis menjadi SuperAdmin (lihat AccessService::authenticate).
 */
class RoleSeeder extends Seeder
{
    private const ROLES = [
        [
            'name'        => 'SUPER_ADMIN',
            'description' => 'Akses penuh, termasuk menetapkan SuperAdmin dan menghapus user.',
            'is_super'    => 1,
        ],
        [
            'name'        => 'ADMIN',
            'description' => 'Akses biasa ke seluruh menu, tanpa hak menetapkan SuperAdmin.',
            'is_super'    => 0,
        ],
    ];

    public function run()
    {
        $db = Database::connect(config(UserGateConfig::class)->dbGroup);
        $now = date('Y-m-d H:i:s');

        foreach (self::ROLES as $role) {
            $existing = $db->table('roles')->where('name', $role['name'])->get()->getRowArray();

            $data = [
                'description' => $role['description'],
                'is_super'    => $role['is_super'],
                'updated_at'  => $now,
            ];

            if ($existing === null) {
                $db->table('roles')->insert($data + ['name' => $role['name'], 'created_at' => $now]);
                continue;
            }

            // Role sudah ada — samakan deskripsi/is_super tanpa membuat
            // baris baru, supaya role_id yang sudah direujuk user_roles
            // tidak ikut berubah.
            $db->table('roles')->where('id', $existing['id'])->update($data);
        }
    }
}
