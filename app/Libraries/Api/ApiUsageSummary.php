<?php

namespace App\Libraries\Api;

use App\Models\Access\ApiKeyModel;

/**
 * Angka-angka ringkas untuk kartu dashboard dari daftar key.
 *
 * Dipisah dari controller supaya dashboard (SysDashboard) dan halaman
 * penggunaan bisa memakai perhitungan yang sama tanpa menduplikasi.
 *
 * Dihitung dari baris yang sudah diambil, bukan query terpisah: jumlahnya
 * sedikit, jadi tidak perlu query tambahan.
 *
 * Catatan akurasi: kolom `last_used_at` hanya diisi paling sering sekali
 * per menit per key (lihat ApiKeyService::touchUsage()), jadi ini snapshot
 * posisi pemakaian key — bukan penghitung request dan bukan grafik tren.
 */
final class ApiUsageSummary
{
    /**
     * @param  list<array<string,mixed>> $keys
     * @return array{total:int, active:int, revoked:int, expired:int, unused:int, applications:int, noLimit:int}
     */
    public function compute(array $keys): array
    {
        $summary = [
            'total'        => 0,
            'active'       => 0,
            'revoked'      => 0,
            'expired'      => 0,
            'unused'       => 0,
            'applications' => 0,
            'noLimit'      => 0,
        ];

        $applications = [];
        $expired      = 0;
        $service      = new ApiKeyService();

        foreach ($keys as $key) {
            $summary['total']++;
            $applications[(int) $key['application_id']] = true;

            $active = (string) $key['status'] === ApiKeyModel::STATUS_ACTIVE;

            $summary[$active ? 'active' : 'revoked']++;

            if (empty($key['last_used_at'])) {
                $summary['unused']++;
            }

            if ($active && (int) ($key['rate_limit_per_minute'] ?? 0) === 0) {
                $summary['noLimit']++;
            }

            if ($service->isExpired($key)) {
                $expired++;
            }
        }

        $summary['applications'] = count($applications);
        $summary['expired']      = $expired;

        return $summary;
    }
}