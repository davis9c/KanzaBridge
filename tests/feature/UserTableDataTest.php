<?php

namespace Tests\Feature;

use App\Libraries\UserGate\UserGateException;
use App\Models\Access\RoleModel;
use App\Models\Access\UserModel;
use App\Models\Access\UserRoleModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Database\RoleAssignmentTrait;
use Tests\Support\Views\ScrollableModalTrait;
use Tests\Support\Libraries\FakeUserGateClient;

/**
 * Endpoint user/data untuk DataTables server-side, dan jalur JSON untuk
 * aksi dari tabel (ubah, toggle status, hapus).
 *
 * Yang dijaga:
 *   - Kontrak DataTables terpenuhi: draw, recordsTotal, recordsFiltered, data.
 *   - recordsFiltered tidak pernah lebih besar dari recordsTotal.
 *   - Sorting hanya memakai kolom yang ada di whitelist; index dan arah
 *     yang tidak dikenal harus diabaikan, bukan masuk ke SQL.
 *   - Flag hak akses per baris (canDelete, canToggle, isSelf) sampai ke
 *     client, supaya tombol yang tampil sama dengan yang diizinkan server.
 *   - Respons JSON selalu membawa token CSRF terbaru. Token di-regenerate
 *     tiap POST sukses (Config\Security::$regenerate = true) dan cookienya
 *     httpOnly, jadi tanpa ini aksi kedua dari modal selalu gagal.
 */
