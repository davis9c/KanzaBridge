<?php

namespace App\Models\Access;

use CodeIgniter\Model;

/**
 * Role lokal.
 *
 * Sengaja di namespace App\Models\Access — BUKAN digabung ke
 * App\Models\RoleModel / App\Models\UserModel yang dipakai lapisan API
 * yang sudah live.
 */
class RoleModel extends Model
{
    /**
     * Role bawaan aplikasi.
     *
     * Disimpan di sini (bukan hanya di RoleSeeder) supaya bisa dijamin
     * ada setiap kali user login — lihat ensureDefaults(). RoleSeeder
     * memakai daftar yang sama supaya tidak ada dua sumber kebenaran.
     *
     * Kolom `is_super` hanya menandai SuperAdmin. Urutan antar-role TIDAK
     * disimpan di sini, tapi di Config\Access::$roleRank.
     *
     * @var list<array{name:string, description:string, is_super:int}>
     */
    public const DEFAULT_ROLES = [
        [
            'name'        => 'SUPER_ADMIN',
            'description' => 'Akses penuh, termasuk menetapkan SuperAdmin dan menghapus user.',
            'is_super'    => 1,
        ],
        [
            'name'        => 'ADMIN',
            'description' => 'Akses biasa ke seluruh menu. Tidak dapat menetapkan role ADMIN dan tidak dapat menyentuh akun setingkatnya sendiri.',
            'is_super'    => 0,
        ],
        [
            'name'        => 'SUPERVISOR',
            'description' => 'Semua menu kecuali Manajemen User. Application dan API key miliknya sendiri.',
            'is_super'    => 0,
        ],
        [
            'name'        => 'PETUGAS',
            'description' => 'Untuk penggunaan harian. Semua menu kecuali Manajemen User; Application dan API key miliknya sendiri.',
            'is_super'    => 0,
        ],
    ];

    protected $DBGroup = 'default';

    protected $table = 'roles';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $allowedFields = [
        'name',
        'description',
        'is_super',
    ];

    protected $useSoftDeletes = false;

    /**
     * Semua role, urut dari super admin.
     */
    public function listRoles(): array
    {
        return $this->orderBy('is_super', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }

    /**
     * Pastikan role ada; kalau belum, buat. Dipakai saat bootstrap
     * SuperAdmin agar tidak bergantung pada RoleSeeder.
     */
    public function ensure(string $name, string $description = '', int $isSuper = 0): int
    {
        $role = $this->findByName($name);

        if ($role !== null) {
            return (int) $role['id'];
        }

        $this->insert([
            'name'        => $name,
            'description' => $description,
            'is_super'    => $isSuper,
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * Pastikan SEMUA role bawaan ada, lalu kembalikan daftar lengkapnya.
     *
     * Dipanggil setiap kali user berhasil login, bukan hanya saat tabel
     * `roles` kosong. Alasannya: daftar role di form "Ubah User" dibaca
     * langsung dari tabel ini. Kalau hanya SUPER_ADMIN yang dibuat saat
     * bootstrap, role ADMIN tidak pernah muncul di form — dan administrator
     * tidak bisa memberikan role itu tanpa harus menjalankan RoleSeeder
     * secara manual lebih dulu.
     *
     * Idempotent, jadi aman dipanggil pada setiap login. Role yang sudah
     * ada tidak disentuh — role_id yang sudah dirujuk `user_roles` tetap
     * sama.
     *
     * @return list<array<string,mixed>>
     */
    public function ensureDefaults(): array
    {
        foreach (self::DEFAULT_ROLES as $role) {
            $this->ensure($role['name'], $role['description'], $role['is_super']);
        }

        return $this->listRoles();
    }
}
