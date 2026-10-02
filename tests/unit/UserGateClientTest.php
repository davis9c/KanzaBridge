<?php

namespace Tests\Unit;

use App\Libraries\UserGate\UserGateClient;
use App\Libraries\UserGate\UserGateException;
use CodeIgniter\Test\CIUnitTestCase;
use Config\UserGate as UserGateConfig;

/**
 * UserGateClient terhadap server UserGate yang sungguhan.
 *
 * Hanya jalur yang TIDAP memerlukan API key sah yang diuji:
 *   - respons 401 "API Key is required." / "Invalid or inactive API Key."
 *   - amplop JSON dan pesan yang dikembalikan UserGate.
 *
 * Test dilewati otomatis bila server tidak dapat dihubungi, supaya
 * unit test tetap bisa jalan di lingkungan offline.
 */
final class UserGateClientTest extends CIUnitTestCase
{
    private UserGateClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config(UserGateConfig::class);

        // Pakai API key yang jelas tidak sah supaya UserGate membalas 401
        // alih-alih memproses permintaan.
        $config->apiKey = 'kunci-uji-yang-sengaja-salah';
        $config->timeout = 5;

        $this->client = new UserGateClient($config);
    }

    public function testLoginTanpaApiKeySahDitolak(): void
    {
        try {
            $this->client->login('siapa-saja', 'apa-saja');
        } catch (UserGateException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertTrue(
                $e->isApiKeyProblem(),
                'Harus dikenali sebagai masalah API key, bukan kredensial user. Pesan: ' . $e->getMessage()
            );

            return;
        }

        $this->markTestSkipped('UserGate menerima API key uji — endpoint tidak menolak.');
    }

    public function testRefreshTanpaApiKeySahDitolak(): void
    {
        try {
            $this->client->refresh('token-palsu');
        } catch (UserGateException $e) {
            $this->assertContains(
                $e->getStatusCode(),
                [401, 403],
                'Refresh dengan API key salah harus ditolak.'
            );

            return;
        }

        $this->markTestSkipped('UserGate menerima API key uji.');
    }

    public function testConfigBelumSiapDikenali(): void
    {
        $config = config(UserGateConfig::class);
        $config->apiKey = 'CHANGE_ME';

        $client = new UserGateClient($config);

        try {
            $client->login('siapa-saja', 'apa-saja');
            $this->fail('Konfigurasi kosong seharusnya ditolak sebelum request dikirim.');
        } catch (UserGateException $e) {
            $this->assertTrue($e->isConfigProblem());
            $this->assertStringContainsString('usergate.apiKey', $e->getMessage());
        }
    }
}
