<?php

use App\Libraries\Api\ApiKeyService;

/**
 * Helper kosakata API V2 (kode scope + tampilan key).
 *
 * Di-autoload lewat Config\Autoload::$helpers.
 *
 * Catatan: helper di sini hanya dipakai lapisan API V2 dan halaman
 * "Application". Tidak ada satupun fungsi yang menyentuh lapisan web
 * (session/UserGate) maupun API V1 (JWT).
 */

if (! function_exists('api_scope_config')) {
    /**
     * Instance Config\ApiScope.
     */
    function api_scope_config(): Config\ApiScope
    {
        return config(Config\ApiScope::class);
    }
}

if (! function_exists('api_scope_catalog')) {
    /**
     * Katalog endpoint untuk checklist UI, sudah dikelompokkan.
     *
     * Setiap kelompok berisi ['label' => ..., 'endpoints' => scope => meta].
     *
     * @return array<string, array{label:string, endpoints:array<string, array<string,mixed>>}>
     */
    function api_scope_catalog(): array
    {
        $config  = api_scope_config();
        $catalog = [];

        // Kelompok yang tidak disebut di $groupOrder tetap ikut, supaya
        // endpoint dari kelompok baru tidak hilang diam-diam dari UI.
        $groups = array_merge(
            $config->groupOrder,
            array_values(array_unique(array_column($config->endpoints, 'group')))
        );

        foreach ($groups as $group) {
            $catalog[$group]['label'] = $config->groupLabels[$group]
                ?? ucfirst(strtolower(str_replace('_', ' ', $group)));

            $catalog[$group]['endpoints'] ??= [];
        }

        foreach ($config->endpoints as $scope => $meta) {
            $catalog[$meta['group']]['endpoints'][$scope] = $meta;
        }

        // Kelompok tanpa endpoint tidak perlu tampil.
        return array_filter($catalog, static fn (array $group): bool => $group['endpoints'] !== []);
    }
}

if (! function_exists('api_scope_label')) {
    /**
     * Label ramah untuk satu kode scope.
     */
    function api_scope_label(string $scope): string
    {
        return api_scope_config()->meta($scope)['label'] ?? $scope;
    }
}

if (! function_exists('api_scope_is_known')) {
    /**
     * Apakah kode scope ada di katalog.
     *
     * Dipakai controller untuk membuang scope tak dikenal yang sengaja
     * dikirim klien form.
     */
    function api_scope_is_known(string $scope): bool
    {
        return api_scope_config()->has($scope);
    }
}

if (! function_exists('api_scope_requested')) {
    /**
     * Scope valid yang diminta dari form.
     *
     * Kode yang tidak dikenal dibuang di sini, jadi controller tidak
     * pernah perlu memvalidasi ulang.
     *
     * @return list<string>
     */
    function api_scope_requested(mixed $posted): array
    {
        if (! is_array($posted)) {
            return [];
        }

        $scopes = [];

        foreach ($posted as $scope) {
            if (! is_string($scope)) {
                continue;
            }

            $scope = trim($scope);

            if ($scope !== '' && api_scope_is_known($scope)) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
    }
}

if (! function_exists('api_key_mask')) {
    /**
     * Tampilan key yang aman: prefix polos + sisanya disamarkan.
     *
     * @param string $prefix Nilai api_keys.key_prefix.
     */
    function api_key_mask(string $prefix): string
    {
        if ($prefix === '') {
            return '-';
        }

        return $prefix . str_repeat('*', 8);
    }
}

if (! function_exists('api_key_generated')) {
    /**
     * Buat sepasang key + hash. Pintasan untuk ApiKeyService::generate().
     *
     * @return array{plain:string, hash:string, prefix:string}
     */
    function api_key_generated(): array
    {
        return (new ApiKeyService())->generate();
    }
}
