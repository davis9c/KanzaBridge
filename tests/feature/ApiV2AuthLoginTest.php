<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Gerbang dan validasi `POST /api/v2/auth/login`.
 *
 * Jalur sukses 200 sengaja diskip bila tidak ada kredensial uji — password
 * asli `sik_beta.user` tidak bisa dipalsukan (AES_ENCRYPT dengan secret di
 * `.env`), jadi test ini tidak menulis jalur 200 palsu.
 */
final class ApiV2AuthLoginTest extends CIUnitTestCase
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
     *  GERBANG API KEY
     * ---------------------------------------------------------------- */

    public function testTanpaKeyDitolak401(): void
    {
        $this->post('api/v2/auth/login', ['json' => ['user_id' => 'x', 'password' => 'y']])
            ->assertStatus(401);
    }

    public function testKeyTanpaScopeAuthLoginDitolak403(): void
    {
        $key = $this->makeApiKey(['meta.read']);

        $this->withHeaders(['X-API-Key' => $key['plain']]);

        $response = $this->post(
            'api/v2/auth/login',
            ['json' => ['user_id' => 'x', 'password' => 'y']]
        );

        $response->assertStatus(403);

        $payload = $this->payload($response);

        $this->assertSame('auth.login', $payload['required_scope']);
    }

    /* ---------------------------------------------------------------- *
     *  VALIDASI BODY
     * ---------------------------------------------------------------- */

    public function testBodyKosongDitolak400(): void
    {
        $key = $this->makeApiKey(['auth.login']);

        $this->withHeaders(['X-API-Key' => $key['plain']]);

        $this->post('api/v2/auth/login')->assertStatus(400);
    }

    public function testFieldHilangDitolak400(): void
    {
        $key = $this->makeApiKey(['auth.login']);

        $this->withHeaders(['X-API-Key' => $key['plain']]);

        $this->withBodyFormat('json');
        $this->withBody(json_encode(['user_id' => 'x']));

        $this->post('api/v2/auth/login')->assertStatus(400);
    }

    /* ---------------------------------------------------------------- *
     *  KREDENSIAL SALAH
     * ---------------------------------------------------------------- */

    public function testKredensialSalahDitolakDenganPesanGenerik(): void
    {
        $key = $this->makeApiKey(['auth.login']);

        $this->withHeaders(['X-API-Key' => $key['plain']]);
        $this->withBodyFormat('json');
        $this->withBody(json_encode(['user_id' => 'tidak-ada', 'password' => 'salah']));

        $response = $this->post('api/v2/auth/login');

        $response->assertStatus(401);

        $payload = $this->payload($response);

        // Pesan harus generik: tidak boleh membedakan "user tidak ada"
        // dengan "password salah".
        $this->assertSame('User ID atau password salah', $payload['message']);
        // Tidak boleh menyisakan bentuk apa pun yang bisa dipakai menebak.
        $this->assertArrayNotHasKey('data', $payload);
    }

    /* ---------------------------------------------------------------- *
     *  RATE LIMIT PER KEY
     * ---------------------------------------------------------------- */

    public function testMelebihiRateLimitKeyDijawab429(): void
    {
        // §6.3 minta throttle per user_id, tapi keputusan saat perancangan
        // adalah memakai rule yang sama dengan API key lain. Test ini
        // mengunci perilaku itu: batas yang dipakai adalah
        // `rate_limit_per_minute` milik key.
        $key = $this->makeApiKey(['auth.login'], ['rate_limit_per_minute' => 2]);

        $this->withHeaders(['X-API-Key' => $key['plain']]);
        $this->withBodyFormat('json');

        $this->withBody(json_encode(['user_id' => 'tidak-ada', 'password' => 'salah']));
        $this->post('api/v2/auth/login')->assertStatus(401);

        $this->withBody(json_encode(['user_id' => 'tidak-ada', 'password' => 'salah']));
        $this->post('api/v2/auth/login')->assertStatus(401);

        $this->withBody(json_encode(['user_id' => 'tidak-ada', 'password' => 'salah']));
        $response = $this->post('api/v2/auth/login');

        $response->assertStatus(429);
        $this->assertGreaterThan(0, (int) $response->response()->getHeaderLine('Retry-After'));
    }

    /* ---------------------------------------------------------------- *
     *  SUKSES (OPSIONAL)
     * ---------------------------------------------------------------- */

    public function testSuksesMengembalikanProfilPegawaiTanpaToken(): void
    {
        $userId   = env('API_V2_TEST_USER_ID', '');
        $password = env('API_V2_TEST_PASSWORD', '');

        if ($userId === '' || $password === '') {
            $this->markTestSkipped(
                'Butuh kredensial sik_beta: set API_V2_TEST_USER_ID dan API_V2_TEST_PASSWORD.'
            );
        }

        $key = $this->makeApiKey(['auth.login']);

        $this->withHeaders(['X-API-Key' => $key['plain']]);
        $this->withBodyFormat('json');
        $this->withBody(json_encode(['user_id' => $userId, 'password' => $password]));

        $response = $this->post('api/v2/auth/login');

        $response->assertOK();

        $payload = $this->payload($response);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pegawai_id', $payload['data']);
        $this->assertArrayHasKey('nik', $payload['data']);
        $this->assertArrayHasKey('nama', $payload['data']);
        $this->assertArrayHasKey('kd_jabatan', $payload['data']);
        $this->assertArrayHasKey('jabatan', $payload['data']);

        // Kontrak penting: TIDAK ada token, TIDAK ada expires, TIDAK ada role.
        $this->assertArrayNotHasKey('token', $payload['data']);
        $this->assertArrayNotHasKey('expires', $payload['data']);
        $this->assertArrayNotHasKey('role', $payload['data']);
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * @return array<string,mixed>
     */
    private function payload(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->getJSON(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
