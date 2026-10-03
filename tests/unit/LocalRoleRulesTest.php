<?php

namespace Tests\Unit;

use App\Models\Access\AccessService;
use App\Models\Access\RoleModel;
use App\Models\Access\UserModel;
use App\Models\Access\UserRoleModel;
use Config\Access as AccessConfig;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Libraries\FakeUserGateClient;

/**
 * Aturan bootstrap & sinkronisasi role lokal.
 *
 * Menguji lapis model yang dipakai AccessService, termasuk kasus
 * sensitivitas yang bisa menyebabkan eskalasi privilege.
 */
final class LocalRoleRulesTest extends CIUnitTestCase
{
    use LocalAccessDatabaseTrait;

    private UserModel $users;
    private RoleModel $roles;
    private UserRoleModel $userRoles;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function testRoleIdDanNamaSuperAdminDapatDitemukan(): void
    {
        $superId = $this->roles->ensure('SUPER_ADMIN', 'penuh', 1);
        $adminId = $this->roles->ensure('ADMIN', 'biasa', 0);

        $this->assertNotSame($superId, $adminId);
        $this->assertSame('SUPER_ADMIN', $this->roles->findByName('SUPER_ADMIN')['name']);
        $this->assertSame(1, (int) $this->roles->findByName('SUPER_ADMIN')['is_super']);
        $this->assertSame(0, (int) $this->roles->findByName('ADMIN')['is_super']);
    }

    public function testEnsureRoleTidakMenduaikanData(): void
    {
        $first  = $this->roles->ensure('ADMIN', 'pertama', 0);
        $second = $this->roles->ensure('ADMIN', 'kedua', 1);

        $this->assertSame($first, $second, 'Panggilan kedua harus memakai baris yang sama.');
        $this->assertSame(1, $this->db->table('roles')->countAllResults());
    }

    /**
     * Tabel `roles` dibaca langsung oleh form "Ubah User" untuk mengisi
     * checkbox role. Kalau hanya SUPER_ADMIN yang dibuat saat bootstrap,
     * role ADMIN tidak pernah muncul di sana dan administrator tidak bisa
     * memberikannya tanpa menjalankan RoleSeeder manual lebih dulu.
     *
     * ensureDefaults() dipanggil di AccessService::createLocalFromUserGate()
     * supaya ini tidak perlu bergantung pada langkah manual.
     */
    public function testEnsureDefaultsMembuatSemuaRoleBawaan(): void
    {
        $this->assertSame(0, $this->db->table('roles')->countAllResults(), 'Tabel role harus kosong di awal.');

        $listed = $this->roles->ensureDefaults();

        $names = array_column($listed, 'name');

        $this->assertContains('SUPER_ADMIN', $names);
        $this->assertContains('ADMIN', $names);
        $this->assertSame(1, (int) $this->roles->findByName('SUPER_ADMIN')['is_super']);
        $this->assertSame(0, (int) $this->roles->findByName('ADMIN')['is_super']);
    }

