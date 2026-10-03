<?php

namespace Tests\Feature;

use App\Models\Access\ApiApplicationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Endpoint application/data untuk DataTables server-side, dan jalur JSON
 * pada simpan/ubah yang dipakai modal.
 *
 * Yang dijaga:
 *   - Kontrak DataTables terpenuhi: draw, recordsTotal, recordsFiltered,
 *     data. Tanpa draw yang dikembalikan, respons lama bisa menimpa yang
 *     baru.
 *   - recordsTotal TIDAK membocorkan application milik user lain. Ini
 *     angka yang selalu terlihat user, jadi harus ikut aturan kepemilikan.
 *   - Parameter sorting dari browser tidak pernah masuk SQL apa adanya:
 *     index di luar whitelist diabaikan.
 *   - Pagination benar-benar bekerja dan dibatasi.
 *   - Respons JSON selalu membawa token CSRF terbaru. Token di-regenerate
 *     tiap POST sukses (Config\Security::$regenerate = true) dan cookienya
 *     httpOnly, jadi tanpa ini request kedua dari modal selalu gagal.
 */
final class ApplicationTableDataTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
    use FeatureTestTrait;
    use LocalAccessDatabaseTrait;

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
     *  KONTRAK RESPONS
     * ---------------------------------------------------------------- */

    public function testResponsMemilikiEmpatFieldDataTables(): void
    {
        $this->loginAsSuper($this->makeOwner());

        $payload = $this->tableData(['draw' => 7]);

        $this->assertArrayHasKey('draw', $payload);
        $this->assertArrayHasKey('recordsTotal', $payload);
        $this->assertArrayHasKey('recordsFiltered', $payload);
        $this->assertArrayHasKey('data', $payload);
    }

    public function testDrawDikembalikanApaAdanya(): void
    {
        $this->loginAsSuper($this->makeOwner());

        // DataTables memakai draw untuk membuang respons yang sudah basi.
        $this->assertSame(12, $this->tableData(['draw' => 12])['draw']);
        $this->assertSame(0, $this->tableData(['draw' => 0])['draw']);

        // Nilai aneh tidak boleh membuat error.
        $this->assertSame(0, $this->tableData(['draw' => -5])['draw']);
        $this->assertSame(0, $this->tableData(['draw' => 'abc'])['draw']);
    }

    public function testBarisMemuatDataYangDibutuhkanTabel(): void
    {
        $adminId = $this->makeOwner();
        $made    = $this->makeApiKey(['meta.read'], [], 'Kios Pendaftaran', $adminId);
        $appId   = (int) $made['application']['id'];

        $this->loginAsSuper($adminId);

        $row = $this->tableData()['data'][0];

        foreach ([
            'id', 'name', 'code', 'description', 'ownerName', 'ownerless',
            'createdAt', 'keyTotal', 'keyActive', 'keysUrl', 'editUrl', 'deleteUrl',
        ] as $field) {
            $this->assertArrayHasKey($field, $row, 'Baris kurang field "' . $field . '".');
        }

        $this->assertSame($appId, $row['id']);
        $this->assertSame(1, $row['keyTotal']);
        $this->assertSame(1, $row['keyActive']);
        $this->assertSame(base_url('application/' . $appId . '/keys'), $row['keysUrl']);
        $this->assertSame(base_url('application/edit/' . $appId), $row['editUrl']);
        $this->assertSame(base_url('application/delete/' . $appId), $row['deleteUrl']);
    }

    /* ---------------------------------------------------------------- *
     *  ATURAN KEPEMILIKAN
     * ---------------------------------------------------------------- */

    public function testRecordsTotalAdminTidakMenghitungMilikOrangLain(): void
    {
        $adminId = $this->makeOwner();
        $orangId = $this->makeOwner();

        $this->makeApplication($adminId);
        $this->makeApplication($adminId);
        $this->makeApplication($orangId);

        $this->loginAs(['ADMIN'], false, $adminId);

        $payload = $this->tableData();

        $this->assertSame(2, $payload['recordsTotal'], 'recordsTotal hanya boleh menghitung milik Admin.');
        $this->assertCount(2, $payload['data']);
    }

    public function testApplicationTanpaOwnerTidakMunculUntukAdmin(): void
    {
        $adminId = $this->makeOwner();

        $applications = new ApiApplicationModel();
        $orphanId     = $applications->createApplication([
            'name'       => 'Tanpa Owner ' . uniqid(),
            'code'       => 'tanpa-owner-' . uniqid(),
            'created_by' => null,
        ]);

        $this->makeApplication($adminId);

        $this->loginAs(['ADMIN'], false, $adminId);

        $payload = $this->tableData();

        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertNotContains($orphanId, array_column($payload['data'], 'id'));
    }

    /* ---------------------------------------------------------------- *
     *  PENCARIAN DAN PAGING
     * ---------------------------------------------------------------- */

    public function testSearchMenyaringNamaDanKode(): void
    {
        $adminId = $this->makeOwner();

        $cocok = $this->makeApplication($adminId, 'Kios Pendaftaran');
        $lain  = $this->makeApplication($adminId, 'Modul Farmasi');

        $this->loginAs(['ADMIN'], false, $adminId);

        $byName = $this->tableData(['search[value]' => 'Kios']);

        $this->assertSame(1, (int) $byName['recordsFiltered']);
        $this->assertSame(2, $byName['recordsTotal'], 'recordsTotal tidak boleh terpengaruh pencarian.');
        $this->assertSame((int) $cocok['application']['id'], (int) $byName['data'][0]['id']);

        $byCode = $this->tableData(['search[value]' => $lain['application']['code']]);

        $this->assertSame(1, (int) $byCode['recordsFiltered']);
        $this->assertSame((int) $lain['application']['id'], (int) $byCode['data'][0]['id']);
    }

    public function testPagingMengambilSegmenYangBenar(): void
    {
        $adminId = $this->makeOwner();

        $made = [];
        for ($i = 1; $i <= 7; $i++) {
            $made[$i] = $this->makeApplication($adminId, sprintf('Aplikasi %02d', $i))['application'];
        }

        $this->loginAs(['ADMIN'], false, $adminId);

        $first = $this->tableData(['start' => 0, 'length' => 3, 'order[0][column]' => 0, 'order[0][dir]' => 'asc']);
        $this->assertCount(3, $first['data']);
        $this->assertSame(7, $first['recordsTotal']);
        $this->assertSame((string) $made[1]['name'], $first['data'][0]['name']);

        $second = $this->tableData(['start' => 3, 'length' => 3, 'order[0][column]' => 0, 'order[0][dir]' => 'asc']);
        $this->assertCount(3, $second['data']);
        $this->assertSame((string) $made[4]['name'], $second['data'][0]['name']);

        $last = $this->tableData(['start' => 6, 'length' => 3, 'order[0][column]' => 0, 'order[0][dir]' => 'asc']);
        $this->assertCount(1, $last['data'], 'Halaman terakhir boleh lebih pendek.');

        // Offset di luar jangkauan -> kosong, bukan error.
        $beyond = $this->tableData(['start' => 999, 'length' => 10]);
        $this->assertCount(0, $beyond['data']);
    }

    public function testLengthDibatasi(): void
    {
        $adminId = $this->makeOwner();

        for ($i = 0; $i < 12; $i++) {
            $this->makeApplication($adminId, 'App ' . $i);
        }

        $this->loginAs(['ADMIN'], false, $adminId);

        // length=-1 berarti "Semua" di UI; harus tetap dibatasi server,
        // kalau tidak satu request bisa menarik seluruh tabel.
        $this->assertLessThanOrEqual(100, count($this->tableData(['length' => -1])['data']));
        $this->assertLessThanOrEqual(100, count($this->tableData(['length' => 99999])['data']));
        $this->assertCount(12, $this->tableData(['length' => -1])['data'], 'Data saat ini masih di bawah batas.');
    }

    /* ---------------------------------------------------------------- *
     *  SORTING
     * ---------------------------------------------------------------- */

    public function testSortingMenurutNamaBekerja(): void
    {
        $adminId = $this->makeOwner();

        $alpha   = $this->makeApplication($adminId, 'Alpha')['application'];
        $bravo   = $this->makeApplication($adminId, 'Bravo')['application'];
        $charlie = $this->makeApplication($adminId, 'Charlie')['application'];

        $this->loginAs(['ADMIN'], false, $adminId);

        $asc = $this->tableData(['order[0][column]' => 0, 'order[0][dir]' => 'asc']);
        $this->assertSame(
            [(string) $alpha['name'], (string) $bravo['name'], (string) $charlie['name']],
            array_column($asc['data'], 'name')
        );

        $desc = $this->tableData(['order[0][column]' => 0, 'order[0][dir]' => 'desc']);
        $this->assertSame(
            [(string) $charlie['name'], (string) $bravo['name'], (string) $alpha['name']],
            array_column($desc['data'], 'name')
        );
    }

    public function testIndexKolomDiLuarWhitelistDiabaikan(): void
    {
        $adminId = $this->makeOwner();

        $this->makeApplication($adminId, 'Charlie');
        $this->makeApplication($adminId, 'Alpha');

        $this->loginAs(['ADMIN'], false, $adminId);

        // Kolom 2 (jumlah key) dan 5 (aksi) tidak ada di peta. Index lain
        // yang bukan kolom tabel juga harus diabaikan.
        foreach ([2, 5, 99, -1] as $column) {
            $payload = $this->tableData(['order[0][column]' => $column, 'order[0][dir]' => 'desc']);
            $this->assertCount(2, $payload['data'], 'Index kolom ' . $column . ' seharusnya tidak error.');
        }
    }

    public function testArahSortirLainDiabaikan(): void
    {
        $adminId = $this->makeOwner();

        $charlie = $this->makeApplication($adminId, 'Charlie')['application'];
        $alpha   = $this->makeApplication($adminId, 'Alpha')['application'];

        // Nama dari factory diberi akhiran unik, jadi yang compared adalah
        // nilai aslinya dari database, bukan string tebakan.
        $expected = ((string) $alpha['name'] < (string) $charlie['name'])
            ? (string) $alpha['name']
            : (string) $charlie['name'];

        $this->loginAs(['ADMIN'], false, $adminId);

        foreach (['asc; DROP TABLE api_applications;--', 'RANDOM()', '', 'ASC'] as $dir) {
            $payload = $this->tableData(['order[0][column]' => 0, 'order[0][dir]' => $dir]);
            $this->assertSame($expected, $payload['data'][0]['name'], 'Arah "' . $dir . '" tidak seharusnya dipakai.');
        }

        // Tabelnya utuh.
        $this->assertSame(2, $this->tableData()['recordsTotal']);
    }

    /* ---------------------------------------------------------------- *
     *  JALUR JSON UNTUK MODAL
     * ---------------------------------------------------------------- */

    public function testSimpanAjaxMembalasJsonDenganTokenBaru(): void
    {
        $adminId = $this->makeOwner();

        $this->loginAs(['ADMIN'], false, $adminId);

        $response = $this->postAjax('application/create', [
            'name'        => 'Modul Via Ajax',
            'code'        => '',
            'description' => 'Dibuat dari modal',
        ]);

        $response->assertOK();
        $payload = $this->json($response);

        $this->assertTrue($payload['ok']);
        $this->assertStringContainsString('Modul Via Ajax', (string) $payload['message']);
        $this->assertArrayHasKey('csrf', $payload, 'Respons harus membawa token CSRF terbaru.');
        $this->assertNotSame('', (string) $payload['csrf']['value']);
        $this->assertNotEmpty((string) $payload['csrf']['name']);

        $created = (new ApiApplicationModel())->findByName('Modul Via Ajax');
        $this->assertNotNull($created, 'Application harus benar-benar tersimpan.');
        $this->assertSame($adminId, (int) $created['created_by']);
    }

    public function testTokenAjaxMenjadiBerbedaSetelahPostSukses(): void
    {
        $adminId = $this->makeOwner();

        $this->loginAs(['ADMIN'], false, $adminId);

        $before = (string) service('security')->getHash();
        $first  = $this->json($this->postAjax('application/create', ['name' => 'Ajax Satu']));
        $second = $this->json($this->postAjax('application/create', ['name' => 'Ajax Dua']));

        $this->assertNotSame($first['csrf']['value'], $second['csrf']['value'],
            'Token harus berubah tiap POST sukses; kalau tidak, JavaScript akan mengirim token basi.');
        $this->assertNotSame($before, $first['csrf']['value']);
    }

    public function testValidasiGagalMembalas422DenganDetail(): void
    {
        $this->loginAs(['ADMIN'], false, $this->makeOwner());

        $response = $this->postAjax('application/create', [
            'name' => 'ab', // terlalu pendek
            'code' => 'KodeHurufBesar',
        ]);

        $response->assertStatus(422);
        $payload = $this->json($response);

        $this->assertFalse($payload['ok']);
        $this->assertArrayHasKey('name', $payload['errors']);
        $this->assertArrayHasKey('code', $payload['errors']);
        $this->assertArrayHasKey('csrf', $payload);
    }

    public function testUbahAjaxMembalasJsonDanMenyimpan(): void
    {
        $adminId = $this->makeOwner();
        $made    = $this->makeApplication($adminId, 'Nama Lama');
        $id      = (int) $made['application']['id'];

        $this->loginAs(['ADMIN'], false, $adminId);

        $response = $this->postAjax('application/edit/' . $id, [
            'name'        => 'Nama Baru',
            'code'        => $made['application']['code'],
            'description' => 'Diubah dari modal',
        ]);

        $response->assertOK();
        $payload = $this->json($response);

        $this->assertTrue($payload['ok']);
        $this->assertArrayHasKey('csrf', $payload);

        $stored = (new ApiApplicationModel())->find($id);
        $this->assertSame('Nama Baru', (string) $stored['name']);
        $this->assertSame('Diubah dari modal', (string) $stored['description']);
    }

    public function testAjaxKeMilikOrangLainDitolak(): void
    {
        $milik     = $this->makeOwner();
        $penyerang = $this->makeOwner();
        $made      = $this->makeApplication($milik, 'Milik Orang Lain');
        $id        = (int) $made['application']['id'];

        $this->loginAs(['ADMIN'], false, $penyerang);

        $response = $this->postAjax('application/edit/' . $id, ['name' => 'Dibajak']);
        $payload  = $this->json($response);

        $this->assertFalse($payload['ok']);
        $this->assertSame((string) $made['application']['name'], (string) (new ApiApplicationModel())->find($id)['name']);
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * @param array<string,string|int> $query
     *
     * @return array<string,mixed>
     */
    private function tableData(array $query = []): array
    {
        $uri = 'application/data' . ($query === [] ? '' : '?' . http_build_query($query));

        $response = $this->get($uri);
        $response->assertOK();

        return $this->json($response);
    }

    private function loginAsSuper(int $userId): void
    {
        $this->loginAs(['SUPER_ADMIN'], true, $userId);
    }

    /**
     * POST AJAX dengan token CSRF sungguhan.
     *
     * Token dibaca dari cookie, jadi cookie-nya ikut disiapkan — sama
     * seperti yang dilakukan browser.
     *
     * Catatan: FeatureTestTrait::post() hanya menerima dua argumen, jadi
     * header harus dipasang lewat withHeaders() — itu yang dibaca
     * setupHeaders() untuk setiap request berikutnya.
     *
     * @param array<string,mixed> $params
     */
    private function postAjax(string $uri, array $params = []): TestResponse
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        $_COOKIE[$security->getCookieName()] = $token;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);

        $response = $this->post($uri, $params + [$security->getTokenName() => $token]);

        // Jangan bocorkan header ini ke request biasa berikutnya.
        $this->withHeaders(['X-Requested-With' => null]);

        return $response;
    }

    /**
     * Body JSON mentah.
     *
     * Sengaja lewat response(), bukan getBody(): TestResponse::getBody()
     * diteruskan ke DOMParser yang memakai DOMDocument::loadHTML(), dan
     * itu membungkus JSON dalam amplop HTML 4.0 sehingga json_decode()
     * selalu gagal.
     *
     * @return array<string,mixed>
     */
    private function json(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->response()->getBody(), true);

        $this->assertIsArray($decoded, 'Respons harus berupa JSON yang valid.');

        return $decoded;
    }

    /**
     * @param list<string> $roles
     */
    private function loginAs(array $roles, bool $isSuper, int $userId): void
    {
        $this->withSession([
            'logged_in'          => true,
            'access_user_id'     => $userId,
            'access_usergate_id' => 'uuid-table-' . $userId,
            'access_username'    => 'pengguna-' . $userId,
            'access_email'       => 'pengguna-' . $userId . '@example.com',
            'access_full_name'   => 'Pengguna Uji ' . $userId,
            'access_roles'       => $roles,
            'access_is_super'    => $isSuper,

            'ug_access_token'     => 'token-uji',
            'ug_access_expires_at' => time() + 3600,
        ]);
    }
}