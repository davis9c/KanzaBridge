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

        foreach (['dashboard', 'guide', 'settings', 'user', 'profile'] as $route) {
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
        }
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
     *  Sidebar
     * ---------------------------------------------------------------- */

    /**
     * Sidebar dirender di luar `layout/dashboard`, jadi dibaca setelah
     * satu request supaya session benar-benar terpasang.
     *
     * @param list<string> $roles
     */
    private function renderSidebar(array $roles, bool $isSuper = false): string
    {
        $this->loginAs($roles, $isSuper);
        $this->visit('dashboard');

        return (string) view('partial/sidebar', ['title' => 'Menu']);
    }

    public function testSidebarMemuatMenuUntukSuperAdminDanAdmin(): void
    {
        foreach ([['SUPER_ADMIN'], ['ADMIN']] as $roles) {
            $html = $this->renderSidebar($roles, $roles === ['SUPER_ADMIN']);

            $this->assertStringContainsString('Dashboard', $html);
            $this->assertStringContainsString('Manajemen User', $html);
            $this->assertStringContainsString('Guide', $html);
        }
    }

    public function testSidebarTanpaRoleHanyaLogout(): void
    {
        $html = $this->renderSidebar([]);

        $this->assertStringNotContainsString('>Dashboard<', $html);
        $this->assertStringNotContainsString('Manajemen User', $html);
        $this->assertStringNotContainsString('>Guide<', $html);

        // Logout tetap ada supaya user bisa keluar.
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('Tidak ada menu untuk akun Anda', $html);
    }

    public function testSidebarMenampilkanBadgeSuperAdmin(): void
    {
        $super = $this->renderSidebar(['SUPER_ADMIN'], true);
        $this->assertStringContainsString('>SuperAdmin</span>', $super);

        $admin = $this->renderSidebar(['ADMIN'], false);
        $this->assertStringNotContainsString('>SuperAdmin</span>', $admin);
    }

    /* ---------------------------------------------------------------- *
     *  Tanpa login
     * ---------------------------------------------------------------- */

    public function testTanpaLoginSemuaHalamanTerlindungiDialihkan(): void
    {
        foreach (['dashboard', 'user', 'guide', 'settings', 'profile'] as $route) {
            $this->get($route)->assertRedirect('login');
        }
    }
}
