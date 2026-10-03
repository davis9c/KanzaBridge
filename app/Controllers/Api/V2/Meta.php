<?php

namespace App\Controllers\Api\V2;

use App\Models\Access\ApiApplicationModel;

/**
 * Endpoint meta API V2.
 *
 * Ada supaya integrator bisa memeriksa key-nya tanpa menyentuh data
 * SIMRS sama sekali: nama application, key yang dipakai, dan endpoint apa
 * saja yang boleh diakses. Dipakai saat pemasangan integrasi untuk
 * memastikan credential dan permission sudah benar sebelum alur data
 * dinyalakan.
 */
class Meta extends BaseApiV2Controller
{
    private ApiApplicationModel $applications;

    public function __construct()
    {
        $this->applications = new ApiApplicationModel();
    }

    /**
     * GET api/v2/me
     */
    public function me()
    {
        $apiKey = $this->requireKey();

        if ($apiKey instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $apiKey;
        }

        $application = $this->applications->find((int) $apiKey['application_id']);

        $catalog = config(\Config\ApiScope::class);
        $granted = [];

        foreach ((array) $apiKey['scopes'] as $scope) {
            $meta = $catalog->meta((string) $scope);

            $granted[] = [
                'scope' => (string) $scope,
                'label' => $meta['label'] ?? $scope,
                'method' => $meta['method'] ?? '',
                'path'  => $meta['path'] ?? '',
            ];
        }

        return $this->respondSuccess([
            'data' => [
                'application' => [
                    'id'   => (int) $apiKey['application_id'],
                    'name' => (string) ($application['name'] ?? '-'),
                    'code' => (string) ($application['code'] ?? '-'),
                ],
                'key' => [
                    'id'     => (int) $apiKey['key_id'],
                    'label'  => (string) $apiKey['label'],
                    'prefix' => (string) $apiKey['key_prefix'],
                ],
                'api_version' => $catalog->version,
                'granted'     => $granted,
            ],
        ], 'Informasi API key');
    }
}