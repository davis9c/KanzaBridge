<?php

namespace Tests\Feature;

use App\Libraries\Api\ApiKeyService;
use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;
use Tests\Support\Database\RoleAssignmentTrait;

/**
 * Dashboard = kartu ringkasan pemakaian API key.
 *
 * Halaman ini sengaja tidak menampilkan daftar key; itu ada di menu
 * Application. Jadi yang diuji di sini adalah angka-angka di kartunya.
 *
 * Yang dijaga:
 *   - Kartunya sesuai dengan key yang boleh dilihat user
 *     (SuperAdmin: semua, Admin: miliknya sendiri). pendekatannya kolom
 *     created_by, sama seperti halaman Application.
 *   - Tidak ada data sensitif yang bocor lewat HTML dashboard. Ini makin
 *     penting sekarang tabel key tidak lagi ada di halaman ini: yang
 *     tersisa hanyalah angka, dan angka pun tidak boleh memungkinkan
 *     apa pun yang bisa ditebak dari luar.
 *   - Query string yang dulu dipakai halaman filter tidak lagi berpengaruh.
 *
 * Halaman ini murni membaca `last_used_at`. Akurasinya satu menit karena
 * ApiKeyService::touchUsage() menulis paling sering sekali per menit per
 * key.
 */
final class ApiUsagePageTest extends CIUnitTestCase
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
        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- *
     *  AKSES
     * ---------------------------------------------------------------- */

    public function testDashboardTerbukaUntukAdminDanSuperAdmin(): void
    {
        foreach ([['ADMIN', false], ['SUPER_ADMIN', true]] as [$role, $isSuper]) {
            $this->loginAs([$role], $isSuper, $this->makeOwner());

            $this->last = $this->get('dashboard');

            $this->last->assertOK();
            $this->assertStringContainsString('Dashboard', (string) $this->last->getBody());
        }
    }

    public function testAdminHanyaMenghitungKeyMiliknyaSendiri(): void
    {
        $adminId = $this->makeOwner();

        $this->makeApiKey(['meta.read'], [], 'Milik Admin', $adminId);
        $this->makeApiKey(['meta.read'], [], 'Milik Admin 2', $adminId);
        $this->makeApiKey(['meta.read'], [], 'Milik Orang Lain', $this->makeOwner());

        $this->loginAs(['ADMIN'], false, $adminId);

        $body = $this->visit('dashboard');

        // Dua application milik admin, dua key.
        $this->assertSame('2', $this->summary($body, 'total'));
        $this->assertSame('2', $this->summary($body, 'applications'));

        // Key milik orang lain tidak ikut terhitung, jadi tidak bocor lewat
        // angka juga.
        $this->assertSame('2', $this->summary($body, 'active'));
    }

    public function testSuperAdminMenghitungSeluruhKey(): void
    {
        $this->makeApiKey(['meta.read'], [], 'Milik Admin A', $this->makeOwner());
        $this->makeApiKey(['jabatan.read'], [], 'Milik Admin B', $this->makeOwner());
        $this->makeApiKey(['meta.read'], [], 'Milik Admin C', $this->makeOwner());

        $this->loginAs(['SUPER_ADMIN'], true, $this->makeOwner());

        $body = $this->visit('dashboard');

        $this->assertSame('3', $this->summary($body, 'total'));
        $this->assertSame('3', $this->summary($body, 'applications'));
    }

    /* ---------------------------------------------------------------- *
     *  ISI KARTU
     * ---------------------------------------------------------------- */

    public function testRingkasanSesuaiDenganData(): void
    {
        $adminId = $this->makeOwner();

        $pertama = $this->makeApiKey(['meta.read'], [], 'Satu Aplikasi', $adminId);

        // Key kedua dan ketiga milik application yang sama supaya angka
        // "application" tidak ikut menghitung.
        $this->makeKeyFor((int) $pertama['application']['id'], 'Dua', ['meta.read']);
        $this->makeKeyFor((int) $pertama['application']['id'], 'Tiga', [
            'meta.read',
        ], [
            'status'                => ApiKeyModel::STATUS_REVOKED,
            'expires_at'            => date('Y-m-d H:i:s', time() - 60),
            'rate_limit_per_minute' => 30,
        ]);

        // Yang pertama sudah pernah dipakai.
        (new ApiKeyModel())->builder()
            ->where('id', (int) $pertama['key']['id'])
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        $this->loginAs(['ADMIN'], false, $adminId);

        $body = $this->visit('dashboard');

        $this->assertSame('1', $this->summary($body, 'applications'));
        $this->assertSame('3', $this->summary($body, 'total'));
        $this->assertSame('2', $this->summary($body, 'active'));
        $this->assertSame('1', $this->summary($body, 'revoked'));
        $this->assertSame('1', $this->summary($body, 'expired'));
        $this->assertSame('2', $this->summary($body, 'unused'));
    }

    public function testKeyBelumDipakaiTerhitungDiKartuBelumPernahDipakai(): void
    {
        $owner = $this->makeOwner();
        $made  = $this->makeApiKey(['meta.read'], [], 'Aplikasi Tanpa Pakai', $owner);

        $this->loginAs(['ADMIN'], false, $owner);

        $body = $this->visit('dashboard');

        $this->assertSame('1', $this->summary($body, 'unused'));

        // Setelah ditandai pernah dipakai, angkanya turun.
        (new ApiKeyModel())->builder()
            ->where('id', (int) $made['key']['id'])
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        $this->assertSame('0', $this->summary($this->visit('dashboard'), 'unused'));
    }

    public function testSemuaEnamKartuSelaluAda(): void
    {
        $this->loginAs(['ADMIN'], false, $this->makeOwner());

        $body = $this->visit('dashboard');

        foreach (['applications', 'total', 'active', 'revoked', 'expired', 'unused'] as $key) {
            $this->assertSame(
                1,
                substr_count($body, 'data-summary="' . $key . '"'),
                'Kartu "' . $key . '" hilang dari dashboard.'
            );
        }
    }

    public function testDashboardTanpaKeySemuaAngkaNol(): void
    {
        $this->loginAs(['ADMIN'], false, $this->makeOwner());

        $this->last = $this->get('dashboard');
        $body       = (string) $this->last->getBody();

        $this->last->assertOK();

        foreach (['applications', 'total', 'active', 'revoked', 'expired', 'unused'] as $key) {
            $this->assertSame('0', $this->summary($body, $key), 'Kartu "' . $key . '" seharusnya 0.');
        }

        // Tidak ada tabel lagi di dashboard, jadi tidak perlu pesan baris kosong.
        $this->assertStringNotContainsString('<table', $body);
    }

    /* ---------------------------------------------------------------- *
     *  TIDAK ADA DATA SENSITIF
     * ---------------------------------------------------------------- */

    /**
     * Dashboard hanya menampilkan angka. Tidak ada nama application, prefix
     * key, maupun hash yang boleh bocor lewat HTML-nya — termasuk milik
     * user lain.
     */
    public function testDashboardTidakMembocorkanDataKey(): void
    {
        $adminId    = $this->makeOwner();
        $milikAsing = $this->makeApiKey(['meta.read'], [], 'Milik Orang Lain', $this->makeOwner());
        $milikSendiri = $this->makeApiKey(['meta.read'], [], 'Milik Sendiri', $adminId);

        $this->loginAs(['ADMIN'], false, $adminId);

        $body = $this->visit('dashboard');

        foreach ([$milikAsing, $milikSendiri] as $made) {
            $this->assertStringNotContainsString((string) $made['application']['name'], $body);
            $this->assertStringNotContainsString((string) $made['application']['code'], $body);
            $this->assertStringNotContainsString((string) $made['key']['key_prefix'], $body);
            $this->assertStringNotContainsString((string) $made['key']['key_hash'], $body);
            $this->assertStringNotContainsString($made['plain'], $body);
            $this->assertStringNotContainsString(api_key_mask((string) $made['key']['key_prefix']), $body);
        }
    }

    /* ---------------------------------------------------------------- *
     *  TIDAK ADA FILTER LAGI
     * ---------------------------------------------------------------- */

    /**
     * Dashboard tidak punya filter lagi, jadi parameter query lama tidak
     * boleh mengubah angka — kalau iya, means query string bisa dipakai
     * untuk_surface data milik orang lain lewat filter.
     */
    public function testParameterQueryLamaTidakBerpengaruh(): void
    {
        $owner = $this->makeOwner();

        $dipakai = $this->makeApiKey(['meta.read'], [], 'Aplikasi Dipakai', $owner);
        $segar   = $this->makeApiKey(['meta.read'], [], 'Aplikasi Segar', $owner);

        (new ApiKeyModel())->builder()
            ->where('id', (int) $dipakai['key']['id'])
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        $this->loginAs(['ADMIN'], false, $owner);

        foreach ([
            'dashboard',
            'dashboard?unused=1',
            'dashboard?status=REVOKED',
            'dashboard?search=Aplikasi',
            'dashboard?unused=1&status=ACTIVE&search=apa+saja',
        ] as $route) {
            $body = $this->visit($route);

            $this->assertSame('2', $this->summary($body, 'total'), 'Angka berubah lewat ' . $route);
            $this->assertSame('1', $this->summary($body, 'unused'), 'Angka berubah lewat ' . $route);
        }
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * Ambil nilai satu kartu ringkasan dari HTML yang sudah dirender.
     *
     * Kartu diberi atribut data-summary supaya test tidak perlu menebak
     * angka mana yang milik kartu mana.
     */
    private function summary(string $body, string $key): string
    {
        // Angka diambil SESUDAH </i> ikon, karena kelas ikon sendiri
        // mengandung angka (mis. "me-1") dan akan lebih dulu tertangkap.
        $matched = preg_match(
            '/data-summary="' . preg_quote($key, '/') . '">.*?<\/i>\s*(\d+)/s',
            $body,
            $found
        );

        $this->assertSame(1, $matched, 'Kartu ringkasan "' . $key . '" tidak ditemukan di dashboard.');

        return $found[1];
    }

    /**
     * Buat key tambahan pada application yang sudah ada.
     *
     * Dipakai untuk menguji ringkasan tanpa menambah application baru.
     *
     * @param  list<string>        $scopes
     * @param  array<string,mixed> $overrides
     * @return array{application:array<string,mixed>, key:array<string,mixed>, plain:string}
     */
    private function makeKeyFor(int $applicationId, string $label, array $scopes, array $overrides = []): array
    {
        $keys      = new ApiKeyModel();
        $scopesDb  = new ApiKeyScopeModel();
        $generated = (new ApiKeyService())->generate();

        $keyId = $keys->createKey($overrides + [
            'application_id'        => $applicationId,
            'label'                 => 'Key ' . $label,
            'key_prefix'            => $generated['prefix'],
            'key_hash'              => $generated['hash'],
            'status'                => ApiKeyModel::STATUS_ACTIVE,
            'expires_at'            => null,
            'rate_limit_per_minute' => 0,
        ]);

        $scopesDb->sync($keyId, $scopes);

        $row = (array) $keys->find($keyId);

        return [
            'application' => ['id' => $applicationId],
            'key'         => $row,
            'plain'       => $generated['plain'],
        ];
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
            'access_usergate_id' => 'uuid-usage-test-' . $userId,
            'access_username'    => 'pengguna-' . $userId,
            'access_email'       => 'pengguna-' . $userId . '@example.com',
            'access_full_name'   => 'Pengguna Uji ' . $userId,
            'access_roles'       => $roles,
            'access_is_super'    => $isSuper,

            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }

    private function visit(string $route): string
    {
        $this->last = $this->get($route);

        return (string) $this->last->getBody();
    }
}