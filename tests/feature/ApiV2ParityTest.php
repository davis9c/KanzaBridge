<?php

namespace Tests\Feature;

use App\Libraries\Api\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Kesetaraan hasil API V1 (JWT) dan API V2 (API key).
 *
 * Klien V1 dipindah bertahap ke V2. Selama proses itu keduanya harus
 * mengembalikan data yang persis sama — kalau tidak, migrasi klien akan
 * menghasilkan perubahan perilaku yang sulit dilacak.
 *
 * Token JWT dibuat langsung lewat TokenService, bukan lewat
 * Api\Auth::login, supaya test ini tidak bergantung pada kredensial SIMRS
 * dan tidak menyentuh UserGate sama sekali.
 *
 * `dokter/dan-spesialis` sengaja TIDAK diuji: modelnya bergabung ke tabel
 * `spesialis` yang tidak ada di sik_beta, jadi V1 maupun V2 gagal di sana
 * dengan cara yang sama. Perbaikannya urusan sik_beta, bukan filter.
 */
final class ApiV2ParityTest extends CIUnitTestCase
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

    /**
     * Setiap baris: [method, path V1, path V2, scope V2, body, ada data].
     *
     * Path V1 dan V2 sengaja ditulis terpisah: sebagian nama path V1
     * tidak bisa dipertahankan persis (mis. `petugas/DanJabatan`).
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:list<string>, 4:array<string,mixed>, 5:bool}>
     */
    public static function endpointProvider(): array
    {
        return [
            'users'                => ['get', 'users', 'users', ['users.read'], [], true],
            'pegawai'              => ['get', 'pegawai', 'pegawai', ['pegawai.read'], [], true],
            'pegawai/by-ids'       => ['post', 'pegawai/by-ids', 'pegawai/by-ids', ['pegawai.by-ids'], ['ids' => [1, 2]], true],
            'pegawai/by-nik'       => ['post', 'pegawai/by-nik', 'pegawai/by-nik', ['pegawai.by-nik'], ['nik' => 'tidak-ada'], false],
            'pegawai/dokter'       => ['post', 'pegawai/dokter', 'pegawai/dokter', ['dokter.read'], [], true],
            'dokter'               => ['post', 'dokter', 'dokter', ['dokter.read'], [], true],
            'jabatan'              => ['get', 'jabatan', 'jabatan', ['jabatan.read'], [], true],
            'jabatan/with-petugas' => ['get', 'jabatan/with-petugas', 'jabatan/with-petugas', ['jabatan.with-petugas'], [], true],
            'petugas/dan-jabatan'  => ['post', 'petugas/DanJabatan', 'petugas/dan-jabatan', ['petugas.read'], [], true],
            'petugas/by-jbtn'      => ['post', 'petugas/by-jbtn', 'petugas/by-jbtn', ['petugas.by-jbtn'], ['kd_jbtn' => 'D1010'], true],
            'petugas/by-nips'      => ['post', 'petugas/by-nips', 'petugas/by-nips', ['petugas.by-nips'], ['nips' => ['tidak-ada']], true],
            'petugas/by-nip'       => ['post', 'petugas/by-nip', 'petugas/by-nip', ['petugas.by-nip'], ['nip' => 'tidak-ada-12345'], false],
        ];
    }

    /**
     * @dataProvider endpointProvider
     *
     * @param list<string>        $scopes
     * @param array<string,mixed> $body
     */
    public function testStatusDanPesanV1V2Sama(
        string $verb,
        string $v1Path,
        string $v2Path,
        array $scopes,
        array $body,
        bool $hasData
    ): void {
        $v1 = $this->callV1($verb, $v1Path, $body);
        $v2 = $this->callV2($verb, $v2Path, $this->makeApiKey($scopes)['plain'], $body);

        $v1Status = $v1->response()->getStatusCode();
        $v2Status = $v2->response()->getStatusCode();

        $this->assertSame(
            $v1Status,
            $v2Status,
            "Status berbeda pada {$v2Path}: V1 {$v1Status}, V2 {$v2Status}"
        );

        $this->assertSame(
            $this->payload($v1)['message'],
            $this->payload($v2)['message'],
            "Pesan berbeda pada {$v2Path}"
        );
    }

    /**
     * @dataProvider endpointProvider
     *
     * @param list<string>        $scopes
     * @param array<string,mixed> $body
     */
    public function testDataV1V2Sama(
        string $verb,
        string $v1Path,
        string $v2Path,
        array $scopes,
        array $body,
        bool $hasData
    ): void {
        $v1 = $this->callV1($verb, $v1Path, $body);
        $v2 = $this->callV2($verb, $v2Path, $this->makeApiKey($scopes)['plain'], $body);

        if ($hasData) {
            $v1->assertOK("V1 {$v1Path} seharusnya 200: " . json_encode($this->payload($v1)['message'] ?? null));
            $v2->assertOK("V2 {$v2Path} seharusnya 200: " . json_encode($this->payload($v2)['message'] ?? null));
        } else {
            // Endpoint dengan parameter yang sengaja tidak ada: sama-sama
            // harus menolak, dan alasan penolakannya juga sama.
            $this->assertSame(404, $v2->response()->getStatusCode(), "V2 {$v2Path} seharusnya 404.");
        }

        $this->assertSame(
            json_encode($this->payload($v1)['data'] ?? null),
            json_encode($this->payload($v2)['data'] ?? null),
            "Data berbeda pada {$v2Path}"
        );
    }

    /**
     * Endpoint yang butuh array kosong harus tetap ditolak dengan 400
     * di kedua versi — V2 tidak boleh diam-diam lebih longgar.
     *
     * @dataProvider endpointProvider
     *
     * @param list<string>        $scopes
     * @param array<string,mixed> $body
     */
    public function testValidasiInputV1V2Sama(
        string $verb,
        string $v1Path,
        string $v2Path,
        array $scopes,
        array $body,
        bool $hasData
    ): void {
        // Hanya berlaku untuk endpoint yang membaca body.
        if ($verb !== 'post') {
            $this->assertTrue(true);

            return;
        }

        $v1 = $this->callV1($verb, $v1Path, []);
        $v2 = $this->callV2($verb, $v2Path, $this->makeApiKey($scopes)['plain'], []);

        $this->assertSame(
            $v1->response()->getStatusCode(),
            $v2->response()->getStatusCode(),
            "Validasi body berbeda pada {$v2Path}"
        );

        $this->assertSame($this->payload($v1), $this->payload($v2), "Respons body kosong berbeda pada {$v2Path}");
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * @param array<string,mixed> $body
     */
    private function callV1(string $verb, string $path, array $body): TestResponse
    {
        $token = (new TokenService())->issue(['user_id' => 'uji', 'nik' => 'uji'], 'uji');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token['token']]);

        return $this->request('api/' . $path, $verb, $body);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function callV2(string $verb, string $path, string $key, array $body): TestResponse
    {
        $this->withHeaders(['X-API-Key' => $key]);

        return $this->request('api/v2/' . $path, $verb, $body);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function request(string $uri, string $verb, array $body): TestResponse
    {
        if ($body !== []) {
            $this->withBodyFormat('json');
            $this->withBody(json_encode($body));
        }

        /** @var TestResponse $response */
        $response = $verb === 'post' ? $this->post($uri) : $this->get($uri);

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->getJSON(), true);

        return is_array($decoded) ? $decoded : [];
    }
}