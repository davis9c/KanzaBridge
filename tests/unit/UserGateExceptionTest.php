<?php

namespace Tests\Unit;

use App\Libraries\UserGate\UserGateException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Perilaku UserGateException terhadap berbagai respons UserGate.
 *
 * Ini yang menentukan pesan mana yang aman ditunjukkan ke user. Test ini
 * TIDAK melakukan HTTP request sungguhan — hanya memverifikasi
 * pengelompokan galat yang dipakai controller.
 */
final class UserGateExceptionTest extends CIUnitTestCase
{
    private function apiKeyException(): UserGateException
    {
        // Pesan UserGate yang mendokumentasikan kasus API key.
        return new UserGateException('API Key is required.', 401);
    }

    public function testApiKeyDitolak(): void
    {
        $e = $this->apiKeyException();

        $this->assertTrue($e->isApiKeyProblem());
        $this->assertTrue($e->isAuthFailure());
        $this->assertFalse($e->isRateLimited());
    }

    public function testKredensialSalahBukanMasalahKonfigurasi(): void
    {
        $e = new UserGateException('Invalid credentials.', 401);

        $this->assertFalse($e->isApiKeyProblem());
        $this->assertTrue($e->isAuthFailure());
    }

    public function testRateLimit(): void
    {
        $e = new UserGateException('', 429);

        $this->assertTrue($e->isRateLimited());
        $this->assertFalse($e->isServerProblem());
    }

    public function testDetailValidasi(): void
    {
        $e = new UserGateException('Validation failed.', 422, [
            'email' => 'The email field must contain a valid email address.',
        ]);

        $this->assertSame(422, $e->getStatusCode());
        $this->assertArrayHasKey('email', $e->getErrors());
    }

    public function testKonfigurasiLokalBelumSiap(): void
    {
        $e = new UserGateException(
            'API key UserGate belum dikonfigurasi.',
            0,
            [],
            UserGateException::KIND_CONFIG
        );

        // Harus dikenali sebagai masalah konfigurasi, BUKAN "server down",
        // supaya user diberi pesan yang tepat.
        $this->assertTrue($e->isConfigProblem());
        $this->assertFalse($e->isServerProblem());
    }

    public function testGagalJaringan(): void
    {
        $e = new UserGateException('Tidak dapat menghubungi UserGate: timeout', 0);

        $this->assertTrue($e->isServerProblem());
        $this->assertFalse($e->isConfigProblem());
        $this->assertFalse($e->isAuthFailure());
    }

    public function testServerError(): void
    {
        $e = new UserGateException('Internal Server Error', 500);

        $this->assertTrue($e->isServerProblem());
    }

    public function testKodeStatusTersimpan(): void
    {
        $e = new UserGateException('User not found.', 404);

        $this->assertSame(404, $e->getStatusCode());
        $this->assertFalse($e->isAuthFailure());
        $this->assertFalse($e->isServerProblem());
    }
}
