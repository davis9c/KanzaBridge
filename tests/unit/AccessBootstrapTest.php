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

        // User kedua TIDAK bisa login: baris lokalnya baru dibuat dan
        // default-nya nonaktif.
        try {
            $this->authenticate(
                FakeUserGateClient::authFor('uuid-2', 'tanpa-role', 'tk@example.com', 'Tanpa Role')
            );
            $this->fail('Login user kedua seharusnya ditolak: akun baru default nonaktif.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('belum diaktifkan', $e->getMessage());
        }

        // Setelah administrator mengaktifkan, login berhasil — dan user
        // tersebut tetap TANPA role.
        $secondLocal = $this->users->findByUserGateId('uuid-2');
        $this->assertSame(UserModel::STATUS_INACTIVE, $secondLocal['status']);

        $this->users->setStatus((int) $secondLocal['id'], UserModel::STATUS_ACTIVE);

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

    /**
 * Tabel `roles` yang kosong tidak boleh membuat user pertama terjebak:
 * form "Ubah User" membaca daftar role dari tabel itu, jadi kalau role
 * bawaan tidak dibuat saat login, tidak ada checkbox yang bisa dicentang.
 */
public function testRoleBawaanTerjaminOlehLoginPertama(): void
    {
        // Sengaja TIDAK memanggil seedRoles() — tabel roles kosong.
        $this->assertSame(0, $this->db->table('roles')->countAllResults());

        $result = $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        $this->assertTrue($result['is_first_user']);
        $this->assertSame(['SUPER_ADMIN'], $result['roles'], 'User pertama tetap SuperAdmin.');

        // Kedua role harus ada supaya form role punya pilihan.
        $names = array_column($this->roles->listRoles(), 'name');

        $this->assertContains('SUPER_ADMIN', $names);
        $this->assertContains('ADMIN', $names);
    }

    public function testFieldRolesDariUserGateDiabaikan(): void
    {
        $this->seedRoles();

        $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        // User kedua dibuat lokal lebih dulu — default-nya nonaktif.
        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi'));
        } catch (\RuntimeException) {
            // diharapkan: belum diaktifkan
        }

        $local = $this->users->findByUserGateId('uuid-2');
        $this->users->setStatus((int) $local['id'], UserModel::STATUS_ACTIVE);

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

    /**
     * Akun yang dibuat lokal default-nya NONAKTIF, apa pun role-nya.
     *
     * Ini inti dari aturan "default nonaktif": punya role ADMIN tidak
     * otomatis berarti boleh masuk. Yang menentukan adalah status.
     */
    public function testPenggunaKeduaDibuatNonaktif(): void
    {
        $this->seedRoles();

        $this->authenticate(FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin'));

        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi'));
        } catch (\RuntimeException) {
            // diharapkan
        }

        $local = $this->users->findByUserGateId('uuid-2');

        $this->assertNotNull($local);
        $this->assertSame(
            UserModel::STATUS_INACTIVE,
            $local['status'],
            'User kedua harus dibuat nonaktif.'
        );
    }

    /**
     * Role dan status independen: user yang langsung diberi role ADMIN pun
     * tetap harus diaktifkan manual oleh administrator.
     */
    public function testRoleAdminTidakMembuatUserOtomatisAktif(): void
    {
        $this->seedRoles();

        $this->authenticate(FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin'));

        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi'));
        } catch (\RuntimeException) {
            // diharapkan
        }

        $local = $this->users->findByUserGateId('uuid-2');
        $admin = $this->roles->findByName('ADMIN');

        // Administrator memberikan role ADMIN...
        (new UserRoleModel())->attach((int) $local['id'], (int) $admin['id']);

        // ...statusnya tetap nonaktif dan login masih ditolak.
        $this->assertSame(UserModel::STATUS_INACTIVE, $local['status']);
        $this->assertSame(['ADMIN'], (new UserRoleModel())->roleNamesFor((int) $local['id']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum diaktifkan');

        $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi'));
    }

    /**
     * Hanya user pertama yang otomatis aktif. Kalau dia juga nonaktif, tidak
     * akan ada SuperAdmin yang bisa mengaktifkan siapa pun.
     */
    public function testHanyaPenggunaPertamaYangOtomatisAktif(): void
    {
        $this->seedRoles();

        $result = $this->authenticate(
            FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin')
        );

        $this->assertTrue($result['is_first_user']);
        $this->assertSame(
            UserModel::STATUS_ACTIVE,
            $result['user']['status'],
            'SuperAdmin pertama harus otomatis aktif.'
        );

        // Sebaliknya, user kedua tidak mendapat perlakuan khusus.
        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'budi', 'budi@example.com', 'Budi'));
        } catch (\RuntimeException) {
            // diharapkan
        }

        $second = $this->users->findByUserGateId('uuid-2');

        $this->assertSame(UserModel::STATUS_INACTIVE, $second['status']);
    }

    /**
     * Dua kondisi nonaktif dibedakan pesannya, karena tindakan yang perlu
     * dilakukan user juga berbeda: minta diaktifkan, atau melapor.
     *
     * Pembeda: `markLogin()` dipanggil SESUDAH cek status, jadi akun yang
     * belum pernah berhasil masuk punya `last_login_at` NULL.
     */
    public function testPesanNonaktifDibedakan(): void
    {
        $this->seedRoles();

        $this->authenticate(FakeUserGateClient::authFor('uuid-1', 'admin', 'admin@example.com', 'Admin'));

        // 1. Belum pernah aktif -> "belum diaktifkan".
        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'baru', 'baru@example.com', 'Baru'));
            $this->fail('Harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('belum diaktifkan', $e->getMessage());
            $this->assertStringNotContainsString('dinonaktifkan', $e->getMessage());
        }

        $local = $this->users->findByUserGateId('uuid-2');
        $this->users->setStatus((int) $local['id'], UserModel::STATUS_ACTIVE);

        // Login sekali supaya last_login_at terisi, lalu dicabut lagi.
        $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'baru', 'baru@example.com', 'Baru'));
        $this->users->setStatus((int) $local['id'], UserModel::STATUS_INACTIVE);

        $this->assertNotNull(
            (new UserModel())->find((int) $local['id'])['last_login_at'],
            'Prasyarat: user sudah pernah login.'
        );

        // 2. Sudah pernah aktif lalu dicabut -> "dinonaktifkan".
        try {
            $this->authenticate(FakeUserGateClient::authFor('uuid-2', 'baru', 'baru@example.com', 'Baru'));
            $this->fail('Harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('dinonaktifkan oleh administrator', $e->getMessage());
            $this->assertStringNotContainsString('belum diaktifkan', $e->getMessage());
        }
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
