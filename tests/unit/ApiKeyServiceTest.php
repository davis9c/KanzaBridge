<?php

namespace Tests\Unit;

use App\Libraries\Api\ApiKeyService;
use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Aturan pembuatan, pencocokan, dan pembatasan API key.
 *
 * Yang diuji di sini murni lapisan layanan: bukan HTTP, bukan UI. Test
 * feature yang memanggil endpoint-nya ada di ApiV2AccessTest.
 */
final class ApiKeyServiceTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
    use LocalAccessDatabaseTrait;

    private ApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLocalAccessDatabase();

        $this->service = new ApiKeyService();
    }

    protected function tearDown(): void
    {
        $this->tearDownLocalAccessDatabase();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- *
     *  PEMBUATAN
     * ---------------------------------------------------------------- */

    public function testKeyYangDihasilkanMemakaiPrefixDanBentukYangDitetapkan(): void
    {
        $config = config('Api');
        $made   = $this->service->generate();

        $this->assertStringStartsWith($config->v2KeyPrefix, $made['plain']);
        $this->assertSame(
            strlen($config->v2KeyPrefix) + ($config->v2KeyBytes * 2),
            strlen($made['plain']),
            'Panjang key harus prefix + 2 x jumlah byte acak.'
        );
        $this->assertSame(hash('sha256', $made['plain']), $made['hash']);
        $this->assertSame(substr($made['plain'], 0, $config->v2KeyPrefixLength), $made['prefix']);
    }

    public function testDuaKeyTidakSama(): void
    {
        $this->assertNotSame($this->service->generate()['hash'], $this->service->generate()['hash']);
    }

    public function testLooksLikeKeyMenolakBentukSalah(): void
    {
        $good = $this->service->generate()['plain'];

        $this->assertTrue($this->service->looksLikeKey($good));
        $this->assertFalse($this->service->looksLikeKey(''));
        $this->assertFalse($this->service->looksLikeKey('bukan-key-kanza'));
        $this->assertFalse($this->service->looksLikeKey($good . 'x'), 'Key yang digosok panjangnya bukan key.');
        $this->assertFalse(
            $this->service->looksLikeKey(config('Api')->v2KeyPrefix . str_repeat('z', 64)),
            'Key dengan badan non-heks bukan key.'
        );
    }

    /* ---------------------------------------------------------------- *
     *  PENCOCOKAN
     * ---------------------------------------------------------------- */

    public function testKeyYangBenarDitemukan(): void
    {
        $made = $this->makeApiKey(['meta.read']);

        $found = $this->service->findByPlainKey($made['plain']);

        $this->assertTrue($found['ok']);
        $this->assertNull($found['failure']);
        $this->assertSame((int) $made['key']['id'], (int) $found['key']['id']);
    }

    public function testKeyYangTidakDikenalDitolak(): void
    {
        $found = $this->service->findByPlainKey($this->service->generate()['plain']);

        $this->assertFalse($found['ok']);
        $this->assertSame(ApiKeyService::FAILURE_UNKNOWN, $found['failure']);
    }

    public function testKeyDicabutDitolak(): void
    {
        $made = $this->makeApiKey();

        (new ApiKeyModel())->setStatus((int) $made['key']['id'], ApiKeyModel::STATUS_REVOKED);

        $found = $this->service->findByPlainKey($made['plain']);

        $this->assertFalse($found['ok']);
        $this->assertSame(ApiKeyService::FAILURE_REVOKED, $found['failure']);
    }

    public function testKeyKedaluwarsaDitolak(): void
    {
        $made = $this->makeApiKey([], [
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $found = $this->service->findByPlainKey($made['plain']);

        $this->assertFalse($found['ok']);
        $this->assertSame(ApiKeyService::FAILURE_EXPIRED, $found['failure']);
    }

    public function testKeyTanpaMasaBerlakuSelaluHidup(): void
    {
        $made = $this->makeApiKey();

        $this->assertNull($made['key']['expires_at']);
        $this->assertTrue($this->service->findByPlainKey($made['plain'])['ok']);
    }

    public function testTanggalKedaluwarsaYangTidakTerbacaDianggapHabis(): void
    {
        // Data rusak tidak boleh diperlakukan sebagai "selamanya".
        $made = $this->makeApiKey();

        (new ApiKeyModel())->update((int) $made['key']['id'], ['expires_at' => 'bukan tanggal']);

        $this->assertTrue($this->service->isExpired((array) $this->makeKeyRow($made)));
    }

    /* ---------------------------------------------------------------- *
     *  SCOPE
     * ---------------------------------------------------------------- */

    public function testScopeKeyHanyaYangDisimpan(): void
    {
        $made = $this->makeApiKey(['meta.read', 'jabatan.read']);

        $scopes = $this->service->scopesFor((int) $made['key']['id']);

        sort($scopes);

        $this->assertSame(['jabatan.read', 'meta.read'], $scopes);
    }

    public function testSyncScopeMenggantiSeluruhScopeLama(): void
    {
        $made  = $this->makeApiKey(['meta.read', 'jabatan.read']);
        $scopes = new ApiKeyScopeModel();

        $scopes->sync((int) $made['key']['id'], ['pegawai.read']);

        $this->assertSame(['pegawai.read'], $this->service->scopesFor((int) $made['key']['id']));
    }

    public function testSyncScopeDenganDaftarKosongMelepasSemuaEndpoint(): void
    {
        $made  = $this->makeApiKey(['meta.read']);
        $scopes = new ApiKeyScopeModel();

        $scopes->sync((int) $made['key']['id'], []);

        $this->assertSame([], $this->service->scopesFor((int) $made['key']['id']));
    }

    /* ---------------------------------------------------------------- *
     *  RATE LIMIT
     * ---------------------------------------------------------------- */

    public function testTanpaBatasTidakDihitung(): void
    {
        $made  = $this->makeApiKey();
        $key   = (array) $made['key'];
        $key['rate_limit_per_minute'] = 0;

        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($this->service->hitRateLimit($key)['allowed']);
        }
    }

    public function testBatasDilampauiMenolakRequestBerikutnya(): void
    {
        $made  = $this->makeApiKey();
        $key   = (array) $made['key'];
        $key['rate_limit_per_minute'] = 3;

        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->service->hitRateLimit($key);
        }

        $this->assertTrue($results[0]['allowed']);
        $this->assertTrue($results[1]['allowed']);
        $this->assertTrue($results[2]['allowed']);
        $this->assertFalse($results[3]['allowed']);
        $this->assertFalse($results[4]['allowed']);

        $this->assertSame(3, $results[2]['limit']);
        $this->assertGreaterThan(0, $results[3]['retryAfter']);
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * @param  array{key:array<string,mixed>} $made
     * @return array<string,mixed>
     */
    private function makeKeyRow(array $made): array
    {
        return (array) (new ApiKeyModel())->find((int) $made['key']['id']);
    }
}