final class UserTableDataTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
    use FeatureTestTrait;
    use LocalAccessDatabaseTrait;
    use RoleAssignmentTrait;
    use ScrollableModalTrait;

    /**
     * UserGate tiruan.
     *
     * Wajib: SysUser memanggil UserGate sungguhan saat update/toggle/delete.
     * Tanpa mock di sini, test akan menembak API produksi.
     */
    private FakeUserGateClient $gate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLocalAccessDatabase();

        $this->gate = new FakeUserGateClient();

        // SysUser::makeUserGateClient() mengambil mock ini kalau ada.
        Services::injectMock('userGateClient', $this->gate);
    }

    protected function tearDown(): void
    {
        Services::resetMock('userGateClient');

        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- *
     *  KONTRAK RESPONS
     * ---------------------------------------------------------------- */

    public function testResponsMemilikiEmpatFieldDataTables(): void
    {
        $this->loginAsSuper($this->makeUser());

        $payload = $this->tableData(['draw' => 3]);

        $this->assertArrayHasKey('draw', $payload);
        $this->assertArrayHasKey('recordsTotal', $payload);
        $this->assertArrayHasKey('recordsFiltered', $payload);
        $this->assertArrayHasKey('data', $payload);
    }

    public function testDrawDikembalikanApaAdanya(): void
    {
        $this->loginAsSuper($this->makeUser());

        $this->assertSame(9, $this->tableData(['draw' => 9])['draw']);
        $this->assertSame(0, $this->tableData(['draw' => 0])['draw']);
        $this->assertSame(0, $this->tableData(['draw' => -2])['draw']);
        $this->assertSame(0, $this->tableData(['draw' => 'abc'])['draw']);
    }

    public function testRecordsFilteredTidakPernahLebihBesarDariTotal(): void
    {
        $this->seedUser('Alpha');
        $this->seedUser('Bravo');

        $this->loginAsSuper($this->makeUser());

        foreach (['Alpha', 'Bravo', 'TidakAdaYangCocok'] as $search) {
            $payload = $this->tableData(['search[value]' => $search]);

            $this->assertLessThanOrEqual(
                (int) $payload['recordsTotal'],
                (int) $payload['recordsFiltered'],
                'recordsFiltered > recordsTotal untuk search "' . $search . '".'
            );
        }
    }

    /* ---------------------------------------------------------------- *
     *  ISI BARIS
     * ---------------------------------------------------------------- */

    public function testBarisMembawaDataDanFlagHakAkses(): void
    {
        $id = $this->seedUser('Bravo');
        $roleId = (new RoleModel())->ensure('ADMIN', 'biasa', 0);
        $this->attachRole($id, $roleId);

        $this->loginAsSuper($this->makeUser());

        $row = $this->tableData()['data'][0];

        foreach ([
            'id', 'username', 'fullName', 'email', 'roleNames', 'roleIds', 'isSuper',
            'status', 'lastLoginAt', 'isSelf', 'canToggle', 'canDelete',
            'canEditRoles', 'editUrl', 'toggleUrl', 'deleteUrl',
        ] as $field) {
            $this->assertArrayHasKey($field, $row, 'Baris kurang field "' . $field . '".');
        }

        $this->assertSame(['ADMIN'], $row['roleNames']);
        $this->assertSame([$roleId], $row['roleIds']);
        $this->assertFalse($row['isSuper']);
        $this->assertSame(base_url('user/edit/' . $id), $row['editUrl']);
        $this->assertSame(base_url('user/toggle-status/' . $id), $row['toggleUrl']);
        $this->assertSame(base_url('user/delete/' . $id), $row['deleteUrl']);
    }

    /**
     * Checkbox role di modal WAJIB dicocokkan lewat `roleIds`, bukan lewat
     * nama role.
     *
     * Nama di tabel (`SUPER_ADMIN`) dan label tampilan (`SuperAdmin`) berbeda,
     * jadi kalau client mencocokkan label, tidak ada checkbox yang pernah
     * tercentang — user terlihat tanpa role dan menyimpan form itu akan
     * menghapus role-nya. Test ini mengunci dua sisi dari kontrak itu:
     * server mengirim id, dan modal memuat id yang sama.
     */
    public function testRoleIdsSesuaiCheckboxDiModal(): void
    {
        (new RoleModel())->ensureDefaults();

        $target = $this->seedUser('Superedis');
        $superId = (new RoleModel())->findByName('SUPER_ADMIN');
        $this->assertNotNull($superId, 'Prasyarat: role SUPER_ADMIN ada.');
        $this->attachRole($target, (int) $superId['id']);

        $this->loginAsSuper($this->makeUser());

        $row = $this->tableData()['data'][0];
        $this->assertSame([(int) $superId['id']], $row['roleIds']);
        $this->assertSame(['SUPER_ADMIN'], $row['roleNames']);

        // Setiap id yang dikirim server harus punya checkbox di modal,
        // supaya client pasti bisa mencocokkannya.
        $modal = $this->modalBody($this->visit('user'), 'modalEditUser');

        foreach ($row['roleIds'] as $roleId) {
            $this->assertStringContainsString(
                'data-role-id="' . $roleId . '"',
                $modal,
                'Checkbox untuk role id ' . $roleId . ' tidak ada di modal.'
            );
        }

        // Label yang dirender modal harus lewat role_label(), dan itu SENGAJA
        // berbeda dari nama role yang dikirim server. Inilah alasan wajibnya
        // pencocokan pakai id: kalau suatu saat ada yang kembali mencocokkan
        // label, assertion NotSame ini yang langsung menangkapnya.
        $this->assertSame('SuperAdmin', role_label((string) $superId['name']));
        $this->assertNotSame(
            role_label((string) $superId['name']),
            (string) $superId['name'],
            'Kalau label == nama role, test ini tidak lagi membuktikan apa pun.'
        );
        $this->assertStringContainsString('SuperAdmin', $modal, 'Label SuperAdmin harus tampil di modal.');
    }

    /**
     * Modal ubah user memakai `modal-dialog-scrollable`.
     *
     * Kalau `<form>` dibungkus di dalam `.modal-content`, rantai flex terputus:
     * `.modal-body` tidak pernah jadi scroll container dan `.modal-content` yang
     * memotong sisanya (overflow: hidden). Akibatnya role di bawah tidak bisa
     * dijangkau user yang tidak punya monitor tinggi atau memakai zoom.
     *
     * Gejalanya murni visual, jadi test markup yang biasa ("modal ini ada")
     * lolos padahal halamannya rusak.
     *
     * @see ScrollableModalTrait
     */
    public function testModalUbahTerputusForm(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->loginAsSuper($this->makeUser());

        // Memeriksa SEMUA modal scrollable di halaman, jadi modal tambah
        // yang baru ikut dijaga di sini juga.
        $this->assertScrollableModalBodyIsDirectChild($this->visit('user'));
    }

    /**
     * User tanpa role tetap datang dengan `roleIds` kosong — bukan null — agar
     * client tidak perlu menebak bentuk datanya.
     */
    public function testRoleIdsKosongUntukUserTanpaRole(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->seedUser('TanpaRole');

        $this->loginAsSuper($this->makeUser());

        $row = $this->tableData()['data'][0];

        $this->assertSame([], $row['roleNames']);
        $this->assertSame([], $row['roleIds']);
    }

    public function testAdminTidakBolehMenghapusTapiSuperAdminBoleh(): void
    {
        $this->seedUser('Target');

        // Admin biasa: tidak punya hak hapus.
        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $row = $this->tableData()['data'][0];
        $this->assertFalse($row['canDelete'], 'Admin tidak boleh melihat tombol hapus.');
        $this->assertTrue($row['canToggle']);

        // SuperAdmin: punya hak hapus.
        $this->loginAsSuper($this->makeUser());

        $row = $this->tableData()['data'][0];
        $this->assertTrue($row['canDelete']);
    }

    public function testBarisMilikSendiriTidakBisaDiToggle(): void
    {
        $selfId = $this->seedUser('Diriku');

        $this->loginAsSuper($this->makeUser(), $selfId);

        $row = $this->tableData()['data'][0];

        $this->assertTrue($row['isSelf']);
        $this->assertFalse($row['canToggle'], 'Tidak boleh menonaktifkan akun sendiri.');
        $this->assertFalse($row['canDelete'], 'Tidak boleh menghapus akun sendiri.');
    }

    /**
     * Admin tidak boleh menyentuh role SuperAdmin milik user lain, jadi
     * checkbox-nya harus datang terkunci dari server.
     */
    public function testRoleSuperAdminMilikOrangLainDatangTerkunci(): void
    {
        $superId = $this->makeUser();
        $roleId  = (new RoleModel())->ensure('SUPER_ADMIN', 'penuh', 1);
        $target  = $this->seedUser('Superedis');
        $this->attachRole($target, $roleId);

        // Actor SuperAdmin boleh mengubahnya.
        $this->loginAsSuper($this->makeUser());
        $this->assertTrue($this->tableData()['data'][0]['canEditRoles']);

        // Actor Admin biasa tidak.
        $this->loginAs(['ADMIN'], false, $this->makeUser(), $superId);
        $row = $this->tableData()['data'][0];

        $this->assertTrue($row['isSuper'], 'Target adalah SuperAdmin.');
        $this->assertFalse($row['canEditRoles'], 'Role SuperAdmin harus terkunci untuk Admin.');
    }

    /* ---------------------------------------------------------------- *
     *  PENCARIAN, FILTER, PAGING, SORTING
     * ---------------------------------------------------------------- */

    public function testSearchDanFilterStatus(): void
    {
        $this->seedUser('Alpha');
        $this->seedUser('Bravo');
        $nonaktif = $this->seedUser('Charlie', UserModel::STATUS_INACTIVE);

        $this->loginAsSuper($this->makeUser());

        // Pencarian mencakup username, email, dan full_name. Dicari "Alph"
        // supaya full_name "Alpha" ikut teruji.
        $byName = $this->tableData(['search[value]' => 'Alph']);
        $this->assertSame(1, (int) $byName['recordsFiltered']);
        $this->assertSame(['alpha'], $this->onlySeeded($byName));

        $byStatus = $this->tableData(['status' => UserModel::STATUS_INACTIVE]);
        $this->assertSame(1, (int) $byStatus['recordsFiltered']);
        $this->assertSame($nonaktif, $byStatus['data'][0]['id']);
    }

    public function testPagingDanBatasLength(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser(sprintf('User %02d', $i));
        }

        $this->loginAsSuper($this->makeUser());

        $total = (int) $this->tableData()['recordsTotal'];

        $page1 = $this->tableData(['start' => 0, 'length' => 2, 'order[0][column]' => 0, 'order[0][dir]' => 'asc']);
        $this->assertCount(2, $page1['data']);
        $this->assertSame(5, $total - 1, 'Lima user uji + satu akun yang dipakai untuk login.');

        $last = $this->tableData(['start' => $total - 1, 'length' => 2]);
        $this->assertCount(1, $last['data'], 'Halaman terakhir boleh lebih pendek.');

        // Offset di luar jangkauan -> kosong, bukan error.
        $this->assertCount(0, $this->tableData(['start' => 999])['data']);

        // length dibatasi server.
        $this->assertLessThanOrEqual(100, count($this->tableData(['length' => 99999])['data']));
    }

    public function testSortingMenurutNamaBekerja(): void
    {
        $this->seedUser('Charlie');
        $this->seedUser('Alpha');
        $this->seedUser('Bravo');

        $this->loginAsSuper($this->makeUser());

        $asc = $this->onlySeeded($this->tableData(['order[0][column]' => 0, 'order[0][dir]' => 'asc']));
        $this->assertSame(['alpha', 'bravo', 'charlie'], $asc);

        $desc = $this->onlySeeded($this->tableData(['order[0][column]' => 0, 'order[0][dir]' => 'desc']));
        $this->assertSame(['charlie', 'bravo', 'alpha'], $desc);
    }

    public function testIndexKolomDanArahDiLuarWhitelistDiabaikan(): void
    {
        $this->seedUser('Alpha');
        $this->seedUser('Bravo');

        $this->loginAsSuper($this->makeUser());

        $expected = (int) $this->tableData()['recordsTotal'];

        // Kolom 3 (role) dan 6 (aksi) tidak ada di peta.
        foreach ([3, 6, 99, -1] as $column) {
            $this->assertCount(
                $expected,
                $this->tableData(['order[0][column]' => $column, 'order[0][dir]' => 'desc'])['data'],
                'Index kolom ' . $column . ' seharusnya tidak error.'
            );
        }

        foreach (['asc; DROP TABLE users;--', 'RANDOM()', '', 'ASC'] as $dir) {
            $payload = $this->tableData(['order[0][column]' => 0, 'order[0][dir]' => $dir]);
            $this->assertSame(
                'alpha',
                $this->onlySeeded($payload)[0] ?? null,
                'Arah "' . $dir . '" tidak seharusnya dipakai.'
            );
        }

        // Tabel utuh.
        $this->assertSame($expected, (int) $this->tableData()['recordsTotal']);
    }

    /* ---------------------------------------------------------------- *
     *  JALUR JSON
     * ---------------------------------------------------------------- */

    /* ---------------------------------------------------------------- *
     *  TAMBAH USER (modal tambah)
     * ---------------------------------------------------------------- */

    /**
     * Tambah user lewat AJAX harus membuat akun dan membalas JSON dengan
     * token baru.
     *
     * Akun baru sengaja dibuat NONAKTIF, jadi pesan sukses wajib menyebut
     * itu — kalau tidak, administrator mengira user bisa langsung masuk.
     */
    public function testTambahAjaxMembuatUserNonaktifDenganRole(): void
    {
        $roles = $this->defaultRoleIds();

        $this->loginAsSuper($this->makeUser());

        // Username ber spasi ditolak lebih dulu.
        $response = $this->postAjax('user/create', [
            'username'  => 'pengguna baru',
            'email'     => 'baru@example.com',
            'full_name' => 'Pengguna Baru',
            'password'  => 'rahasia123',
            'roles'     => [$roles['ADMIN']],
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            ['username' => 'Username harus 3-100 karakter alfanumerik.'],
            $this->json($response)['errors']
        );

        $response = $this->postAjax('user/create', [
            'username'  => 'penggunabaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Pengguna Baru',
            'password'  => 'rahasia123',
            'roles'     => [$roles['ADMIN']],
        ]);

        $response->assertOK();
        $payload = $this->json($response);

        $this->assertTrue($payload['ok']);
        $this->assertStringContainsString('NONAKTIF', (string) $payload['message']);
        $this->assertArrayHasKey('csrf', $payload);

        $created = (new UserModel())->findByUsername('penggunabaru');

        $this->assertNotNull($created);
        $this->assertSame(UserModel::STATUS_INACTIVE, (string) $created['status']);
        $this->assertSame(['ADMIN'], (new UserRoleModel())->roleNamesFor((int) $created['id']));
    }

    public function testTambahAjaxMenolakPasswordPendek(): void
    {
        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/create', [
            'username'  => 'penggunabaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Pengguna Baru',
            'password'  => 'pendek',
        ]);

        $response->assertStatus(422);

        $errors = $this->json($response)['errors'];

        $this->assertArrayHasKey('password', $errors);
        $this->assertNull((new UserModel())->findByUsername('penggunabaru'));
    }

    public function testTambahAjaxMenolakUsernameYangSudahDipakai(): void
    {
        (new UserModel())->createLocal([
            'usergate_id' => 'uuid-dulu',
            'username'    => 'dulu',
            'email'       => 'dulu@example.com',
            'full_name'   => 'Nama Dulu',
            'status'      => UserModel::STATUS_INACTIVE,
        ]);

        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/create', [
            'username'  => 'dulu',
            'email'     => 'dulu@example.com',
            'full_name' => 'Nama Dulu',
            'password'  => 'rahasia123',
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            'Username sudah dipakai user lain.',
            $this->json($response)['errors']['username']
        );
    }

    /**
     * Role yang tidak boleh diberikan harus dibuang di server, bukan hanya
     * disembunyikan di form.
     */
    public function testTambahAjaxMembuangRoleDiJenjangAtas(): void
    {
        $roles = $this->defaultRoleIds();

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $response = $this->postAjax('user/create', [
            'username'  => 'penggunabaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Pengguna Baru',
            'password'  => 'rahasia123',

            // ADMIN mencoba memberikan SUPER_ADMIN dan ADMIN (setingkat).
            'roles' => [$roles['SUPER_ADMIN'], $roles['ADMIN'], $roles['PETUGAS']],
        ]);

        $response->assertOK();

        $created = (new UserModel())->findByUsername('penggunabaru');

        $this->assertNotNull($created);
        $this->assertSame(
            ['PETUGAS'],
            (new UserRoleModel())->roleNamesFor((int) $created['id'])
        );
    }

    /**
     * Kegagalan UserGate saat tambah harus sampai ke user sebagai pesan 422,
     * dan tidak boleh meninggalkan user lokal setengah jadi.
     */
    public function testTambahAjaxMenampilkanPesanSaatUserGateMenolak(): void
    {
        $this->loginAsSuper($this->makeUser());

        $this->gate->failWith = new UserGateException(
            'Email sudah terdaftar.',
            422,
            ['email' => 'Email sudah terdaftar.']
        );

        $response = $this->postAjax('user/create', [
            'username'  => 'penggunabaru',
            'email'     => 'kembar@example.com',
            'full_name' => 'Pengguna Baru',
            'password'  => 'rahasia123',
        ]);

        $response->assertStatus(422);

        $payload = $this->json($response);

        $this->assertFalse($payload['ok']);
        $this->assertSame('Email sudah terdaftar.', $payload['message']);
        $this->assertNull((new UserModel())->findByUsername('penggunabaru'));
    }

    /**
     * Tanpa JavaScript, form di dalam modal tetap harus POST dan kembali ke
     * /user dengan modal tambah terbuka lagi.
     *
     * Tanpa penanda `errorForm`, user akan melihat halaman yang terlihat utuh
     * padahal form-nya tidak pernah muncul — isian yang sudah diketik hilang
     * tanpa penjelasan.
     */
    public function testTambahTanpaAjaxMembukaKembaliModal(): void
    {
        $this->defaultRoleIds();

        $this->loginAsSuper($this->makeUser());

        $response = $this->post('user/create', $this->csrfPayload() + [
            'username'  => 'pendek',
            'email'     => 'bukan-email',
            'full_name' => 'X',
            'password'  => 'pendek',
        ]);

        $response->assertRedirect('user');
        $response->assertSessionHas('errorForm', 'create');

        // Flashdata yang dipasang manual tidak bertahan antar-request:
        // call() pada FeatureTestTrait menulis ulang $_SESSION dari
        // $this->session sebelum setiap request. Jadi penandanya langsung
        // dipasang di sana — perilaku ini sudah dipakai MenuVisibilityTest
        // untuk flash dari AccessFilter.
        $this->session['errorForm']           = 'create';
        $this->session['__ci_vars']['errorForm'] = 'new';
        $this->session['errors']              = ['password' => 'Password minimal 8 karakter.'];
        $this->session['__ci_vars']['errors'] = 'new';
        $this->session['_ci_old_input']       = ['get' => [], 'post' => ['username' => 'pendek']];

        $body = $this->visit('user');

        $this->assertStringContainsString('data-auto-open="1"', $body);
        $this->assertStringContainsString('createModal.show();', $body);

        $modal = $this->modalBody($body, 'modalCreateUser');

        $this->assertStringContainsString('Password minimal 8 karakter.', $modal);
        $this->assertStringContainsString('value="pendek"', $modal, 'Isian lama harus dikembalikan ke form.');
    }

    /**
     * Error dari halaman ubah (fallback tanpa JavaScript) tidak boleh muncul
     * di dalam modal tambah.
     *
     * Keduanya memakai flashdata `errors` yang sama. Tanpa penanda
     * `errorForm`, errornya jadi milik form ubah — kalau tetap disuapkan ke
     * modal tambah, user akan melihat kesalahan username di form yang tidak
     * sedang ia isi.
     */
    public function testErrorHalamanUbahTidakMunculDiModalTambah(): void
    {
        $this->defaultRoleIds();

        $this->loginAsSuper($this->makeUser());

        $this->session['errors']              = ['email' => 'Format email tidak valid.'];
        $this->session['__ci_vars']['errors'] = 'new';

        $body = $this->visit('user');

        $this->assertStringNotContainsString('data-auto-open="1"', $body);
        $this->assertStringNotContainsString(
            'Format email tidak valid.',
            $this->modalBody($body, 'modalCreateUser')
        );

        // Tapi tetap terlihat di halaman, sebagai alert.
        $this->assertStringContainsString('Format email tidak valid.', $body);
    }

    public function testUbahAjaxMembalasJsonDenganTokenBaru(): void
    {
        $id = $this->seedUser('Namalama');

        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/edit/' . $id, [
            'username'  => 'UsernameBaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Nama Baru',
        ]);

        $response->assertOK();
        $payload = $this->json($response);

        $this->assertTrue($payload['ok']);
        $this->assertStringContainsString('UsernameBaru', (string) $payload['message']);
        $this->assertArrayHasKey('csrf', $payload);

        $stored = (new UserModel())->find($id);
        $this->assertSame('UsernameBaru', (string) $stored['username']);
    }

    public function testTokenAjaxBerbedaSetelahSetiapPost(): void
    {
        $id = $this->seedUser('Target');

        $this->loginAsSuper($this->makeUser());

        $first  = $this->json($this->postAjax('user/edit/' . $id, [
            'username'  => 'Satu',
            'email'     => 'satu@example.com',
            'full_name' => 'Satu',
        ]));
        $second = $this->json($this->postAjax('user/edit/' . $id, [
            'username'  => 'Dua',
            'email'     => 'dua@example.com',
            'full_name' => 'Dua',
        ]));

        $this->assertNotSame(
            $first['csrf']['value'],
            $second['csrf']['value'],
            'Token harus berubah tiap POST sukses, kalau tidak request kedua gagal.'
        );
    }

    /**
     * UserGate menolak PUT tanpa field `status` — dulu update() tidak
     * mengirimnya, sehingga setiap ubah user gagal dengan
     * "The status field is required."
     */
    public function testUbahAjaxMengirimStatusKeUserGate(): void
    {
        $id = $this->seedUser('Namalama');

        $this->loginAsSuper($this->makeUser());

        $this->postAjax('user/edit/' . $id, [
            'username'  => 'UsernameBaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Nama Baru',
        ])->assertOK();

        $this->assertCount(1, $this->gate->updateCalls, 'UserGate harus dipanggil tepat sekali.');

        $payload = $this->gate->updateCalls[0]['payload'];

        $this->assertArrayHasKey('status', $payload, 'UserGate mewajibkan field status.');
        $this->assertSame('ACTIVE', $payload['status'], 'Status tidak diubah oleh form ubah.');
        $this->assertSame('UsernameBaru', $payload['username']);
    }

    public function testToggleStatusAjax(): void
    {
        $id = $this->seedUser('Toggle');

        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/toggle-status/' . $id);
        $response->assertOK();

        $payload = $this->json($response);
        $this->assertTrue($payload['ok']);
        $this->assertArrayHasKey('csrf', $payload);

        $this->assertSame(UserModel::STATUS_INACTIVE, (string) (new UserModel())->find($id)['status']);
    }

    public function testToggleStatusAkunSendiriDitolak(): void
    {
        $selfId = $this->seedUser('Diriku');

        $this->loginAsSuper($this->makeUser(), $selfId);

        $response = $this->postAjax('user/toggle-status/' . $selfId);
        $response->assertStatus(422);

        $payload = $this->json($response);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('tidak dapat menonaktifkan akun Anda sendiri', $payload['message']);

        $this->assertSame(UserModel::STATUS_ACTIVE, (string) (new UserModel())->find($selfId)['status']);
    }

    /**
     * Opsi role di modal harus ikut disaring untuk actor non-SuperAdmin.
     *
     * `index()` pernah memakai `listRoles()` sehingga modal menunjukkan opsi
     * SuperAdmin kepada Admin, sementara `requestedRoleIds()` menyembunyikannya
     * lewat `assignableRoles()`. Server tetap menyaring ulang, jadi ini soal
     * tampilan — tapi pilihan yang terlihat harus sama dengan yang boleh dipakai.
     */
    public function testModalHanyaTampilkanRoleYangBolehDiberikan(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->seedUser('Sasaran');

        // Admin (rank 30) hanya boleh memberikan role yang STRICTLY lebih
        // rendah: SUPERVISOR (20) dan PETUGAS (10). Bukan ADMIN-nya sendiri —
        // tidak ada promote lateral — dan tentu bukan SuperAdmin.
        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $modal = $this->modalBody($this->visit('user'), 'modalEditUser');

        $this->assertSame(
            2,
            substr_count($modal, 'name="roles[]"'),
            'Admin harus melihat Supervisor dan Petugas saja.'
        );
        $this->assertStringContainsString('>Supervisor<', $modal);
        $this->assertStringContainsString('>Petugas<', $modal);
        $this->assertStringNotContainsString('>SuperAdmin<', $modal, 'Admin tidak boleh melihat opsi SuperAdmin.');
        $this->assertStringNotContainsString('>Admin<', $modal, 'Admin tidak boleh memberikan role ADMIN.');

        // SuperAdmin: melihat keempat role.
        $this->loginAsSuper($this->makeUser());

        $modal = $this->modalBody($this->visit('user'), 'modalEditUser');

        $this->assertSame(
            4,
            substr_count($modal, 'name="roles[]"'),
            'SuperAdmin harus melihat keempat role bawaan.'
        );
    }

    /**
     * Admin tidak boleh menonaktifkan akun SuperAdmin.
     *
     * `destroy()` memakai assertSuperAdmin() sementara `toggleStatus()` tidak,
     * sehingga satu Admin bisa mengunci seluruh akun SuperAdmin. Status lokal
     * harus benar-benar tetap utuh, bukan hanya ditolak di UI.
     */
    public function testAdminTidakBolehMenonaktifkanSuperAdmin(): void
    {
        $superId = (new RoleModel())->ensure('SUPER_ADMIN', 'penuh', 1);
        $target  = $this->seedUser('Superadis');
        $this->attachRole($target, $superId);

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        // Di server, baris SuperAdmin juga tidak boleh mendapat canToggle.
        $rows = $this->tableData()['data'];
        $row  = null;

        foreach ($rows as $candidate) {
            if ((int) $candidate['id'] === $target) {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row, 'Baris SuperAdmin harus ada di tabel.');
        $this->assertFalse($row['canToggle'], 'Admin tidak boleh bisa menonaktifkan SuperAdmin.');

        $response = $this->postAjax('user/toggle-status/' . $target);
        $response->assertStatus(422);

        $payload = $this->json($response);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('di bawah jenjang Anda', $payload['message']);

        $this->assertSame(
            UserModel::STATUS_ACTIVE,
            (string) (new UserModel())->find($target)['status'],
            'Status SuperAdmin tidak boleh berubah.'
        );
    }

    /**
     * Admin tetap boleh menonaktifkan user biasa — guard baru tidak boleh
     * membekukan seluruh tombol toggle untuk non-SuperAdmin.
     */
    /**
     * Admin tidak boleh menyentuh role maupun status akun Admin lain.
     *
     * Jenjang-based, bukan hanya "bukan SuperAdmin": ADMIN (30) terhadap
     * ADMIN (30) berarti `30 < 30` salah, jadi role dan status-nya terkunci.
     * Profil (username/email/nama) tetap boleh diedit — yang dikunci role.
     */
    public function testRoleSetaraTidakBisaDisentuh(): void
    {
        $adminId  = (new RoleModel())->ensure('ADMIN', 'biasa', 0);
        $peer     = $this->seedUser('AdminKembar');
        $this->attachRole($peer, $adminId);

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $row = null;

        foreach ($this->tableData()['data'] as $candidate) {
            if ((int) $candidate['id'] === $peer) {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row, 'Baris Admin lain harus ada di tabel.');
        $this->assertFalse($row['canEditRoles'], 'Role akun setingkat harus terkunci.');
        $this->assertFalse($row['canToggle'], 'Status akun setingkat harus terkunci.');
        $this->assertFalse($row['canDelete'], 'Hapus user tetap khusus SuperAdmin.');

        // Role peer tidak boleh berubah walau `roles[]` dikirim.
        $response = $this->postAjax('user/edit/' . $peer, [
            'username'  => 'PeerBerubah',
            'email'     => 'peer@example.com',
            'full_name' => 'Peer Berubah',
            'roles'     => [$adminId],
        ]);
        $response->assertOK();

        $this->assertSame(
            ['ADMIN'],
            (new UserRoleModel())->roleNamesFor($peer),
            'Role akun setingkat harus tetap utuh.'
        );
    }

    /**
     * Mengosongkan `roles[]` untuk akun setingkat juga tidak boleh melucutkan
     * role-nya — itu jalur demote tanpa jejak.
     */
    public function testAdminTidakBolehMelucutkanRoleAdminLain(): void
    {
        $adminId = (new RoleModel())->ensure('ADMIN', 'biasa', 0);
        $peer    = $this->seedUser('AdminLain');
        $this->attachRole($peer, $adminId);

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $this->postAjax('user/edit/' . $peer, [
            'username'  => 'PeerLolos',
            'email'     => 'peer2@example.com',
            'full_name' => 'Peer Lolos',
            'roles'     => [],
        ])->assertOK();

        $this->assertSame(
            ['ADMIN'],
            (new UserRoleModel())->roleNamesFor($peer),
            'Role Admin lain tidak boleh dilucuti oleh Admin.'
        );
    }

    /**
     * Hierarki harus benar-benar bisa dipakai: Admin boleh memberikan
     * Supervisor dan Petugas, dan role itu langsung berlaku.
     */
    public function testAdminBisaMemberikanRoleDiBawahnya(): void
    {
        (new RoleModel())->ensureDefaults();

        $target = $this->seedUser('Bawahan');

        $superId    = (new RoleModel())->findByName('SUPER_ADMIN');
        $supervisor = (new RoleModel())->findByName('SUPERVISOR');
        $petugas    = (new RoleModel())->findByName('PETUGAS');
        $this->assertNotNull($superId);
        $this->assertNotNull($supervisor);
        $this->assertNotNull($petugas);

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $this->postAjax('user/edit/' . $target, [
            'username'  => 'Bawahan',
            'email'     => 'bawahan@example.com',
            'full_name' => 'Bawahan',
            'roles'     => [(int) $supervisor['id'], (int) $petugas['id']],
        ])->assertOK();

        $names = (new UserRoleModel())->roleNamesFor($target);
        sort($names);

        $this->assertSame(['PETUGAS', 'SUPERVISOR'], $names);

        // Role ADMIN sendiri harus ditolak walau dikirim.
        $this->postAjax('user/edit/' . $target, [
            'username'  => 'Bawahan',
            'email'     => 'bawahan@example.com',
            'full_name' => 'Bawahan',
            'roles'     => [(int) $superId['id'], (int) $supervisor['id']],
        ])->assertOK();

        $names = (new UserRoleModel())->roleNamesFor($target);
        sort($names);

        $this->assertSame(
            ['SUPERVISOR'],
            $names,
            'SUPER_ADMIN yang dikirim Admin harus diabaikan, role lama dipertahankan.'
        );
    }

    public function testAdminTetapBisaMenonaktifkanUserBiasa(): void
    {
        $target = $this->seedUser('Biasa');

        $this->loginAs(['ADMIN'], false, $this->makeUser());

        $this->postAjax('user/toggle-status/' . $target)->assertOK();

        $this->assertSame(
            UserModel::STATUS_INACTIVE,
            (string) (new UserModel())->find($target)['status']
        );
    }

    public function testValidasiGagalMembalas422DenganDetail(): void
    {
        $id = $this->seedUser('Target');

        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/edit/' . $id, [
            'username'  => 'ab',                 // terlalu pendek
            'email'     => 'bukan-email',        // tidak valid
            'full_name' => 'X',                  // terlalu pendek
        ]);

        $response->assertStatus(422);
        $payload = $this->json($response);

        $this->assertFalse($payload['ok']);
        $this->assertArrayHasKey('username', $payload['errors']);
        $this->assertArrayHasKey('email', $payload['errors']);
        $this->assertArrayHasKey('csrf', $payload);
    }

    public function testAjaxKeUserTidakAdaMembalas422(): void
    {
        $this->loginAsSuper($this->makeUser());

        $response = $this->postAjax('user/edit/999999', [
            'username'  => 'X',
            'email'     => 'x@example.com',
            'full_name' => 'Xx',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($this->json($response)['ok']);
    }

    /**
     * Kegagalan UserGate saat ubah harus sampai ke user sebagai pesan, bukan
     * dilaporkan sebagai "Data tidak ditemukan".
     */
    public function testUserGateMenolakMenghasilkanPesanYangJelas(): void
    {
        $id = $this->seedUser('Target');

        $this->loginAsSuper($this->makeUser());
        $this->gate->failWith = new UserGateException(
            'Server UserGate sedang bermasalah.',
            503,
            [],
            UserGateException::KIND_TRANSPORT
        );

        $response = $this->postAjax('user/edit/' . $id, [
            'username'  => 'UsernameBaru',
            'email'     => 'baru@example.com',
            'full_name' => 'Nama Baru',
        ]);

        $response->assertStatus(422);
        $payload = $this->json($response);

        $this->assertFalse($payload['ok']);
        $this->assertNotEmpty((string) $payload['message']);

        // Data lokal tidak boleh ikut berubah kalau UserGate menolak.
        $this->assertSame('Target', (string) (new UserModel())->find($id)['full_name']);
    }

    /* ---------------------------------------------------------------- *
     *  TAMPILAN
     * ---------------------------------------------------------------- */

    public function testHanyaAdaTabelDanKetigaModal(): void
    {
        $this->loginAsSuper($this->makeUser());

        $body = $this->visit('user');

        $this->assertStringContainsString('id="tbl-user"', $body);
        $this->assertStringContainsString('id="modalCreateUser"', $body);
        $this->assertStringContainsString('id="modalEditUser"', $body);
        $this->assertStringContainsString('id="modalConfirmAction"', $body);

        // tbody dikosongkan untuk server-side.
        $clean = preg_replace('/<!--.*?-->/s', '', $body);
        $this->assertMatchesRegularExpression('/<tbody>\s*<\/tbody>/', $clean);
        $this->assertStringNotContainsString('colspan', $clean);

        // Kartu filter GET lama sudah tidak ada; search bawaan DataTables
        // yang dipakai.
        $this->assertStringNotContainsString('method="GET"', $clean);

        // Halaman ubah tetap ada sebagai fallback.
        $this->get('user/edit/' . $this->seedUser('Fallback'))->assertOK();
    }

    /**
     * Tombol "Tambah User" harus membuka modal, bukan memanggil halaman.
     *
     * Semula tombol itu `<a href="user/create">`, jadi yang terjadi adalah
     * navigasi ke halaman penuh — modal tambah tidak pernah ada. Karena itu
     * assertion-nya bukan "halaman create masih bisa dibuka", tapi "tidak ada
     * tautan ke halaman yang sudah dihapus" dan "form-nya ada di DOM".
     */
    public function testTombolTambahUserMembukaModal(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->loginAsSuper($this->makeUser());

        $body = $this->visit('user');

        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="btnCreateUser"/',
            $body,
            'Tombol Tambah User harus <button>, bukan tautan.'
        );
        $this->assertStringNotContainsString(
            'href="' . base_url('user/create') . '"',
            $body,
            'Tidak ada lagi tautan ke halaman /user/create.'
        );
        $this->assertStringContainsString('createModal.show();', $body, 'Tombol harus membuka modal tambah.');

        // Formulanya benar-benar ada, lengkap dengan field password yang
        // hanya ada di modal tambah.
        $modal = $this->modalBody($body, 'modalCreateUser');

        $this->assertStringContainsString('id="formCreateUser"', $modal);
        $this->assertStringContainsString('action="' . base_url('user/create') . '"', $modal);
        $this->assertStringContainsString('name="password"', $modal);
        $this->assertStringContainsString('name="full_name"', $modal);
        $this->assertStringNotContainsString('name="password"', $this->modalBody($body, 'modalEditUser'));

        // Route GET-nya sudah dihapus: tidak ada lagi halaman tambah
        // terpisah yang bisa dibuka lewat URL.
        $this->expectException(PageNotFoundException::class);

        $this->get('user/create');
    }

    /**
     * Role di modal tambah harus sama persis dengan modal ubah.
     *
     * Kalau daftar role di satu modal saja, user bisa membuat akun tanpa role
     * yang bisa ia ubah nanti — atau sebaliknya, modal menampilkan role yang
     * server sebenarnya tolak.
     */
    public function testModalTambahPunyaRoleYangSamaDenganModalUbah(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->loginAsSuper($this->makeUser());

        $body  = $this->visit('user');
        $create = $this->modalBody($body, 'modalCreateUser');
        $edit   = $this->modalBody($body, 'modalEditUser');

        $this->assertSame(
            substr_count($edit, 'name="roles[]"'),
            substr_count($create, 'name="roles[]"'),
            'Jumlah checkbox role harus sama di kedua modal.'
        );
    }

    /**
     * Tabel `roles` kosong harus dijelaskan, bukan dibiarkan diam.
     *
     * Tanpa ini, modal cuma menampilkan satu checkbox "Tanpa role" yang
     * terkunci — terlihat seperti pilihan yang bekerja, padahal tidak ada
     * role sama sekali yang bisa dipilih.
     *
     * View-nya dirender langsung dengan `$roles` kosong, bukan lewat HTTP.
     * Dawulu test ini login sebagai SuperAdmin sambil mengosongkan tabel
     * `roles` — dan itu tidak mungkin lagi: sejak `isSuperAdmin()` membaca DB,
     * seorang SuperAdmin wajib punya baris SUPER_ADMIN di tabel itu. Menguji
     * cabang view secara langsung justru lebih tepat dan tidak memaksa test
     * mengarang keadaan yang mustahil.
     */
    public function testRolesKosongMenampilkanPetunjukSeeder(): void
    {
        $body = (string) view('sys-user/index', [
            'title' => 'Manajemen User',
            'roles' => [],
        ]);

        $this->assertSame([], (new RoleModel())->listRoles(), 'Prasyarat: tabel roles kosong.');

        // Di luar modal, jadi terlihat sebelum tombol Ubah ditekan.
        $this->assertStringContainsString('php spark db:seed RoleSeeder', $body);

        // Dan tetap ada di dalam modal ubah.
        $modal = $this->modalBody($body, 'modalEditUser');
        $this->assertStringContainsString('php spark db:seed RoleSeeder', $modal);

        // Opsi "Tanpa role" disembunyikan supaya tidak terbaca sebagai pilihan.
        $this->assertStringNotContainsString('Tanpa role', $modal);
        $this->assertStringNotContainsString('name="roles[]"', $modal);
    }

    public function testRolesAdaTetapMenampilkanCheckbox(): void
    {
        (new RoleModel())->ensureDefaults();

        $this->loginAsSuper($this->makeUser());

        $modal = $this->modalBody($this->visit('user'), 'modalEditUser');

        $this->assertStringContainsString('Tanpa role', $modal);
        $this->assertSame(
            4,
            substr_count($modal, 'name="roles[]"'),
            'Keempat role bawaan harus punya checkbox masing-masing.'
        );

        // Petunjuk seeder tidak boleh muncul sekarang roles sudah ada.
        $this->assertStringNotContainsString('php spark db:seed RoleSeeder', $modal);
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * Ambil isi satu modal saja dari HTML halaman.
     *
     * Diperlukan karena peringatan "roles kosong" sengaja dirender dua kali:
     * di badan halaman dan di dalam modal. Assertion harus bisa memilih
     * salah satu.
     *
     * Batas potongnya adalah modal BERIKUTNYA, bukan hanya modal konfirmasi,
     * supaya pemanggil bisa mengambil masing-masing dari tiga modal di
     * halaman ini tanpa ikut menelan isi modal setelahnya.
     */
    private function modalBody(string $html, string $id): string
    {
        $start = strpos($html, 'id="' . $id . '"');

        $this->assertNotFalse($start, 'Modal "' . $id . '" tidak ada di halaman.');

        $next = strpos($html, 'class="modal fade"', $start + 1);
        $end  = $next === false ? strlen($html) : $next;

        return substr($html, $start, $end - $start);
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    private function makeUser(): int
    {
        static $n = 0;
        $n++;

        return (new UserModel())->createLocal([
            'usergate_id' => 'uuid-user-' . $n,
            'username'    => 'user' . $n,
            'email'       => 'user' . $n . '@example.com',
            'full_name'   => 'User ' . $n,

            // Default createLocal() adalah NONAKTIF; user ini yang dipakai
            // login, jadi harus aktif.
            'status'      => UserModel::STATUS_ACTIVE,
        ]);
    }

    /**
 * Buat satu user lokal dengan nama tampilan tertentu.
 *
 * Username, email, dan UUID diberi uniqid supaya dua user dengan nama
 * sama tidak saling menimpa saat dipakai pada test yang membuat beberapa
 * user sekaligus.
 */
private function seedUser(string $name, string $status = UserModel::STATUS_ACTIVE): int
    {
        return (new UserModel())->createLocal([
            'usergate_id' => 'uuid-' . uniqid(),
            'username'    => str_replace(' ', '', strtolower($name)) . uniqid(),
            'email'       => str_replace(' ', '', strtolower($name)) . uniqid() . '@example.com',
            'full_name'   => $name,
            'status'      => $status,
        ]);
    }

    private function attachRole(int $userId, int $roleId): void
    {
        (new UserRoleModel())->attach($userId, $roleId);
    }

    /**
     * Ambil username yang dibuat lewat seedUser() saja.
     *
     * Akun yang dipakai untuk login juga muncul di tabel (SuperAdmin melihat
     * semua user, termasuk dirinya sendiri), jadi assertion harus menyaring
     * username yang ditambahkan seedUser().
     *
     * @param  array<string,mixed> $payload
     * @return list<string>
     */
    private function onlySeeded(array $payload): array
    {
        $names = array_column((array) ($payload['data'] ?? []), 'username');

        // uniqid() tepat 13 karakter heksadesimal, jadi suffix-nya dipotong
        // dengan panjang tetap — bukan regex, karena huruf a-f juga muncul di
        // nama_test sendiri ("charlie").
        $suffixLength = 13;

        $seeded = array_values(array_filter(
            $names,
            static fn (string $name): bool => (bool) preg_match('/^(alpha|bravo|charlie)[0-9a-f]{13}$/', $name)
        ));

        return array_map(
            static fn (string $name): string => substr($name, 0, -$suffixLength),
            $seeded
        );
    }

    /**
     * @param array<string,string|int> $query
     *
     * @return array<string,mixed>
     */
    private function tableData(array $query = []): array
    {
        $response = $this->get('user/data' . ($query === [] ? '' : '?' . http_build_query($query)));
        $response->assertOK();

        return $this->json($response);
    }

    private function loginAsSuper(int $userId, int $selfId = 0): void
    {
        $this->loginAs(['SUPER_ADMIN'], true, $userId, $selfId);
    }

    /**
     * @param list<string> $roles
     *
     * Role dipasang sungguhan di DB, bukan cuma diklaim lewat session.
     * `AccessService::isSuperAdmin()` membaca DB, jadi test SuperAdmin yang
     * hanya menalsukan `access_is_super` tidak akan menguji jalur produksi.
     */
    private function loginAs(array $roles, bool $isSuper, int $userId, int $selfId = 0): void
    {
        $actorId = $selfId > 0 ? $selfId : $userId;

        $this->assignRoles($actorId, $roles);

        $this->withSession([
            'logged_in'          => true,
            'access_user_id'     => $actorId,
            'access_usergate_id' => 'uuid-' . $userId,
            'access_username'    => 'u' . $userId,
            'access_email'       => 'u' . $userId . '@example.com',
            'access_full_name'   => 'User ' . $userId,
            'access_roles'       => $roles,
            'access_is_super'    => $isSuper,

            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function postAjax(string $uri, array $params = []): TestResponse
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);

        $response = $this->post($uri, $params + $this->csrfPayload());

        $this->withHeaders(['X-Requested-With' => null]);

        return $response;
    }

    /**
     * Isi POST dengan token CSRF yang sah.
     *
     * `csrf` adalah filter global yang jalan sebelum filter route, jadi tanpa
     * token yang benar request ditolak 403 dan tidak pernah sampai ke
     * controller. Token dibaca dari cookie (Config\Security::$csrfProtection =
     * 'cookie'), jadi cookie-nya ikut dipasang.
     *
     * @return array<string,string>
     */
    private function csrfPayload(): array
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        $_COOKIE[$security->getCookieName()] = $token;

        return [$security->getTokenName() => $token];
    }

    /**
     * Id role bawaan, dipetakan dari namanya.
     *
     * ensureDefaults() mengembalikan daftar baris, jadi pemetaan nama ke id
     * dibuat di sini — supaya test bisa menyebut role-nya lewat nama.
     *
     * @return array<string,int>
     */
    private function defaultRoleIds(): array
    {
        $ids = [];

        foreach ((new RoleModel())->ensureDefaults() as $role) {
            $ids[(string) $role['name']] = (int) $role['id'];
        }

        return $ids;
    }

    /**
     * @return array<string,mixed>
     */
    private function json(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->response()->getBody(), true);

        $this->assertIsArray($decoded, 'Respons harus berupa JSON yang valid.');

        return $decoded;
    }

    private function visit(string $route): string
    {
        $this->last = $this->get($route);

        return (string) $this->last->getBody();
    }
}