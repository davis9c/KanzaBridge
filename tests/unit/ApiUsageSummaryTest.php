<?php

namespace Tests\Unit;

use App\Libraries\Api\ApiUsageSummary;
use App\Models\Access\ApiKeyModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Perhitungan kartu ringkasan dashboard.
 *
 * Murni fungsi murni dari array baris key — tanpa database, tanpa HTTP.
 * Yang molybdenum di sini adalah hitungannya sendiri supaya test feature
 * (ApiUsagePageTest) cukup memeriksa bahwa angkanya tampil di halaman.
 */
final class ApiUsageSummaryTest extends CIUnitTestCase
{
    private ApiUsageSummary $summary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->summary = new ApiUsageSummary();
    }

    public function testDaftarKosongSemuaNol(): void
    {
        $result = $this->summary->compute([]);

        foreach (['total', 'active', 'revoked', 'expired', 'unused', 'applications', 'noLimit'] as $field) {
            $this->assertSame(0, $result[$field], 'Field "' . $field . '" seharusnya 0 untuk daftar kosong.');
        }
    }

    public function testMenghitungStatusDanPemakaian(): void
    {
        $result = $this->summary->compute([
            $this->key(['application_id' => 1, 'status' => 'ACTIVE', 'last_used_at' => '2026-01-01 10:00:00']),
            $this->key(['application_id' => 1, 'status' => 'ACTIVE', 'last_used_at' => null]),
            $this->key(['application_id' => 2, 'status' => 'REVOKED', 'last_used_at' => null]),
        ]);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['active']);
        $this->assertSame(1, $result['revoked']);
        $this->assertSame(2, $result['unused'], 'Key tanpa last_used_at dihitung belum pernah dipakai.');

        // Dua key di application yang sama hanya dihitung satu application.
        $this->assertSame(2, $result['applications']);
    }

    public function testKeyKedaluwarsaDihitungTerpisahDariStatus(): void
    {
        $result = $this->summary->compute([
            // Status masih ACTIVE tapi sudah lewat tanggalnya.
            $this->key(['status' => 'ACTIVE', 'expires_at' => '2020-01-01 00:00:00']),
            $this->key(['status' => 'REVOKED', 'expires_at' => '2020-01-01 00:00:00']),
            // Kedaluwarsa di masa depan -> belum kedaluwarsa.
            $this->key(['status' => 'ACTIVE', 'expires_at' => '2099-01-01 00:00:00']),
        ]);

        $this->assertSame(3, $result['active'] + $result['revoked']);
        $this->assertSame(2, $result['expired'], 'Kedaluwarsa dihitung terpisah dari status key.');
    }

    public function testKeyTanpaBatasDihitungHanyaYangAktif(): void
    {
        $result = $this->summary->compute([
            // Aktif tanpa batas -> dihitung.
            $this->key(['status' => 'ACTIVE', 'rate_limit_per_minute' => 0]),
            // Dicabut tanpa batas -> tidak dihitung, sudah tidak berlaku.
            $this->key(['status' => 'REVOKED', 'rate_limit_per_minute' => 0]),
            // Aktif dengan batas -> tidak dihitung.
            $this->key(['status' => 'ACTIVE', 'rate_limit_per_minute' => 30]),
        ]);

        $this->assertSame(1, $result['noLimit']);
    }

    public function testNilaiHilangDianggapTanpaBatas(): void
    {
        // Baris dari DB bisa punya kolom null; tidak boleh jadi error.
        $result = $this->summary->compute([
            [
                'id'                  => 1,
                'application_id'      => 1,
                'status'              => 'ACTIVE',
                'last_used_at'        => null,
                'expires_at'          => null,
                'rate_limit_per_minute' => null,
            ],
        ]);

        $this->assertSame(1, $result['noLimit']);
        $this->assertSame(0, $result['expired']);
    }

    /**
     * @param  array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function key(array $overrides): array
    {
        return $overrides + [
            'id'                    => 1,
            'application_id'        => 1,
            'status'                => ApiKeyModel::STATUS_ACTIVE,
            'last_used_at'          => null,
            'expires_at'            => null,
            'rate_limit_per_minute' => 0,
        ];
    }
}