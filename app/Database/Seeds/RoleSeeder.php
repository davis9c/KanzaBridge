<?php

namespace App\Database\Seeds;

use App\Models\Access\RoleModel;
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
 *
 * Perhatikan: role bawaan sebenarnya sudah dijamin ada setiap kali user
 * login, lewat RoleModel::ensureDefaults(). Seeder ini karena itu hanya
 * langkah pemeliharaan — dipakai setelah restore backup atau ketika
 * database disiapkan tanpa lewat login.
 */
class RoleSeeder extends Seeder
{
    /**
     * Daftar role di-delegate ke RoleModel::DEFAULT_ROLES supaya tidak ada
     * dua sumber kebenaran. Seeder tetap ada karena langkah ini kadang perlu
     * dijalankan manual, misalnya setelah restore backup.
     *
     * @var list<array{name:string, description:string, is_super:int}>
     */
    private const ROLES = RoleModel::DEFAULT_ROLES;

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
