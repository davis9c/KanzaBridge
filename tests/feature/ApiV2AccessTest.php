<?php

namespace Tests\Feature;

use App\Libraries\Api\TokenService;
use App\Models\Access\ApiKeyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Gerbang API key pada endpoint `/api/v2/*`.
 *
 * Diuji lewat request sungguhan supaya yang diperiksa benar-benar
 * hasil filter + controller, bukan hanya hasil pemanggilan service.
 *
 * Tabel api_* ikut dikosongkan lewat LocalAccessDatabaseTrait; sik_beta
 * hanya dibaca, tidak pernah ditulis test ini.
 */
final class ApiV2AccessTest extends CIUnitTestCase
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
     *  TANPA KEY
     * ---------------------------------------------------------------- */

    public function testTanpaHeaderApiKeyDitolak(): void
    {
        $response = $this->callV2('get', 'api/v2/jabatan');

        $response->assertStatus(401);
        $this->assertStringContainsString('API key', (string) $this->payload($response)['message']);
    }

    public function testHeaderKosongDitolak(): void
    {
        $this->callV2('get', 'api/v2/jabatan', '')->assertStatus(401);
    }

    public function testKeyTidakDikenalDitolak(): void
    {
        $this->callV2('get', 'api/v2/jabatan', $this->foreignKey())->assertStatus(401);
    }

    public function testPesan401TidakMembocorkanAlasan(): void
    {
        // Key dicabut, kedaluwarsa, dan tidak dikenal harus menjawab dengan
        // pesan yang sama, supaya klien tidak bisa menebak status sebuah
        // key hanya dari kata respons.
        $messages = [
            (string) $this->payload($this->callV2('get', 'api/v2/jabatan', $this->foreignKey()))['message'],
        ];

        $revoked = $this->makeApiKey(['jabatan.read'], ['status' => ApiKeyModel::STATUS_REVOKED]);
        $messages[] = (string) $this->payload(
            $this->callV2('get', 'api/v2/jabatan', $revoked['plain'])
        )['message'];

        $expired = $this->makeApiKey(['jabatan.read'], [
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);
        $messages[] = (string) $this->payload(
            $this->callV2('get', 'api/v2/jabatan', $expired['plain'])
        )['message'];

        $this->assertCount(
            1,
            array_unique($messages),
            'Semua kegagalan key harus memakai pesan yang sama: ' . implode(' | ', $messages)
        );
    }

    /* ---------------------------------------------------------------- *
     *  KEY HIDUP
     * ---------------------------------------------------------------- */

    public function testKeyDenganScopeYangCukupBerhasil(): void
    {
        $made     = $this->makeApiKey(['jabatan.read']);
        $response = $this->callV2('get', 'api/v2/jabatan', $made['plain']);

        $response->assertOK();

        $payload = $this->payload($response);

        $this->assertSame(200, $payload['status']);
        $this->assertIsArray($payload['data']);
    }

    public function testKeyDiterimaLewatHeaderAuthorization(): void
    {
        $made = $this->makeApiKey(['jabatan.read']);

        $this->withHeaders(['Authorization' => 'Bearer ' . $made['plain']]);

        $this->get('api/v2/jabatan')->assertOK();
    }

    public function testKeyDicabutTidakBisaDipakai(): void
    {
        $made = $this->makeApiKey(['jabatan.read'], ['status' => ApiKeyModel::STATUS_REVOKED]);

        $this->callV2('get', 'api/v2/jabatan', $made['plain'])->assertStatus(401);
    }

    public function testKeyKedaluwarsaTidakBisaDipakai(): void
    {
        $made = $this->makeApiKey(['jabatan.read'], [
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $this->callV2('get', 'api/v2/jabatan', $made['plain'])->assertStatus(401);
    }

    /* ---------------------------------------------------------------- *
     *  SCOPE
     * ---------------------------------------------------------------- */

    public function testKeyTanpaScopeEndpointDitolakDengan403(): void
    {
        $made     = $this->makeApiKey(['meta.read']);
        $response = $this->callV2('get', 'api/v2/jabatan', $made['plain']);

        $response->assertStatus(403);

        $payload = $this->payload($response);

        $this->assertSame('jabatan.read', $payload['required_scope']);
    }

    public function testSetiapEndpointDijagaScopeSendiri(): void
    {
        // Key dengan satu scope tidak boleh bisa membuka endpoint lain,
        // termasuk endpoint yang memang membaca dari tabel yang sama.
        $made = $this->makeApiKey(['pegawai.read']);

        $this->callV2('get', 'api/v2/pegawai', $made['plain'])->assertOK();

        foreach (['api/v2/me', 'api/v2/jabatan', 'api/v2/users'] as $uri) {
            $this->callV2('get', $uri, $made['plain'])
                ->assertStatus(403, "Endpoint {$uri} seharusnya butuh scope sendiri.");
        }
    }

    public function testDuaEndpointDokterBerbagiScope(): void
    {
        $made = $this->makeApiKey(['dokter.read']);

        $this->callV2('post', 'api/v2/dokter', $made['plain'])->assertOK();
        $this->callV2('post', 'api/v2/pegawai/dokter', $made['plain'])->assertOK();
    }

    public function testScopeUntukSatuEndpointTidakMembukaEndpointLain(): void
    {
        $made = $this->makeApiKey(['petugas.by-nip']);

        $this->callV2('post', 'api/v2/petugas/dan-jabatan', $made['plain'])
            ->assertStatus(403);

        // Endpoint yang diizinkan tetap jalan; NIP asal tidak ada, jadi
        // jawabannya 404 dari controller, bukan 403 dari filter.
        $this->callV2('post', 'api/v2/petugas/by-nip', $made['plain'], ['nip' => 'tidak-ada-12345'])
            ->assertStatus(404);
    }

    /* ---------------------------------------------------------------- *
     *  RATE LIMIT
     * ---------------------------------------------------------------- */

    public function testMelebihiRateLimitDijawab429(): void
    {
        $made = $this->makeApiKey(['jabatan.read'], ['rate_limit_per_minute' => 2]);

        $this->callV2('get', 'api/v2/jabatan', $made['plain'])->assertOK();
        $this->callV2('get', 'api/v2/jabatan', $made['plain'])->assertOK();

        $response = $this->callV2('get', 'api/v2/jabatan', $made['plain']);

        $response->assertStatus(429);

        $retryAfter = $response->response()->getHeaderLine('Retry-After');

        $this->assertGreaterThan(0, (int) $retryAfter, 'Respons 429 harus memberi Retry-After.');
        $this->assertStringContainsString(
            '2 request per menit',
            (string) $this->payload($response)['message']
        );
    }

    /* ---------------------------------------------------------------- *
     *  META
     * ---------------------------------------------------------------- */

    public function testEndpointMeMemaporkanHakAksesKey(): void
    {
        $made  = $this->makeApiKey(['meta.read', 'jabatan.read']);
        $data  = $this->payload($this->callV2('get', 'api/v2/me', $made['plain']))['data'];

        $this->assertSame((int) $made['application']['id'], (int) $data['application']['id']);
        $this->assertSame('v2', $data['api_version']);
        $this->assertCount(2, $data['granted']);

        $scopes = array_column($data['granted'], 'scope');
        sort($scopes);

        $this->assertSame(['jabatan.read', 'meta.read'], $scopes);
    }

    public function testEndpointMeTidakMembocorkanKey(): void
    {
        $made = $this->makeApiKey(['meta.read']);
        $body = (string) $this->callV2('get', 'api/v2/me', $made['plain'])->getBody();

        $this->assertStringNotContainsString($made['plain'], $body);
        $this->assertStringNotContainsString(hash('sha256', $made['plain']), $body);
    }

    /* ---------------------------------------------------------------- *
     *  V1 TETAP JALAN
     * ---------------------------------------------------------------- */

    public function testJwtTidakBisaDipakaiUntukEndpointV2(): void
    {
        $token = (new TokenService())->issue(['user_id' => 'uji', 'nik' => 'uji'], 'uji');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token['token']]);

        $this->get('api/v2/jabatan')->assertStatus(401);
        $this->get('api/jabatan')->assertOK();
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * Panggil endpoint V2 dengan header API key.
     *
     * Header tidak bisa dikirim per-panggilan lewat opsi `headers`,
     * jadi selalu lewat withHeaders(). Array kosong dipakai untuk
     * membersihkan header dari panggilan sebelumnya.
     *
     * @param array<string,mixed> $body Body JSON untuk request POST.
     */
    private function callV2(string $method, string $uri, string $key = '', array $body = []): TestResponse
    {
        $this->withHeaders($key === '' ? [] : ['X-API-Key' => $key]);

        if ($body !== []) {
            $this->withBodyFormat('json');
            $this->withBody(json_encode($body));
        }

        /** @var TestResponse $response */
        $response = $this->{$method}($uri);

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(TestResponse $response): array
    {
        $decoded = json_decode((string) $response->getJSON(), true);

        $this->assertIsArray($decoded, 'Respons bukan JSON yang valid.');

        return $decoded;
    }

    /**
     * Key dengan bentuk benar tapi tidak pernah terdaftar.
     */
    private function foreignKey(): string
    {
        return config('Api')->v2KeyPrefix . bin2hex(random_bytes(config('Api')->v2KeyBytes));
    }
}