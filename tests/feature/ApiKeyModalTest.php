<?php

namespace Tests\Feature;

use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Views\ScrollableModalTrait;

/**
 * Halaman "API Key" setelah form buat key dipindah ke dalam modal.
 *
 * Yang dijaga:
 *   - Form buat key tidak lagi ada sebagai kartu penuh di halaman; hanya
 *     tombol pembuka modalnya.
 *   - Kalau validasi create gagal, halaman yang kembali harus membuka
 *     modal create dengan isian lama — kalau tidak, user melihat halaman
 *     yang terlihat utuh tapi tidak tahu ke mana harus isi ulang.
 *   - Kalau validasi hak akses gagal, modal hak akses untuk key yang
 *     benar harus terbuka lagi dengan centang yang sama seperti yang
 *     dicoba.
 *   - Endpoint minimal satu tetap berlaku di kedua jalur.
 */
final class ApiKeyModalTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
    use FeatureTestTrait;
    use LocalAccessDatabaseTrait;
    use ScrollableModalTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLocalAccessDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- *
     *  TATA LETAK
     * ---------------------------------------------------------------- */

    public function testHanyaMenampilkanTabelDanTombol(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $body = $this->visit('application/' . $app . '/keys');

        // Tabel dan tombol pembuka modal ada.
        $this->assertStringContainsString('id="tbl-api-key"', $body);
        $this->assertStringContainsString('id="btnCreateKey"', $body);
        $this->assertStringContainsString('id="modalCreateKey"', $body);
        $this->assertStringContainsString('id="modalKeyScopes"', $body);

        // Card "Buat API Key Baru" sebagai blok halaman sudah tidak ada:
        // heading itu hanya boleh muncul sebagai judul modal.
        $this->assertSame(1, substr_count($body, 'Buat API Key Baru'));
        $this->assertStringContainsString('id="modalCreateKeyLabel"', $body);
        $this->assertSame(0, substr_count($body, 'name="label" class="form-control'));

        // Tidak ada form create di luar modal: satu-satunya form yang POST
        // ke endpoint create berada di dalam #modalCreateKey.
        $this->assertMatchesRegularExpression(
            '#id="modalCreateKey".*?action="[^"]*/keys"#s',
            $body,
            'Form create harus berada di dalam modal.'
        );
    }

    /**
     * Script yang memakai `bootstrap.*` harus dirender SETELAH bundle
     * Bootstrap, bukan di dalam content section.
     *
     * Layout memuat `bootstrap.bundle.min.js` di dekat akhir `<body>`, yaitu
     * SESUDAH `renderSection('content')`. Inline `<script>` dieksekusi sinkron
     * saat parsing, jadi script yang diletakkan di content section berjalan
     * sebelum global `bootstrap` ada dan berhenti dengan
     * `ReferenceError: bootstrap is not defined`.
     *
     * Gejalanya deceptive: markup modal sudah benar dan test markup lolos,
     * tapi satu exception membatalkan seluruh IIFE — tombol "Buat Key" tidak
     * bereaksi, "Hak Akses" mati, "Salin" mati, dan auto-reopen setelah
     * validasi gagal ikut mati. Yang tetap jalan hanya DataTable di
     * `scripts` section, jadi halaman terlihat normal.
     *
     * PHPUnit tidak menjalankan JavaScript, jadi yang bisa dikunci di sini
     * adalah invarian strukturalnya: urutan di HTML akhir.
     */
    public function testScriptModalDirenderSetelahBundleBootstrap(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $body = $this->visit('application/' . $app . '/keys');

        $bundle = strpos($body, 'bootstrap.bundle.min.js');
        $use    = strpos($body, 'new bootstrap.Modal');

        $this->assertNotFalse($bundle, 'Layout harus tetap memuat bundle Bootstrap.');
        $this->assertNotFalse($use, 'Halaman harus tetap punya script modal.');

        $this->assertGreaterThan(
            $bundle,
            $use,
            'Script yang memakai bootstrap.Modal dirender SEBELUM bundle Bootstrap. '
            . 'Pindahkan blok <script> ke section(\'scripts\') di layout/dashboard.php.'
        );
    }

    /**
     * Modal create dan modal hak akses memakai `modal-dialog-scrollable`.
     * Kalau `<form>` dibungkus di dalam `.modal-content`, rantai flex terputus
     * dan modal terpotong tanpa scrollbar — tombol "Buat Key" di footer tidak
     * terlihat dan tidak bisa dijangkau.
     *
     * Gejalanya murni visual, jadi test markup yang biasa ("modal ini ada")
     * lolos padahal halamannya rusak. Yang dikunci di sini adalah prasyaratnya:
     * `.modal-body` harus anak langsung dari `.modal-content`.
     *
     * @see ScrollableModalTrait
     */
    public function testModalScrollableTerputusForm(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $this->assertScrollableModalBodyIsDirectChild(
            $this->visit('application/' . $app . '/keys')
        );
    }

    /**
     * Dua modal harus punya set checkbox-nya sendiri dengan prefix id
     * berbeda, supaya tidak ada id DOM kembar saat keduanya terbuka.
     *
     * Partial-nya dirender langsung dengan prefix eksplisit supaya yang
     * diuji benar-benar perilaku prefix, bukan sekadar isi halaman.
     */
    public function testChecklistMemakaiPrefixIdYangDiberikan(): void
    {
        $catalog = api_scope_catalog();

        $create = (string) view('sys-application/_scope-checklist', [
            'catalog'   => $catalog,
            'selected'  => ['meta.read'],
            'idPrefix'  => 'new-key',
            'showTools' => false,
        ]);

        $scopes = (string) view('sys-application/_scope-checklist', [
            'catalog'   => $catalog,
            'selected'  => ['jabatan.read'],
            'idPrefix'  => 'scope-key',
            'showTools' => true,
        ]);

        foreach (array_keys(config('ApiScope')->endpoints) as $scope) {
            $this->assertSame(1, substr_count($create, 'id="new-key-' . $scope . '"'), 'Modal create: ' . $scope);
            $this->assertSame(1, substr_count($scopes, 'id="scope-key-' . $scope . '"'), 'Modal hak akses: ' . $scope);

            // Label harus menunjuk checkbox yang ada, di masing-masing modal.
            $this->assertSame(1, substr_count($create, 'for="new-key-' . $scope . '"'));
            $this->assertSame(1, substr_count($scopes, 'for="scope-key-' . $scope . '"'));

            // Tidak ada id yang berprefix satu tapi dipakai di modal lain.
            $this->assertSame(0, substr_count($scopes, 'id="new-key-' . $scope . '"'));
            $this->assertSame(0, substr_count($create, 'id="scope-key-' . $scope . '"'));
        }

        // Yang tercentang hanya yang diminta: meta.read di modal create,
        // jabatan.read di modal hak akses.
        $this->assertSame(1, substr_count($create, 'checked'), 'Modal create mencentang 1 scope.');
        $this->assertSame(1, substr_count($scopes, 'checked'), 'Modal hak akses mencentang 1 scope.');

        $this->assertMatchesRegularExpression(
            '#id="new-key-meta\.read"[^>]*\bchecked#s',
            $create,
            'meta.read harus tercentang di modal create.'
        );
        $this->assertMatchesRegularExpression(
            '#id="scope-key-jabatan\.read"[^>]*\bchecked#s',
            $scopes,
            'jabatan.read harus tercentang di modal hak akses.'
        );

        // Tombol Pilih semua hanya ada di modal hak akses.
        $this->assertStringContainsString('data-scope-toggle="all"', $scopes);
        $this->assertStringNotContainsString('data-scope-toggle', $create);
        $this->assertStringContainsString('<span data-scope-count>1</span>', $scopes);
    }

    /* ---------------------------------------------------------------- *
     *  VALIDASI CREATE
     * ---------------------------------------------------------------- */

    /**
     * Controller harus menandai form mana yang gagal.
     *
     * Diuji terpisah dari render halaman karena FeatureTestTrait mengosongkan
     * $_SESSION di setiap request — flashdata tidak bertahan dari satu
     * request ke request berikutnya di dalam satu test. Yang bisa diperiksa
     * di sini adalah isi penandanya; efeknya ke halaman diuji di test render.
     */
    public function testCreateGagalMemberiPenandaModalCreate(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $this->last = $this->postForm('application/' . $app . '/keys', [
            'label'  => 'Key Tanpa Scope',
            'scopes' => [],
        ]);

        $this->last->assertRedirect('application/' . $app . '/keys');
        $this->last->assertSessionHas('errorForm', 'create');
        $this->assertTrue($this->last->isRedirect());

        // Pesan error dan isian lama ikut, supaya bisa dikembalikan ke modal.
        $this->assertSame(
            'Pilih minimal satu endpoint yang boleh diakses.',
            session('errors')['scopes'] ?? null
        );
        // withInput() menyimpan ulang input dari $_POST. Pada request ini label
        // sengaja kosong (yang diuji adalah kasus "tidak ada scope"), jadi
        // yang kembali memang tidak ada — yang penting penandanya ada
        // supaya modal reopened dengan pesan errornya.
        $this->assertArrayHasKey('post', session('_ci_old_input'));

        // Dan memang tidak tersimpan.
        $this->assertNull((new ApiKeyModel())->where('label', 'Key Tanpa Scope')->first());
    }

    public function testCreateTanpaNamaJugaMemberiPenanda(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $this->last = $this->postForm('application/' . $app . '/keys', [
            'label'  => '   ',
            'scopes' => ['meta.read'],
        ]);

        $this->last->assertSessionHas('errorForm', 'create');
        $this->assertSame('Nama key wajib diisi.', session('errors')['label'] ?? null);
    }

    /**
     * Efek penanda terhadap halaman: modal create harus terbuka sendiri,
     * lengkap dengan isian lama dan pesan error.
     */
    public function testModalCreateTerbukaDenganIsianLama(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        // Setup flashdata persis seperti yang dilakukan controller. Tidak ada
        // request di antaranya: call() pada FeatureTestTrait mengosongkan
        // $_SESSION, jadi flashdata harus dipasang setelah request terakhir.
        session()->setFlashdata('errorForm', 'create');
        session()->setFlashdata('errors', ['scopes' => 'Pilih minimal satu endpoint yang boleh diakses.']);
        $_SESSION['_ci_old_input'] = [
            'get'  => [],
            'post' => ['label' => 'Key Tanpa Scope', 'scopes' => []],
        ];

        $body = $this->renderKeys($app);

        $this->assertStringContainsString('data-auto-open="1"', $body);
        $this->assertStringContainsString('createModal.show();', $body);
        $this->assertStringContainsString('Key Tanpa Scope', $body);
        $this->assertStringContainsString('Pilih minimal satu endpoint', $body);
    }

    public function testCreateBerhasilTetapTampilSekali(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);

        $this->last = $this->postForm('application/' . $app . '/keys', [
            'label'  => 'Key Baru',
            'scopes' => ['meta.read', 'users.read'],
        ]);

        $this->last->assertRedirect('application/' . $app . '/keys');

        // Key plaintext hanya keluar lewat flashdata, sekali saja.
        $this->last->assertSessionHas('new_api_key');
        $this->last->assertSessionHas('new_api_key_label', 'Key Baru');

        // Tidak ada penanda reopen: halaman sukses tidak membuka modal.
        $this->assertArrayNotHasKey('errorForm', session()->getFlashdata() ?: []);

        $body = $this->visit('application/' . $app . '/keys');

        // Modal tidak reopened pada sukses.
        $this->assertStringNotContainsString('data-auto-open="1"', $body);

        // Key benar-benar tersimpan dengan scope-nya.
        $keys = (new ApiKeyModel())->where('label', 'Key Baru')->first();
        $this->assertNotNull($keys);
        $this->assertSame(
            ['meta.read', 'users.read'],
            (new ApiKeyScopeModel())->forKey((int) $keys['id'])
        );
    }

    /**
     * Panel key plaintext harus benar-benar ada di HTML, karena itu satu-
     *-satunya tempat secret itu keluar dari server.
     *
     * Flashdata tidak bertahan antar-request di dalam test, jadi halaman
     * dirender dengan flashdata-nya diisi manual.
     */
    public function testPanelKeyPlaintextTampilDenganIsi(): void
    {
        [$app, $owner] = $this->appWithKeys();

        $this->loginAs($owner);
        session()->setFlashdata('new_api_key', 'kb_live_rahasia1234567890');
        session()->setFlashdata('new_api_key_label', 'Produksi');

        $body = $this->renderKeys($app);

        $this->assertStringContainsString('id="new-key-alert"', $body);
        $this->assertStringContainsString('id="new-key-value"', $body);
        $this->assertStringContainsString('kb_live_rahasia1234567890', $body);
        $this->assertStringContainsString('Produksi', $body);
        $this->assertStringContainsString('X-API-Key:', $body, 'Contoh curl harus ikut ditampilkan.');
    }

    /* ---------------------------------------------------------------- *
     *  VALIDASI HAK AKSES
     * ---------------------------------------------------------------- */

    public function testScopeKosongMemberiPenandaModalYangBenar(): void
    {
        [$app, $owner] = $this->appWithKeys();
        $keyId         = $this->keyIdOf($app, 'Key Uji');

        $this->loginAs($owner);

        $this->last = $this->postForm('application/' . $app . '/keys/' . $keyId . '/scopes', [
            'scopes' => [],
        ]);

        $this->last->assertRedirect('application/' . $app . '/keys');
        $this->last->assertSessionHas('errorForm', 'scopes');
        $this->last->assertSessionHas('errorKeyId', $keyId);

        // Scope lama tidak ikut hilang — request ini tidak mengubah apa pun.
        $this->assertSame(['meta.read'], (new ApiKeyScopeModel())->forKey($keyId));
    }

    /**
     * Penanda hak akses harus membuka modal permission milik key yang
     * gagal disimpan, bukan modal create.
     */
    public function testModalHakAksesTerbukaUntukKeyYangBenar(): void
    {
        [$app, $owner] = $this->appWithKeys();
        $keyId         = $this->keyIdOf($app, 'Key Uji');

        $this->loginAs($owner);

        session()->setFlashdata('errorForm', 'scopes');
        session()->setFlashdata('errorKeyId', $keyId);
        session()->setFlashdata('error', 'Key harus punya minimal satu endpoint yang boleh diakses.');
        $_SESSION['_ci_old_input'] = ['get' => [], 'post' => ['scopes' => []]];

        $body = $this->renderKeys($app);

        $this->assertStringContainsString('openScopes(button)', $body);
        $this->assertStringContainsString(
            '.js-scopes[data-key-id="' . $keyId . '"]',
            $body,
            'Modal hak akses yang dibuka harus milik key yang gagal disimpan.'
        );
        $this->assertStringContainsString('minimal satu endpoint', $body);

        // Modal create tidak yang dibuka.
        $this->assertStringNotContainsString('data-auto-open="1"', $body);
    }

    public function testSimpanScopeBerhasilTetapBerfungsi(): void
    {
        [$app, $owner] = $this->appWithKeys();
        $keyId         = $this->keyIdOf($app, 'Key Uji');

        $this->loginAs($owner);

        $this->postForm('application/' . $app . '/keys/' . $keyId . '/scopes', [
            'scopes' => ['jabatan.read', 'dokter.read'],
        ])->assertRedirect('application/' . $app . '/keys');

        $this->assertSame(
            ['dokter.read', 'jabatan.read'],
            (new ApiKeyScopeModel())->forKey($keyId)
        );

        // Halaman tidak reopen modal mana pun.
        $this->assertStringNotContainsString('data-auto-open="1"', $this->visit('application/' . $app . '/keys'));
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * @return array{0: int, 1: int} id application dan id owner
     */
    private function appWithKeys(): array
    {
        $made = $this->makeApiKey(['meta.read'], [], 'Key Uji');

        return [(int) $made['application']['id'], (int) $made['application']['created_by']];
    }

    private function keyIdOf(int $appId, string $label): int
    {
        // Builder tidak punya first(); pakai find() dari model.
        $row = (new ApiKeyModel())
            ->where('application_id', $appId)
            ->where('label', $label)
            ->first();

        $this->assertNotNull($row, 'Key "' . $label . '" tidak ditemukan.');

        return (int) $row['id'];
    }

    private function loginAs(int $ownerId): void
    {
        $this->withSession([
            'logged_in'          => true,
            'access_user_id'     => $ownerId,
            'access_usergate_id' => 'uuid-keymodal-' . $ownerId,
            'access_username'    => 'pemilik-' . $ownerId,
            'access_email'       => 'pemilik-' . $ownerId . '@example.com',
            'access_full_name'   => 'Pemilik ' . $ownerId,
            'access_roles'       => ['ADMIN'],
            'access_is_super'    => false,

            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }

    private function postForm(string $uri, array $params = []): TestResponse
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        $_COOKIE[$security->getCookieName()] = $token;

        return $this->post($uri, $params + [$security->getTokenName() => $token]);
    }

    private function visit(string $route): string
    {
        $this->last = $this->get($route);

        return (string) $this->last->getBody();
    }

    /**
     * Render halaman key dengan flashdata yang sudah disiapkan manual.
     *
     * Dipisah dari visit() karena FeatureTestTrait mengosongkan $_SESSION
     * di setiap call(), jadi flashdata yang dibuat request sebelumnya tidak
     * akan terbaca pada request berikutnya di dalam satu test.
     */
    /**
     * Render halaman key dengan flashdata yang sudah disiapkan manual.
     *
     * Sengaja TIDAK memanggil $this->get() di sini: setiap call() pada
     * FeatureTestTrait mengosongkan $_SESSION, sehingga flashdata yang
     * sudah disiapkan akan hilang sebelum view dirender.
     */
    private function renderKeys(int $appId): string
    {
        return (string) view('sys-application/keys', [
            'title'       => 'API Key',
            'application' => ['id' => $appId, 'name' => 'Aplikasi Uji'],
            'keys'        => (new ApiKeyModel())->listForApplication($appId),
            'catalog'     => api_scope_catalog(),
            'statuses'    => ApiKeyModel::statuses(),
        ]);
    }
}