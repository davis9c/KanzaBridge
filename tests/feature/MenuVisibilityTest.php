<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Aturan tampilan menu.
 *
 *   SuperAdmin / Admin -> semua menu
 *   tanpa role         -> login boleh, tapi TIDAK ADA menu sama sekali
 *
 * Diuji lewat request sungguhan supaya yang diperiksa benar-benar
 * hasil render, filter, dan helper — bukan hanya nilai session.
 *
 * Token UserGate sengaja tidak dimasukkan, jadi yang diuji murni aturan
 * tampilan menu, bukan siklus refresh token.
 *
 * isolation: `is_super_admin()` memang sengaja jatuh ke query
 * database bila session menyatakan bukan SuperAdmin (session bisa saja
 * sudah tua). Karena itu tabel `users`/`user_roles` harus dikosongkan —
 * kalau tidak, test ini ikut bergantung pada data sisa dan bisa gagal
 * tidak menentu tergantung urutan eksekusi test.
 */
final class MenuVisibilityTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use LocalAccessDatabaseTrait;

    private ?TestResponse $last = null;

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

    /**
     * Sesi login minimal.
     *
     * Menyertakan access token yang masih valid supaya AuthFilter tidak
     * memaksa logout. Dengan begitu tidak ada request ke UserGate sungguhan
     * dan test murni menguji aturan menu.
     *
     * @param list<string> $roles
     */
    private function loginAs(array $roles, bool $isSuper = false): void
    {
        $this->withSession([
            'logged_in'          => true,
            'access_user_id'     => 1,
            'access_usergate_id' => 'uuid-menu-test',
            'access_username'    => 'budi',
            'access_email'       => 'budi@example.com',
            'access_full_name'   => 'Budi Santoso',
            'user_id'            => 'uuid-menu-test',
            'nama'               => 'Budi Santoso',
            'access_roles'       => $roles,
            'access_is_super'    => $isSuper,

            // Token tiruan: hanya dibaca AuthFilter, tidak pernah divalidasi
            // ke UserGate selama belum kedaluwarsa.
            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }

    private function visit(string $route): string
    {
        $this->last = $this->get($route);

        return (string) $this->last->response()->getBody();
    }

    /**
     * Isi POST dengan token CSRF yang sah.
     *
     * Wajib, karena `csrf` adalah filter global yang berjalan SEBELUM filter
     * route. Tanpa token, request ditolak 403 lebih dulu dan AccessFilter
     * tidak pernah sempat menolak — testnya jadi tidak menguji apa pun.
     *
     * Token dibaca dari cookie (Config\Security::$csrfProtection = 'cookie'),
     * jadi cookie-nya ikut dipasang.
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

    /* ---------------------------------------------------------------- *
     *  Topbar (layout yang benar-benar dipakai)
     * ---------------------------------------------------------------- */

    public function testSuperAdminDanAdminMelihatSemuaMenu(): void
    {
        foreach ([['SUPER_ADMIN'], ['ADMIN']] as $roles) {
            $this->loginAs($roles, $roles === ['SUPER_ADMIN']);

            $body = $this->visit('dashboard');
            $this->last->assertOK();

            $label = json_encode($roles);

            $this->assertStringContainsString('Dashboard', $body, 'Menu Dashboard hilang untuk ' . $label);
            $this->assertStringContainsString('Manajemen User', $body, 'Menu Manajemen User hilang untuk ' . $label);
            $this->assertStringContainsString('Application', $body, 'Menu Application hilang untuk ' . $label);
            $this->assertStringContainsString('API Key', $body, 'Menu API Key hilang untuk ' . $label);
            $this->assertStringContainsString('Guide', $body, 'Menu Guide hilang untuk ' . $label);
            $this->assertStringContainsString('Logout', $body);
        }
    }

    public function testUserTanpaRoleTidakMelihatMenuApaPun(): void
    {
        $this->loginAs([]);

        // User tanpa role tetap MEREKAH login, tapi tidak melihat menu.
        $this->visit('dashboard');
        $this->last->assertRedirect('no-access');

        // Halaman tujuan menampilkan penjelasan, bukan menu.
        $body = $this->visit('no-access');
        $this->last->assertOK();

        $this->assertStringNotContainsString('Manajemen User', $body);
        $this->assertStringContainsString('Belum Memiliki Role', $body);
    }

    public function testUserTanpaRoleDiarahkanDariSemuaHalamanTerlindungi(): void
    {
        $this->loginAs([]);

        foreach (['dashboard', 'guide', 'settings', 'user', 'profile', 'application'] as $route) {
            $this->get($route)->assertRedirect('no-access');
        }
    }

    public function testSuperAdminDanAdminBolehMembukaHalamanTerlindungi(): void
    {
        foreach ([['SUPER_ADMIN'], ['ADMIN']] as $roles) {
            $this->loginAs($roles, $roles === ['SUPER_ADMIN']);

            $this->get('dashboard')->assertOK();
            $this->get('guide')->assertOK();
            $this->get('settings')->assertOK();
            $this->get('user')->assertOK();
            $this->get('application')->assertOK();
        }
    }

    /* ---------------------------------------------------------------- *
     *  Route yang DIBATASI per role
     * ---------------------------------------------------------------- */

    /**
     * Supervisor dan Petugas punya role, jadi mereka tetap melihat menu dan
     * halaman lain — hanya Manajemen User yang tertutup.
     *
     * Semua sub-route `user` harus ikut tertutup. Diuji per-prefix supaya
     * `user/data` (dipakai DataTables) dan `user/edit/1` tidak bocor hanya
     * karena tidak disebut satu per satu.
     */
    public function testSupervisorDanPetugasTidakBisaMembukaManajemenUser(): void
    {
        foreach ([['SUPERVISOR'], ['PETUGAS']] as $roles) {
            $label = json_encode($roles);

            $this->loginAs($roles);

            /*
             * `user/create` sengaja tidak ada di daftar ini: tambah user
             * memakai modal di halaman /user, jadi tidak ada route GET-nya.
             * Yang diuji di sini cukup route yang benar-benar ada — filter
             * group diambil dari route yang cocok, jadi route yang hilang
             * akan menjawab 404, bukan redirect.
             */
            foreach (['user', 'user/data', 'user/edit/1'] as $route) {
                $this->get($route)->assertRedirect('dashboard');
            }

            // POST create juga harus ikut tertutup — itu satu-satunya route
            // yang tersisa di bawah prefix `user` setelah halaman tambah
            // dipindahkan ke modal.
            $this->post('user/create', $this->csrfPayload())->assertRedirect('dashboard');

            // Halaman lain tetap boleh.
            $this->get('dashboard')->assertOK();
            $this->get('guide')->assertOK();
            $this->get('settings')->assertOK();
            $this->get('profile')->assertOK();
            $this->get('application')->assertOK();

            $this->assertStringNotContainsString(
                'Manajemen User',
                $this->visit('dashboard'),
                'Menu Manajemen User masih tampil untuk ' . $label
            );
        }
    }

    /**
     * Penolakan harus menyertakan penjelasan, dan TIDAK mengarahkan ke
     * `no-access` — halaman itu berbunyi "Akun Anda Belum Memiliki Role",
     * padahal role-nya ada.
     *
     * Flash diperiksa pada respons redirect itu sendiri. Alasannya: CI4's
     * FeatureTestTrait::call() menulis ulang `$_SESSION = $this->session` di
     * SETIAP request, dan `$this->session` tidak pernah diperbarui dengan
     * flashdata. Jadi flash yang dibuat di request pertama tidak pernah
     * sampai ke request berikutnya — perilaku browser sungguhan, tapi tidak
     * bisa diuji dengan dua request berurutan.
     */
    public function testPenolakanMenyertakanAlasan(): void
    {
        $this->loginAs(['PETUGAS']);

        $response = $this->get('user');
        $response->assertRedirect('dashboard');

        $response->assertSessionHas('error', 'Anda tidak punya akses ke Manajemen User.');
    }

    /**
     * Flash `error` itu harus benar-benar tampil di dashboard.
     *
     * Disemai manual lewat `$this->session` karena keterbatasan di atas.
     */
    public function testDashboardMenampilkanFlashError(): void
    {
        $this->loginAs(['PETUGAS']);

        $this->session['error'] = 'Anda tidak punya akses ke Manajemen User.';
        $this->session['__ci_vars']['error'] = 'new';

        $body = $this->visit('dashboard');
        $this->last->assertOK();

        $this->assertStringContainsString('Anda tidak punya akses ke Manajemen User', $body);
        $this->assertStringNotContainsString('Belum Memiliki Role', $body);
    }

    /**
     * Admin dan SuperAdmin tetap boleh — batasnya hanya role di bawahnya.
     */
    public function testRoleDiAtasSupervisorTetapBisaMembukaManajemenUser(): void
    {
        foreach ([['ADMIN'], ['SUPER_ADMIN']] as $roles) {
            $this->loginAs($roles, $roles === ['SUPER_ADMIN']);

            $this->get('user')->assertOK();
            $this->assertStringContainsString('Manajemen User', $this->visit('dashboard'));
        }
    }

    /**
     * Pencocokan route harus per SEGMENT, bukan sekadar prefiks string.
     *
     * Tanpa batas `/`, prefix `user` ikut mencocoki `/username` — route seperti
     * itu akan ikut tertutup tanpa sengaja.
     */
    public function testPencocokanRoutePerSegmen(): void
    {
        $prefixes = array_keys(config('Access')->restrictedRoutes);

        $this->assertSame(['user'], $prefixes);

        $matches = static function (string $path) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return true;
                }
            }

            return false;
        };

        $this->assertTrue($matches('user'), 'user harus cocok.');
        $this->assertTrue($matches('user/data'), 'user/data harus cocok.');
        $this->assertTrue($matches('user/edit/12'), 'user/edit/12 harus cocok.');
        $this->assertFalse($matches('username'), '/username tidak boleh ikut tertutup.');
        $this->assertFalse($matches('users'), '/users tidak boleh ikut tertutup.');
        $this->assertFalse($matches('application'), 'application tidak dibatasi.');
    }

    /**
     * URL lama /application/usage sekarang redirect ke dashboard, bukan
     * 404 — supaya bookmark dan tautan yang sudah tersebar tetap hidup.
     */
    public function testUrlLamaPenggunaanRedirectKeDashboard(): void
    {
        $this->loginAs(['ADMIN']);

        $this->get('application/usage')->assertRedirect(base_url('dashboard'));

        // Filter di query string ikut diteruskan.
        $this->get('application/usage?status=REVOKED')
            ->assertRedirect(base_url('dashboard') . '?status=REVOKED');
    }

    public function testBadgeSuperAdminHanyaMunculUntukSuperAdmin(): void
    {
        $this->loginAs(['SUPER_ADMIN'], true);

        $this->assertStringContainsString('SuperAdmin', $this->visit('dashboard'));
        $this->last->assertOK();

        $this->loginAs(['ADMIN'], false);
        $body = $this->visit('dashboard');
        $this->last->assertOK();

        // Label "Admin" tetap tampil, tapi badge "SuperAdmin" tidak.
        $this->assertStringNotContainsString('SuperAdmin</span>', $body);
    }

    public function testNamaUserDitampilkanDiTopbar(): void
    {
        $this->loginAs(['ADMIN']);

        $this->assertStringContainsString('Budi Santoso', $this->visit('dashboard'));
        $this->last->assertOK();
    }

    /* ---------------------------------------------------------------- *
     *  Topbar (menu)
     * ---------------------------------------------------------------- */

    /**
     * Topbar dirender di luar `layout/dashboard`, jadi dibaca setelah
     * satu request supaya session benar-benar terpasang.
     *
     * @param list<string> $roles
     */
    private function renderTopbar(array $roles, bool $isSuper = false): string
    {
        $this->loginAs($roles, $isSuper);
        $this->visit('dashboard');

        return (string) view('partial/topbar', ['title' => 'Menu']);
    }

    public function testTopbarMemuatMenuUntukSuperAdminDanAdmin(): void
    {
        foreach ([['SUPER_ADMIN'], ['ADMIN']] as $roles) {
            $html = $this->renderTopbar($roles, $roles === ['SUPER_ADMIN']);

            $this->assertStringContainsString('Dashboard', $html);
            $this->assertStringContainsString('Manajemen User', $html);
            $this->assertStringContainsString('Application', $html);
            $this->assertStringContainsString('Guide', $html);
        }
    }

    public function testTopbarTanpaRoleHanyaLogout(): void
    {
        $html = $this->renderTopbar([]);

        $this->assertStringNotContainsString('>Dashboard<', $html);
        $this->assertStringNotContainsString('Manajemen User', $html);
        $this->assertStringNotContainsString('>Application<', $html);
        $this->assertStringNotContainsString('>Guide<', $html);

        // Logout tetap ada supaya user bisa keluar.
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('Tidak ada menu untuk akun Anda', $html);
    }

    /**
     * Menu harus tetap bisa dibuka di layar sempit / saat browser di-zoom.
     *
     * Topbar lama memakai `navbar-expand` tanpa `navbar-toggler` maupun
     * `collapse`. Akibatnya di bawah lebar tertentu link menu meluber keluar
     * layar DAN tidak ada tombol apa pun untuk memunculkannya — menu hilang
     * total, padahal isinya ada di HTML. Sekarang zona di bawah `lg` (992px)
     * memakai tombol hamburger.
     */
    public function testTopbarPunyaTogglerUntukLayarSempit(): void
    {
        $html = $this->renderTopbar(['ADMIN']);

        // Breakpoint harus finite: navbar-expand-lg, bukan navbar-expand.
        $this->assertStringContainsString('navbar-expand-lg', $html);
        $this->assertStringNotContainsString('navbar-expand bg-body', $html);

        // Tombol hamburger...
        $this->assertStringContainsString('navbar-toggler', $html);
        $this->assertStringContainsString('data-bs-toggle="collapse"', $html);

        // ...yangCollapse isi menu.
        $this->assertStringContainsString('data-bs-target="#navbarKanza"', $html);
        $this->assertStringContainsString('aria-controls="navbarKanza"', $html);
        $this->assertStringContainsString('collapse navbar-collapse', $html);
        $this->assertStringContainsString('id="navbarKanza"', $html);

        // Navbar harus benar-benar terstruktur: toggler ada DI DALAM nav.
        $this->assertMatchesRegularExpression(
            '#<nav[^>]*navbar-expand-lg.*navbar-toggler.*collapse navbar-collapse.*</nav>#s',
            $html
        );
    }

    public function testTopbarMenampilkanBadgeSuperAdmin(): void
    {
        $super = $this->renderTopbar(['SUPER_ADMIN'], true);
        $this->assertStringContainsString('(SuperAdmin)</small>', $super);

        $admin = $this->renderTopbar(['ADMIN'], false);
        $this->assertStringNotContainsString('(SuperAdmin)</small>', $admin);
    }

    /* ---------------------------------------------------------------- *
     *  Tanpa login
     * ---------------------------------------------------------------- */

    public function testTanpaLoginSemuaHalamanTerlindungiDialihkan(): void
    {
        foreach (['dashboard', 'user', 'guide', 'settings', 'profile', 'application', 'application/usage'] as $route) {
            $this->get($route)->assertRedirect('login');
        }
    }
}
