<?php

namespace Tests\Support\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\UserGate as UserGateConfig;
use RuntimeException;

/**
 * Basis untuk test yang menyentuh tabel aplikasi KanzaBridge (`users`,
 * `roles`, `user_roles`, `api_applications`, `api_keys`, `api_key_scopes`).
 *
 * Tabel-tabel itu berisi data nyata, jadi test TIDAK boleh dijalankan
 * di environment production. Trait ini memaksa berhenti lebih dulu
 * daripada diam-diam menghapus data.
 */
trait LocalAccessDatabaseTrait
{
    protected function setUpLocalAccessDatabase(): void
    {
        $this->assertSafeToTruncate();

        $this->db = \Config\Database::connect(config(UserGateConfig::class)->dbGroup);

        $this->truncateAccessTables();
    }

    protected function tearDownLocalAccessDatabase(): void
    {
        if (isset($this->db)) {
            $this->truncateAccessTables();
        }
    }

    /**
     * Nama database yang boleh dikosongkan oleh test.
     *
     * `khanzabridge` SENGAJA TIDAK ADA di sini. Database itu berisi data user
     * sungguhan (akun hasil login UserGate beserta role-nya), dan `roles`
     * hanya diisi lagi saat user pertama login — jadi truncate di sana
     * menghilangkan akun yang tidak bisa dipulihkan dari dalam aplikasi.
     *
     * Kalau nama database uji Anda ternyata `khanzabridge`, pindahkan dulu ke
     * database lain; jangan menambahkan nama itu ke daftar ini.
     *
     * @return list<string>
     */
    private function safeDatabases(): array
    {
        return ['test', 'testing', ':memory:'];
    }

    private function assertSafeToTruncate(): void
    {
        $environment = (string) env('CI_ENVIRONMENT', 'production');

        if ($environment === 'production') {
            throw new RuntimeException(
                'Test akses lokal tidak boleh berjalan di environment production — '
                . 'tabel users/roles/user_roles akan dikosongkan.'
            );
        }

        $config = config('Database');
        $group  = (string) config(UserGateConfig::class)->dbGroup;
        $dbName = (string) ($config->{$group}['database'] ?? '');

        if ($dbName !== '' && ! in_array($dbName, $this->safeDatabases(), true)) {
            throw new RuntimeException(
                'Test akses lokal menolak berjalan pada database "' . $dbName . '". '
                . 'Tabel users/roles/user_roles di sana berisi data nyata dan truncate '
                . 'tidak bisa dibatalkan. Arahkan ke database uji lewat phpunit.xml '
                . '(salinan dari phpunit.xml.dist): '
                . '<env name="database.default.database" value="testing"/>. '
                . 'Environment variable dari shell tidak bisa override .env, karena env() '
                . 'membaca $_ENV/$_SERVER yang sudah diisi DotEnv dari .env. '
                . 'Jangan menambahkan nama database produksi ke safeDatabases().'
            );
        }
    }

    /**
     * Kosongkan tabel aplikasi.
     *
     * MySQL menolak TRUNCATE pada tabel yang dirujuk foreign key meski
     * tabel pengacunya kosong, jadi FK checks dimatikan sesaat.
     *
     * Urutan penting: anak dulu (`api_key_scopes`), lalu induknya.
     */
    private function truncateAccessTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->db->table('user_roles')->truncate();
            $this->db->table('users')->truncate();
            $this->db->table('roles')->truncate();

            $this->db->table('api_key_scopes')->truncate();
            $this->db->table('api_keys')->truncate();
            $this->db->table('api_applications')->truncate();
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
