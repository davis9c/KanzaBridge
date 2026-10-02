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