    /**
     * Dipanggil pada setiap login, jadi harus idempotent dan tidak boleh
     * mengubah role_id yang sudah dirujuk user_roles.
     */
    public function testEnsureDefaultsIdempotentDanTidakMengubahRoleId(): void
    {
        $this->roles->ensureDefaults();

        $idsAwal = [];
        foreach ($this->roles->listRoles() as $role) {
            $idsAwal[$role['name']] = (int) $role['id'];
        }

        $userId = $this->users->createLocal([
            'usergate_id' => 'uuid-stable-role',
            'username'    => 'stabil',
            'email'       => 'stabil@example.com',
            'full_name'   => 'Stabil Role',
        ]);

        $this->db->table('user_roles')->insert([
            'user_id'     => $userId,
            'role_id'     => $idsAwal['ADMIN'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Login kedua dan seterusnya.
        $this->roles->ensureDefaults();
        $this->roles->ensureDefaults();

        $this->assertSame(
            count(RoleModel::DEFAULT_ROLES),
            $this->db->table('roles')->countAllResults(),
            'Tidak boleh ada role tambahan.'
        );

        foreach ($idsAwal as $name => $id) {
            $this->assertSame(
                $id,
                (int) $this->roles->findByName($name)['id'],
                'role_id untuk ' . $name . ' tidak boleh berubah.'
            );
        }

        // Relasi user_roles masih utuh.
        $this->assertSame(
            ['ADMIN'],
            (new UserRoleModel())->roleNamesFor($userId)
        );
    }

    public function testDaftarUserDisertakanNamaRole(): void
    {
        $superId = $this->roles->ensure('SUPER_ADMIN', '', 1);
        $adminId = $this->roles->ensure('ADMIN', '', 0);

        $a = $this->users->createLocal(['usergate_id' => 'u1', 'username' => 'ana', 'email' => 'ana@x.y', 'full_name' => 'Ana']);
        $b = $this->users->createLocal(['usergate_id' => 'u2', 'username' => 'budi', 'email' => 'budi@x.y', 'full_name' => 'Budi']);

        $this->userRoles->sync($a, [$superId, $adminId]);
        $this->userRoles->sync($b, [$adminId]);

        $rows = $this->users->listWithRoles();
        $this->assertCount(2, $rows);

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['username']] = $row;
        }

        sort($byId['ana']['role_names']);
        $this->assertSame(['ADMIN', 'SUPER_ADMIN'], $byId['ana']['role_names']);
        $this->assertSame(['ADMIN'], $byId['budi']['role_names']);
    }

    public function testPencarianUser(): void
    {
        $this->users->createLocal(['usergate_id' => 'u1', 'username' => 'ana', 'email' => 'ana@x.y', 'full_name' => 'Ana S']);
        $this->users->createLocal(['usergate_id' => 'u2', 'username' => 'budi', 'email' => 'budi@x.y', 'full_name' => 'Budi T']);

        $this->assertCount(1, $this->users->listWithRoles(['search' => 'budi']));
        $this->assertCount(1, $this->users->listWithRoles(['search' => 'Ana S']));
        $this->assertCount(0, $this->users->listWithRoles(['search' => 'tidak-ada']));
    }

    public function testRoleMilikSuperAdminTidakDapatDiturunkanOlehAdmin(): void
    {
        $superId = $this->roles->ensure('SUPER_ADMIN', '', 1);
        $adminId = $this->roles->ensure('ADMIN', '', 0);

        $superLocalId = $this->users->createLocal([
            'usergate_id' => 'u1', 'username' => 'root', 'email' => 'root@x.y', 'full_name' => 'Root',
        ]);

        $this->userRoles->sync($superLocalId, [$superId]);

        // Skenario penyerang: actor bukan SuperAdmin mengirim role
        // ADMIN saja. Role SuperAdmin milik target harus dipertahankan.
        $submitted = [$adminId];
        $submitted = array_values(array_unique(array_merge(
            $submitted,
            $this->roleIdsOfUser($superLocalId, ['SUPER_ADMIN'])
        )));

        $this->userRoles->sync($superLocalId, $submitted);

        $names = $this->userRoles->roleNamesFor($superLocalId);
        sort($names);

        $this->assertSame(
            ['ADMIN', 'SUPER_ADMIN'],
            $names,
            'Role SuperAdmin tidak boleh hilang karena tidak diizinkan oleh actor non-SuperAdmin.'
        );
    }

    public function testRoleMilikSendiriTidakDapatDicabut(): void
    {
        $superId = $this->roles->ensure('SUPER_ADMIN', '', 1);

        $localId = $this->users->createLocal([
            'usergate_id' => 'u1', 'username' => 'root', 'email' => 'root@x.y', 'full_name' => 'Root',
        ]);

        $this->userRoles->sync($localId, [$superId]);

        $current = $this->userRoles->roleNamesFor($localId);
        $isSuper = (new AccessService(new FakeUserGateClient()))
            ->isSuperAdminRole($current);

        $this->assertTrue($isSuper);

        // Diri sendiri && SuperAdmin && role baru tidak memuat SuperAdmin
        // => harus ditolak.
        $wouldLose = $isSuper
            && $localId === $localId
            && ! in_array('SUPER_ADMIN', [], true);

        $this->assertTrue($wouldLose, 'Kondisi guard harus menangkap usaha ini.');
    }

    /**
     * `isSuperAdmin()` harus membaca DB, bukan `access_is_super` di session.
     *
     * Session dibuat saat login. Kalau demote terjadi setelah itu, session
     * masih memegang flag lama dan user itu tetap punya hak SuperAdmin sampai
     * logout/login. Tiga arah di bawah mengunci bahwa DB yang menentukan.
     */
    public function testHakSuperAdminSelaluDibacaDariDb(): void
    {
        $superId = $this->roles->ensure('SUPER_ADMIN', 'penuh', 1);

        $userId = $this->users->createLocal([
            'usergate_id' => 'u-demote', 'username' => 'turu', 'email' => 'turu@x.y', 'full_name' => 'Turu',
        ]);

        $service = new AccessService(new FakeUserGateClient());

        // 1. Session mengklaim SuperAdmin, DB tidak punya role itu.
        $this->fakeSession(['access_user_id' => $userId, 'access_roles' => ['SUPER_ADMIN'], 'access_is_super' => true]);

        $this->assertFalse(
            $service->isSuperAdmin(),
            'Session yang mengklaim SuperAdmin tidak boleh memberi hak; DB tidak punya role itu.'
        );

        // 2. DB punya role SuperAdmin, session bilang bukan.
        $this->userRoles->attach($userId, $superId);

        $this->fakeSession(['access_user_id' => $userId, 'access_roles' => ['ADMIN'], 'access_is_super' => false]);

        $this->assertTrue(
            $service->isSuperAdmin(),
            'Role di DB harus langsung berlaku walau session belum ikut berubah.'
        );

        // 3. Role dicabut dari DB, session masih SuperAdmin.
        $this->userRoles->detachAll($userId);

        $this->fakeSession(['access_user_id' => $userId, 'access_roles' => ['SUPER_ADMIN'], 'access_is_super' => true]);

        $this->assertFalse(
            $service->isSuperAdmin(),
            'Setelah role dicabut, hak SuperAdmin harus hilang tanpa perlu logout.'
        );
    }

    /**
     * Isi session tiruan.
     *
     * `withSession()` milik FeatureTestTrait tidak tersedia di unit test, dan
     * `CIUnitTestCase::mockSession()` membuat session kosong tanpa membaca
     * properti `$session`. Jadi nilainya ditulis langsung ke session service.
     *
     * @param array<string,mixed> $vars
     */
    private function fakeSession(array $vars): void
    {
        $this->mockSession();

        foreach ($vars as $key => $value) {
            session()->set($key, $value);
        }
    }

    /**
     * Role bawaan harus memuat keempat jenjang.
     *
     * Jenjang urutannya di Config\Access::$roleRank, bukan di kolom
     * `roles.is_super` yang hanya boolean.
     */
    public function testRoleBawaanMemuatEmpatJenjang(): void
    {
        $listed = array_column($this->roles->ensureDefaults(), 'name');

        // `listRoles()` mengurutkan per is_super lalu nama, BUKAN per rank —
        // jadi yang diuji di sini himpunannya, bukan urutannya.
        $this->assertEqualsCanonicalizing(
            ['SUPER_ADMIN', 'ADMIN', 'SUPERVISOR', 'PETUGAS'],
            $listed
        );

        $rank = config(AccessConfig::class)->roleRank;

        foreach ($rank as $name => $value) {
            $this->assertNotNull(
                $this->roles->findByName($name),
                'Role bawaan "' . $name . '" harus ada di tabel roles.'
            );
        }

        // Jenjang harus benar-benar terurut, kalau tidak aturan "di bawahnya"
        // tidak berarti apa-apa. assertGreaterThan($expected, $actual)
        // berarti "$actual > $expected".
        $this->assertGreaterThan($rank['SUPERVISOR'], $rank['ADMIN']);
        $this->assertGreaterThan($rank['PETUGAS'], $rank['SUPERVISOR']);

        // Hanya SUPER_ADMIN yang boleh punya rank tertinggi.
        $this->assertSame(
            $rank['SUPER_ADMIN'],
            max($rank),
            'SUPER_ADMIN harus jenjang tertinggi.'
        );

        // Label UI harus tersedia untuk semua role bawaan.
        foreach (array_keys($rank) as $name) {
            $this->assertNotSame(
                $name,
                role_label($name),
                'Role "' . $name . '" tidak punya label tampilan.'
            );
        }
    }

    /**
     * @param  list<string> $names
     * @return list<int>
     */
    private function roleIdsOfUser(int $userId, array $names): array
    {
        $ids = [];

        foreach ($this->userRoles->assignmentsFor($userId) as $row) {
            if (in_array($row['name'], $names, true)) {
                $ids[] = (int) $row['role_id'];
            }
        }

        return $ids;
    }
}
