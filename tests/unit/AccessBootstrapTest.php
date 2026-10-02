<?php

namespace Tests\Unit;

use App\Models\Access\AccessService;
use App\Models\Access\RoleModel;
use App\Models\Access\UserModel;
use App\Models\Access\UserRoleModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Libraries\FakeUserGateClient;

/**
 * Aturan otorisasi lokal:
 *
 *   - user lokal pertama yang login menjadi SuperAdmin,
 *   - user berikutnya dapat login TANPA role (berhasil, tapi tanpa menu),
 *   - field `roles` dari UserGate diabaikan.
 *
 * Menjalankan terhadap database `default` (khanzabridge). Tabel dibuat
 * oleh migrasi; test mengosongkan isinya lebih dulu supaya aturan
 * "user pertama" bisa diuji secara deterministik.
 */
final class AccessBootstrapTest extends CIUnitTestCase
{
    use LocalAccessDatabaseTrait;

    private AccessService $service;
    private UserModel $users;
    private RoleModel $roles;
    private UserRoleModel $userRoles;

    protected function setUp(): void
    {
        parent::setUp();

        // Memakai group `default` sungguhan, bukan SQLite in-memory, supaya
        // test benar-benar memverifikasi skema hasil migrasi.
        $this->setUpLocalAccessDatabase();

        $this->users     = new UserModel();
        $this->roles     = new RoleModel();
        $this->userRoles = new UserRoleModel();
    }

    protected function tearDown(): void
    {
        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /**
     * Helper: seed role default seperti RoleSeeder.
     */
    private function seedRoles(): void
    {
        $this->roles->ensure('SUPER_ADMIN', 'Akses penuh.', 1);
        $this->roles->ensure('ADMIN', 'Akses biasa.', 0);
    }

    private function authenticate(array $auth): array
    {
        $this->service = new AccessService(new FakeUserGateClient($auth));

        return $this->service->authenticate($auth['user']['username'], 'rahasia');
    }

    public function testPenggunaPertamaMenjadiSuperAdmin(): void
    {
        $this->seedRoles();

        $result = $this->authenticate(
            FakeUserGateClient::authFor(
                'uuid-pertama',
                'admin',
                'admin@example.com',
                'Administrator'
            )
        );

        $this->assertTrue($result['is_first_user']);
        $this->assertTrue($result['is_super_admin']);
        $this->assertSame(['SUPER_ADMIN'], $result['roles']);

        // Baris lokal dibuat dari identitas UserGate.
        $local = $this->users->findByUserGateId('uuid-pertama');
        $this->assertNotNull($local);
        $this->assertSame('admin', $local['username']);
        $this->assertSame('admin@example.com', $local['email']);
        $this->assertSame('Administrator', $local['full_name']);
        $this->assertSame(UserModel::STATUS_ACTIVE, $local['status']);
        $this->assertNotEmpty($local['last_login_at']);
    }

    public function testSuperAdminTetapDibuatBilaRoleBelumDiSeed(): void
    {
        // Sengaja TIDAK memanggil seedRoles(): aturan "user pertama"
        // harus tetap berlaku walau RoleSeeder belum dijalankan.
        $result = $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        $this->assertTrue($result['is_first_user']);
        $this->assertSame(['SUPER_ADMIN'], $result['roles']);
    }

    public function testPenggunaKeduaBisaLoginTanpaRole(): void
    {
        $this->seedRoles();

        // User pertama => SuperAdmin.
        $first = $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );
        $firstLocalId = (int) $first['user']['id'];

        // User kedua => berhasil login, tetapi tanpa role.
        $second = $this->authenticate(
            FakeUserGateClient::authFor('uuid-2', 'tanpa-role', 'tk@example.com', 'Tanpa Role')
        );

        $this->assertFalse($second['is_first_user']);
        $this->assertFalse($second['is_super_admin']);
        $this->assertSame([], $second['roles']);

        // ...dan user tanpa role tidak punya menu.
        $this->assertFalse(has_any_role());
        $this->assertFalse(is_super_admin());

        // Role user pertama tidak ikut hilang.
        $this->assertSame(['SUPER_ADMIN'], $this->userRoles->roleNamesFor($firstLocalId));
    }

    public function testFieldRolesDariUserGateDiabaikan(): void
    {
        $this->seedRoles();

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        // authFor() selalu menyertakan roles: ["ADMIN"] dari UserGate.
        // User kedua TIDAK boleh mendapat role dari situ.
        $second = $this->authenticate(
            FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi')
        );

        $this->assertSame([], $second['roles']);
    }

    public function testRoleDariUserGateDiabaikanSaatPenggunaPertama(): void
    {
        $this->seedRoles();

        // Bahkan user PERTAMA harus mendapat SUPER_ADMIN dari aturan
        // lokal, bukan dari roles yang dikirim UserGate.
        $result = $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        $this->assertSame(['SUPER_ADMIN'], $result['roles']);
    }

    public function testPenggunaTidakAktifDiUserGateDitolak(): void
    {
        $this->seedRoles();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak aktif');

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-x', 'x', 'x@example.com', 'X', 'INACTIVE')
        );
    }

    public function testPenggunaNonaktifSecaraLokalDitolak(): void
    {
        $this->seedRoles();

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        $second = $this->users->findByUserGateId('uuid-1');
        $this->users->setStatus((int) $second['id'], UserModel::STATUS_INACTIVE);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('dinonaktifkan');

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );
    }

    public function testIdentitasDisinkronkanDariUserGate(): void
    {
        $this->seedRoles();

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'lama', 'lama@example.com', 'Nama Lama')
        );

        // Login berikutnya dengan data yang sudah berubah di UserGate.
        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'baru', 'baru@example.com', 'Nama Baru')
        );

        $local = $this->users->findByUserGateId('uuid-1');

        $this->assertSame('baru', $local['username']);
        $this->assertSame('baru@example.com', $local['email']);
        $this->assertSame('Nama Baru', $local['full_name']);

        // Role TIDAK ikut berubah dari UserGate.
        $this->assertSame(['SUPER_ADMIN'], $this->userRoles->roleNamesFor((int) $local['id']));
    }

    public function testHanyaAdaSatuBarisLokalPerUserGate(): void
    {
        $this->seedRoles();

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        for ($i = 0; $i < 3; $i++) {
            $this->authenticate(
                FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
            );
        }

        $this->assertSame(1, $this->users->total());
    }

    public function testSyncRoleMenggantiSeluruhRole(): void
    {
        $this->seedRoles();

        $localId = $this->users->createLocal([
            'usergate_id' => 'uuid-1',
            'username'    => 'budi',
            'email'       => 'budi@example.com',
            'full_name'   => 'Budi',
        ]);

        $superId = (int) $this->roles->findByName('SUPER_ADMIN')['id'];
        $adminId = (int) $this->roles->findByName('ADMIN')['id'];

        $this->userRoles->sync($localId, [$superId]);
        $this->assertSame(['SUPER_ADMIN'], $this->userRoles->roleNamesFor($localId));

        // Turunkan ke ADMIN saja.
        $this->userRoles->sync($localId, [$adminId]);
        $this->assertSame(['ADMIN'], $this->userRoles->roleNamesFor($localId));

        // Kosongkan sepenuhnya => user tanpa role.
        $this->userRoles->sync($localId, []);
        $this->assertSame([], $this->userRoles->roleNamesFor($localId));
    }
}
