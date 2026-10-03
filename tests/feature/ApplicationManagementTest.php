<?php

namespace Tests\Feature;

use App\Models\Access\ApiApplicationModel;
use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Database\RoleAssignmentTrait;

/**
 * Halaman "Application": manage API key beserta aturan kepemilikan.
 *
 * Aturan yang dijaga:
 *   SuperAdmin -> melihat & mengelola semua application
 *   Admin      -> hanya application yang ia buat sendiri
 *
 * Penegakan diuji dua kali: daftar yang dirender, dan penolakan saat URL
 * aplikasi orang lain diakses langsung. Yang kedua adalah yang benar-benar
 * menentukan.
 *
 * POST memakai token CSRF sungguhan — helper postForm() menyediakannya —
 * supaya alur form yang diuji sama dengan yang dipakai browser.
 *
 * Token UserGate tidak dimasukkan, jadi AuthFilter tidak memaksa logout dan
 * test murni menguji aturan application.
 */
final class ApplicationManagementTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
    use FeatureTestTrait;
    use LocalAccessDatabaseTrait;
    use RoleAssignmentTrait;

    private ?TestResponse $last = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLocalAccessDatabase();
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[config('Cookie')->prefix . config('Security')->cookieName]);

        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- *
     *  AKSES HALAMAN
     * ---------------------------------------------------------------- */

    public function testAdminDanSuperAdminSamaSajaBolehMembukaHalaman(): void
    {
        foreach ([['ADMIN', false], ['SUPER_ADMIN', true]] as [$role, $isSuper]) {
            $this->loginAs([$role], $isSuper);

            $this->last = $this->get('application');

            $this->last->assertOK();
            $this->assertStringContainsString('Manajemen Application', (string) $this->last->getBody());
        }
    }

    public function testMenuApplicationMunculDiTopbar(): void
    {
        $this->loginAs(['ADMIN']);

        $this->get('application')->assertOK();

        $topbar = (string) view('partial/topbar', ['title' => 'Menu']);

        $this->assertStringContainsString('Application', $topbar);
        $this->assertStringContainsString(base_url('application'), $topbar);
    }

    /* ---------------------------------------------------------------- *
     *  ATURAN KEPEMILIKAN
     * ---------------------------------------------------------------- */

    public function testSuperAdminMelihatSemuaApplication(): void
    {
        $adminId    = $this->makeOwner();
        $superId    = $this->makeOwner();
        $milikAdmin = $this->makeApplication($adminId)['application'];
        $milikSuper = $this->makeApplication($superId)['application'];

        $this->loginAs(['SUPER_ADMIN'], true, $superId);

        $payload = $this->tableData();

        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertContains((string) $milikAdmin['name'], $this->names($payload));
        $this->assertContains((string) $milikSuper['name'], $this->names($payload));
    }

    public function testAdminHanyaMelihatApplicationMiliknyaSendiri(): void
    {
        $adminId    = $this->makeOwner();
        $orangId    = $this->makeOwner();
        $milikAdmin = $this->makeApplication($adminId)['application'];
        $milikOrang = $this->makeApplication($orangId)['application'];

        $this->loginAs(['ADMIN'], false, $adminId);

        $payload = $this->tableData();
        $names   = $this->names($payload);

        $this->assertSame(1, $payload['recordsTotal'], 'recordsTotal hanya boleh menghitung milik Admin sendiri.');
        $this->assertContains((string) $milikAdmin['name'], $names);
        $this->assertNotContains(
            (string) $milikOrang['name'],
            $names,
            'Application milik user lain tidak boleh muncul di daftar.'
        );
    }

    public function testAdminTidakBisaMengelolaApplicationMilikOrangLain(): void
    {
        $ownerId  = $this->makeOwner();
        $stranger = $this->makeOwner();
        $target   = $this->makeApplication($ownerId)['application'];
        $id       = (int) $target['id'];

        $this->loginAs(['ADMIN'], false, $stranger);

        $this->get('application/' . $id . '/keys')->assertRedirect('application');
        $this->get('application/edit/' . $id)->assertRedirect('application');

        $this->postForm('application/' . $id . '/keys', [
            'label'  => 'Sisip',
            'scopes' => ['meta.read'],
        ])->assertRedirect('application');

        $this->postForm('application/delete/' . $id)->assertRedirect('application');

        // Tidak boleh ada perubahan apa pun.
        $this->assertSame(0, (new ApiKeyModel())->countAllResults());
        $this->assertNotNull((new ApiApplicationModel())->find($id), 'Application tidak boleh terhapus.');
    }

    public function testSuperAdminBisaMengelolaApplicationMilikAdmin(): void
    {
        $ownerId = $this->makeOwner();
        $superId = $this->makeOwner();
        $target  = $this->makeApplication($ownerId)['application'];
        $id      = (int) $target['id'];

        $this->loginAs(['SUPER_ADMIN'], true, $superId);

        $this->get('application/' . $id . '/keys')->assertOK();
        $this->get('application/edit/' . $id)->assertOK();
    }

    public function testApplicationTanpaOwnerHanyaUntukSuperAdmin(): void
    {
        $applications = new ApiApplicationModel();
        $adminId      = $this->makeOwner();

        $id = $applications->createApplication([
            'name'       => 'Warisan ' . uniqid(),
            'code'       => 'warisan-' . uniqid(),
            'created_by' => null,
        ]);

        $this->loginAs(['ADMIN'], false, $adminId);
        $this->get('application/' . $id . '/keys')->assertRedirect('application');

        $this->loginAs(['SUPER_ADMIN'], true, $adminId);
        $this->get('application/' . $id . '/keys')->assertOK();
    }

    /* ---------------------------------------------------------------- *
     *  BUAT KEY
     * ---------------------------------------------------------------- */

    public function testAdminMembuatKeyMiliknyaSendiri(): void
    {
        $app = $this->loginAsOwnerOfNewApplication('ADMIN');
        $id  = (int) $app['id'];

        $this->last = $this->postForm('application/' . $id . '/keys', [
            'label'                 => 'Produksi',
            'expires_at'            => '',
            'rate_limit_per_minute' => '60',
            'scopes'                => ['meta.read', 'jabatan.read'],
        ]);

        $this->last->assertRedirect('application/' . $id . '/keys');

        $keys = (new ApiKeyModel())->listForApplication($id);

        $this->assertCount(1, $keys);
        $this->assertSame('Produksi', $keys[0]['label']);
        $this->assertSame(['jabatan.read', 'meta.read'], $keys[0]['scopes']);
        $this->assertSame(60, (int) $keys[0]['rate_limit_per_minute']);
        $this->assertNull($keys[0]['expires_at']);
    }

    public function testKeyHanyaDisimpanSebagaiHash(): void
    {
        $app = $this->loginAsOwnerOfNewApplication('ADMIN');
        $id  = (int) $app['id'];

        $this->postForm('application/' . $id . '/keys', [
            'label'  => 'Produksi',
            'scopes' => ['meta.read'],
        ])->assertRedirect('application/' . $id . '/keys');

        $row = (new ApiKeyModel())->builder()
            ->where('application_id', $id)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row, 'Key harus tersimpan.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $row['key_hash']);
        $this->assertStringStartsWith(config('Api')->v2KeyPrefix, (string) $row['key_prefix']);
        $this->assertSame(config('Api')->v2KeyPrefixLength, strlen((string) $row['key_prefix']));
    }

    public function testKeyBaruDitolakTanpaScope(): void
    {
        $app = $this->loginAsOwnerOfNewApplication('ADMIN');
        $id  = (int) $app['id'];

        $this->last = $this->postForm('application/' . $id . '/keys', [
            'label'  => 'Tanpa Scope',
            'scopes' => ['scope-palsu', '  ', 'apikey.lolos'],
        ]);

        $this->last->assertRedirect('application/' . $id . '/keys');
        $this->last->assertSessionHas('errors');
        $this->assertSame(0, (new ApiKeyModel())->countAllResults());
    }

    public function testScopeTakDikenalDibuang(): void
    {
        $app = $this->loginAsOwnerOfNewApplication('ADMIN');
        $id  = (int) $app['id'];

        $this->postForm('application/' . $id . '/keys', [
            'label'  => 'Bersih',
            'scopes' => ['meta.read', 'hantu.scope', '  ', 'jabatan.read'],
        ])->assertRedirect('application/' . $id . '/keys');

        $keys   = (new ApiKeyModel())->listForApplication($id);
        $scopes = (new ApiKeyScopeModel())->forKey((int) $keys[0]['id']);

        sort($scopes);

        $this->assertSame(['jabatan.read', 'meta.read'], $scopes);
    }

    /* ---------------------------------------------------------------- *
     *  CABUT & ROTASI
     * ---------------------------------------------------------------- */

    public function testMencabutKeyMelepasSemuaEndpoint(): void
    {
        $made  = $this->makeApiKey(['meta.read', 'jabatan.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $key   = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->postForm('application/' . $app . '/keys/' . $key . '/toggle-status')
            ->assertRedirect('application/' . $app . '/keys');

        $this->assertSame(ApiKeyModel::STATUS_REVOKED, (string) (new ApiKeyModel())->find($key)['status']);
        $this->assertSame(
            [],
            (new ApiKeyScopeModel())->forKey($key),
            'Key yang dicabut tidak boleh punya endpoint.'
        );
    }

    public function testRotasiMenggantiKeyDanMempertahankanScope(): void
    {
        $made  = $this->makeApiKey(['meta.read', 'jabatan.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $old   = (int) $made['key']['id'];

        $keys  = new ApiKeyModel();
        $scope = new ApiKeyScopeModel();

        $this->loginAs(['ADMIN'], false, $owner);

        $this->last = $this->postForm('application/' . $app . '/keys/' . $old . '/rotate');

        $this->last->assertRedirect('application/' . $app . '/keys');

        // Key lama langsung mati dan tidak boleh dipakai lagi.
        $this->assertSame(ApiKeyModel::STATUS_REVOKED, (string) $keys->find($old)['status']);
        $this->assertSame([], $scope->forKey($old));

        $new = null;

        foreach ($keys->listForApplication($app) as $row) {
            if ((int) $row['id'] !== $old) {
                $new = $row;
            }
        }

        $this->assertNotNull($new, 'Rotasi harus membuat key baru.');
        $this->assertSame('Key Uji', $new['label']);
        $this->assertSame(['jabatan.read', 'meta.read'], $new['scopes']);
        $this->assertNotSame(hash('sha256', $made['plain']), $new['key_hash']);
    }

    public function testRotasiMenghasilkanKeyYangBisaDipakai(): void
    {
        $made  = $this->makeApiKey(['jabatan.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $old   = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->last = $this->postForm('application/' . $app . '/keys/' . $old . '/rotate');

        $this->last->assertSessionHas('new_api_key');

        $this->withHeaders(['X-API-Key' => (string) session('new_api_key')]);

        $this->get('api/v2/jabatan')->assertOK();
    }

    public function testKeyLamaTidakLagiDipakaiSetelahRotasi(): void
    {
        $made  = $this->makeApiKey(['jabatan.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $old   = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->postForm('application/' . $app . '/keys/' . $old . '/rotate');

        $this->withHeaders(['X-API-Key' => $made['plain']]);

        $this->get('api/v2/jabatan')->assertStatus(401);
    }

    /* ---------------------------------------------------------------- *
     *  HAK AKSES ENDPOINT
     * ---------------------------------------------------------------- */

    public function testChecklistEndpointAdaDiDalamModal(): void
    {
        $made = $this->makeApiKey(['meta.read']);
        $app  = (int) $made['application']['id'];
        $key  = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, (int) $made['application']['created_by']);

        $body = $this->visit('application/' . $app . '/keys');

        // Satu modal hak akses dipakai bersama. Tombol per baris mengisi
        // modal itu lewat data-*, bukan dengan menyasar modalnya sendiri.
        $this->assertSame(1, substr_count($body, 'id="modalKeyScopes"'));
        $this->assertStringContainsString('id="formKeyScopes"', $body);
        $this->assertStringContainsString('data-scope-form', $body);
        $this->assertStringContainsString('data-scope-checkbox', $body);

        $this->assertStringContainsString('class="btn btn-sm btn-outline-primary js-scopes"', $body);
        $this->assertSame(
            1,
            substr_count($body, 'application/' . $app . '/keys/' . $key . '/scopes'),
            'URL scopes harus ada di tombol baris tersebut.'
        );

        // Scope key dikirim ke tombol supaya modal bisa diisi tanpa fetch.
        // json_encode(["meta.read"]) di-escape menjadi [&quot;meta.read&quot;],
        // lalu dibungkus kutip tunggal oleh atribut HTML.
        $this->assertStringContainsString('data-key-id="' . $key . '"', $body);
        $this->assertStringContainsString(
            'data-scopes=\'["meta.read"]\'',
            $body,
            'Daftar scope key harus ikut di tombol agar modal bisa diisi tanpa fetch.'
        );

        // Setiap endpoint katalog punya butirnya di modal.
        foreach (config('ApiScope')->endpoints as $meta) {
            $this->assertStringContainsString($meta['path'], $body, 'Path endpoint tidak tampil di modal.');
        }
    }

    /**
     * Dulu halaman ini merender satu modal hak akses per key. Sekarang
     * hanya satu, dipakai bersama — jadi ukurannya tidak ikut bertambah
     * setiap ada key baru.
     */
    public function testHanyaAdaSatuModalPermissions(): void
    {
        $made  = $this->makeApiKey(['meta.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        // Key kedua dibuat lewat UI supaya milik application yang sama.
        $this->postForm('application/' . $app . '/keys', [
            'label'  => 'Key Kedua',
            'scopes' => ['meta.read'],
        ])->assertRedirect('application/' . $app . '/keys');

        $body = $this->visit('application/' . $app . '/keys');

        $this->assertSame(
            1,
            substr_count($body, 'id="modalKeyScopes"'),
            'Modal hak akses harus tetap satu walau ada banyak key.'
        );

        // Dua key, dua tombol — tapi tetap satu modal. Kemunculan kata
        // "js-scopes" keempat ada di JavaScript (selector + definisi
        // openScopes), jadi yang dihitung hanya atributnya.
        $this->assertSame(2, substr_count($body, 'class="btn btn-sm btn-outline-primary js-scopes"'));
        $this->assertSame(0, substr_count($body, 'id="scopeModal-'), 'Modal per key harus dihapus.');
    }

    public function testChecklistEndpointMunculDiHalamanKey(): void
    {
        $made = $this->makeApiKey(['meta.read']);
        $app  = (int) $made['application']['id'];

        $this->loginAs(['ADMIN'], false, (int) $made['application']['created_by']);

        $body = $this->visit('application/' . $app . '/keys');

        // Setiap endpoint katalog harus punya butir checklist-nya.
        foreach (config('ApiScope')->endpoints as $meta) {
            $this->assertStringContainsString($meta['path'], $body, 'Path endpoint tidak tampil di checklist.');
        }
    }

    public function testMenyimpanScopeMenggantiSeluruhScopeLama(): void
    {
        $made  = $this->makeApiKey(['meta.read', 'jabatan.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $key   = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->postForm('application/' . $app . '/keys/' . $key . '/scopes', [
            'scopes' => ['petugas.read'],
        ])->assertRedirect('application/' . $app . '/keys');

        $this->assertSame(['petugas.read'], (new ApiKeyScopeModel())->forKey($key));
    }

    public function testScopeKeyMilikOrangLainTidakBisaDisentuh(): void
    {
        $made     = $this->makeApiKey(['meta.read']);
        $keyId    = (int) $made['key']['id'];
        $stranger = $this->makeOwner();

        $this->loginAs(['ADMIN'], false, $stranger);

        $this->postForm('application/' . $made['application']['id'] . '/keys/' . $keyId . '/scopes', [
            'scopes' => ['users.read'],
        ])->assertRedirect('application');

        $this->assertSame(['meta.read'], (new ApiKeyScopeModel())->forKey($keyId));
    }

    public function testScopeTidakBisaDikosongkan(): void
    {
        $made  = $this->makeApiKey(['meta.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];
        $key   = (int) $made['key']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->last = $this->postForm('application/' . $app . '/keys/' . $key . '/scopes', ['scopes' => []]);

        $this->last->assertRedirect('application/' . $app . '/keys');
        $this->last->assertSessionHas('error');

        $this->assertSame(['meta.read'], (new ApiKeyScopeModel())->forKey($key));
    }

    /* ---------------------------------------------------------------- *
     *  APLIKASI
     * ---------------------------------------------------------------- */

    public function testApplicationBaruDicatatMilikPembuat(): void
    {
        $adminId = $this->makeOwner();

        $this->loginAs(['ADMIN'], false, $adminId);

        $this->last = $this->postForm('application/create', [
            'name'        => 'Modul Registrasi BPM',
            'code'        => '',
            'description' => 'Integrasi antar kios',
        ]);

        $this->last->assertRedirect();

        $applications = new ApiApplicationModel();
        $created     = $applications->findByName('Modul Registrasi BPM');

        $this->assertNotNull($created);
        $this->assertGreaterThan(0, (int) $created['id']);
        $this->assertSame('modul-registrasi-bpm', (string) $created['code']);
        $this->assertSame($adminId, (int) $created['created_by']);
    }

    public function testKodeApplicationHarusUnik(): void
    {
        $applications = new ApiApplicationModel();

        $applications->createApplication([
            'name'       => 'Sudah Ada',
            'code'       => 'kode-bentrok',
            'created_by' => $this->makeOwner(),
        ]);

        $this->loginAs(['ADMIN']);

        $this->last = $this->postForm('application/create', [
            'name' => 'Duplikat ' . uniqid(),
            'code' => 'kode-bentrok',
        ]);

        $this->last->assertSessionHas('errors');
    }

    public function testHapusApplicationIkutMenghapusKey(): void
    {
        $made  = $this->makeApiKey(['meta.read']);
        $owner = (int) $made['application']['created_by'];
        $app   = (int) $made['application']['id'];

        $this->loginAs(['ADMIN'], false, $owner);

        $this->postForm('application/delete/' . $app)->assertRedirect('application');

        $this->assertNull((new ApiApplicationModel())->find($app));
        $this->assertSame(0, (new ApiKeyModel())->countAllResults());
        $this->assertSame(0, (new ApiKeyScopeModel())->countAllResults());
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * Buat application milik user baru, lalu login sebagai pemiliknya.
     *
     * @param list<string> $roles
     * @return array<string,mixed> Baris application yang dibuat.
     */
    private function loginAsOwnerOfNewApplication(string $role, bool $isSuper = false): array
    {
        $userId = $this->makeOwner();
        $app    = $this->makeApplication($userId)['application'];

        $this->loginAs([$role], $isSuper, $userId);

        return $app;
    }

    /**
     * @param list<string> $roles
     *
     * Role dipasang sungguhan di DB — `isSuperAdmin()` membaca DB, bukan
     * `access_is_super` di session.
     */
    private function loginAs(array $roles, bool $isSuper = false, int $userId = 0): void
    {
        $userId = $userId > 0 ? $userId : $this->makeOwner();

        $this->assignRoles($userId, $roles);

        $this->withSession([
            'logged_in'          => true,
            'access_user_id'     => $userId,
            'access_usergate_id' => 'uuid-application-test-' . $userId,
            'access_username'    => 'pengguna-' . $userId,
            'access_email'       => 'pengguna-' . $userId . '@example.com',
            'access_full_name'   => 'Pengguna Uji ' . $userId,
            'access_roles'       => $roles,
            'access_is_super'    => $isSuper,

            // Token tiruan: dibaca AuthFilter, tidak pernah divalidasi
            // ke UserGate selama belum kedaluwarsa.
            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }

    /**
     * POST ke halaman web dengan token CSRF yang benar.
     *
     * Halaman "Application" dilindungi csrf_field(). Test ini memakai token
     * sungguhan, bukan mematikan proteksinya, supaya alur form yang diuji
     * sama dengan yang dipakai browser.
     *
     * @param array<string,mixed> $params
     */
    private function postForm(string $uri, array $params = []): TestResponse
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        // Token dibaca dari cookie, jadi cookie-nya ikut disiapkan.
        $_COOKIE[$security->getCookieName()] = $token;

        return $this->post($uri, $params + [$security->getTokenName() => $token]);
    }

    /**
     * POST AJAX ke halaman web: sama seperti postForm, tapi menyalakan
     * header X-Requested-With supaya controller menjawab JSON.
     *
     * @param array<string,mixed> $params
     */
    private function postAjax(string $uri, array $params = []): TestResponse
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        $_COOKIE[$security->getCookieName()] = $token;

        return $this->post(
            $uri,
            $params + [$security->getTokenName() => $token],
            ['X-Requested-With' => 'XMLHttpRequest']
        );
    }

    /**
     * Body JSON mentah dari sebuah TestResponse.
     *
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

    /**
     * Ambil isi tabel application dari endpoint DataTables server-side.
     *
     * Halaman /application tidak lagi memuat baris tabel di HTML-nya —
     * <tbody> dibangkitkan JavaScript. Jadi daftar application harus dibaca
     * dari endpoint yang sama dengan yang dipanggil browser.
     *
     * @param array<string,string> $query
     *
     * @return array<string,mixed>
     */
    private function tableData(array $query = []): array
    {
        $this->last = $this->get('application/data' . ($query === [] ? '' : '?' . http_build_query($query)));

        $this->last->assertOK();

        // Sengaja lewat response(), bukan getBody(). TestResponse::getBody()
        // diteruskan ke DOMParser yang memakai DOMDocument::loadHTML(), dan
        // itu membungkus JSON di dalam amplop HTML 4.0 sehingga json_decode
        // selalu gagal.
        $decoded = json_decode((string) $this->last->response()->getBody(), true);

        $this->assertIsArray($decoded, 'Endpoint application/data harus membalas JSON yang valid.');

        return $decoded;
    }

    /**
     * Kumpulan nama application pada satu halaman hasil tabel.
     *
     * @param array<string,mixed> $payload
     *
     * @return list<string>
     */
    private function names(array $payload): array
    {
        $names = array_column((array) ($payload['data'] ?? []), 'name');

        return array_map(static fn ($name): string => (string) $name, $names);
    }
}