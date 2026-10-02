<?php

namespace Tests\Support\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\UserGate as UserGateConfig;
use RuntimeException;

/**
 * Basis untuk test yang menyentuh tabel akses lokal (`users`, `roles`,
 * `user_roles`).
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
     * @return list<string>
     */
    private function safeDatabases(): array
    {
        return ['khanzabridge', 'test', 'testing', ':memory:'];
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
                . 'Tambahkan nama database itu ke LocalAccessDatabaseTrait::safeDatabases() '
                . 'jika ini memang database uji.'
            );
        }
    }

    /**
     * Kosongkan tabel akses.
     *
     * MySQL menolak TRUNCATE pada tabel yang dirujuk foreign key meski
     * tabel pengacunya kosong, jadi FK checks dimatikan sesaat.
     */
    private function truncateAccessTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->db->table('user_roles')->truncate();
            $this->db->table('users')->truncate();
            $this->db->table('roles')->truncate();
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
