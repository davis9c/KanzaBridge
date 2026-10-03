<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Api\ApiKeyFactoryTrait;
use Tests\Support\Database\LocalAccessDatabaseTrait;

/**
 * Aturan katalog endpoint API V2 vs route yang benar-benar ada.
 *
 * Ini penjaga anti-lenyap antara tiga hal yang harus selalu sama:
 *   Config\ApiScope  -> checklist di UI
 *   RoutesApi.php    -> route yang dilayani filter
 *   Controller V2    -> method yang dijalankan
 *
 * Kalau ketiganya melenceng, permission yang dicentang administrator
 * tidak akan berarti apa-apa.
 */
final class ApiScopeCatalogTest extends CIUnitTestCase
{
    use ApiKeyFactoryTrait;
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
     *  KATALOG
     * ---------------------------------------------------------------- */

    public function testKatalogScopeTidakKosong(): void
    {
        $this->assertNotEmpty(config('ApiScope')->endpoints);
    }

    public function testSetiapScopePunyaMetadataLengkap(): void
    {
        $base = config('ApiScope')->basePath;

        foreach (config('ApiScope')->endpoints as $scope => $meta) {
            $this->assertArrayHasKey('group', $meta, "Scope {$scope} tidak punya group.");
            $this->assertArrayHasKey('label', $meta, "Scope {$scope} tidak punya label.");
            $this->assertArrayHasKey('method', $meta, "Scope {$scope} tidak punya method.");
            $this->assertArrayHasKey('path', $meta, "Scope {$scope} tidak punya path.");
            $this->assertArrayHasKey('description', $meta, "Scope {$scope} tidak punya description.");

            $this->assertNotSame('', trim((string) $meta['label']), "Label scope {$scope} kosong.");
            $this->assertNotSame('', trim((string) $meta['description']), "Deskripsi scope {$scope} kosong.");
            $this->assertStringStartsWith(
                $base . '/',
                (string) $meta['path'],
                "Path scope {$scope} harus berada di bawah {$base}/"
            );
        }
    }

    public function testPathScopeUnik(): void
    {
        $seen = [];

        foreach (config('ApiScope')->endpoints as $scope => $meta) {
            $path = (string) $meta['path'];

            $this->assertArrayNotHasKey(
                $path,
                $seen,
                'Path ' . $path . ' dipakai dua kali: ' . $scope . ' dan ' . (string) ($seen[$path] ?? '?')
            );

            $seen[$path] = $scope;
        }
    }

    public function testSemuaEndpointWajibReadonly(): void
    {
        // API V2 tidak pernah menulis ke sik_beta. Kalau ada endpoint
        // tulis yang masuk ke katalog tanpa sengaja, test ini gagal
        // sebelum sempat dipakai klien.
        foreach (config('ApiScope')->endpoints as $scope => $meta) {
            $this->assertTrue(
                (bool) $meta['readonly'],
                "Scope {$scope} ditandai bukan read-only. API V2 hanya boleh membaca."
            );

            $this->assertContains(
                strtoupper((string) $meta['method']),
                ['GET', 'POST'],
                "Method scope {$scope} di luar GET/POST."
            );
        }
    }

    /* ---------------------------------------------------------------- *
     *  ROUTE <-> KATALOG
     * ---------------------------------------------------------------- */

    public function testSetiapRouteV2PunyaScopeYangDikenal(): void
    {
        $catalog = config('ApiScope')->endpoints;
        $routes  = $this->v2Routes();

        $this->assertNotEmpty($routes, 'Tidak ada route API V2 sama sekali.');

        foreach ($routes as $path => $route) {
            $scope = $route['scope'];

            $this->assertNotNull($scope, "Route {$path} tidak menyebut scope lewat filter apikey:.");
            $this->assertArrayHasKey(
                $scope,
                $catalog,
                "Route {$path} memakai scope \"{$scope}\" yang tidak ada di Config\\ApiScope."
            );
        }
    }

    public function testSetiapScopeKatalogPunyaRoute(): void
    {
        $v2Routes = $this->v2Routes();

        foreach (config('ApiScope')->endpoints as $scope => $meta) {
            $this->assertArrayHasKey(
                (string) $meta['path'],
                $v2Routes,
                "Scope {$scope} ada di katalog tapi tidak ada route-nya."
            );
        }
    }

    public function testMethodKatalogSamaDenganMethodRoute(): void
    {
        $v2Routes = $this->v2Routes();

        foreach (config('ApiScope')->endpoints as $scope => $meta) {
            $route = $v2Routes[(string) $meta['path']];

            $this->assertSame(
                strtoupper((string) $meta['method']),
                $route['verb'],
                "Method katalog untuk {$scope} berbeda dengan method route {$meta['path']}."
            );
        }
    }

    public function testSetiapRouteV2MenunjukControllerYangAda(): void
    {
        foreach ($this->v2Routes() as $path => $route) {
            [$controller, $method] = explode('::', $route['handler']);

            $this->assertTrue(class_exists($controller), "Controller {$controller} untuk {$path} tidak ada.");
            $this->assertTrue(
                method_exists($controller, $method),
                "Method {$controller}::{$method}() untuk {$path} tidak ada."
            );
        }
    }

    public function testRouteV2SelaluTerfilterApiKey(): void
    {
        foreach ($this->v2Routes() as $path => $route) {
            $filtered = array_filter(
                $route['filters'],
                static fn (string $filter): bool => str_starts_with($filter, 'apikey')
            );

            $this->assertNotEmpty(
                $filtered,
                "Route {$path} tidak memakai filter apikey."
            );
        }
    }

    public function testRouteV1TetapPakaiJwt(): void
    {
        // V2 tidak boleh diam-diam mengubah mekanisme V1 yang masih live.
        $v1 = $this->v1Routes();

        $this->assertNotEmpty($v1, 'Route API V1 hilang semua.');

        foreach ($v1 as $from => $filters) {
            if (str_starts_with($from, 'api/auth/')) {
                // Endpoint publik V1 memang tanpa autentikasi.
                continue;
            }

            $this->assertContains('jwt', $filters, "Route V1 {$from} kehilangan filter jwt.");
        }
    }

    /* ---------------------------------------------------------------- *
     *  BANTUAN
     * ---------------------------------------------------------------- */

    /**
     * Peta path (tanpa baseURL) => ['verb', 'handler', 'scope', 'filters'].
     *
     * @return array<string, array{verb:string, handler:string, scope:?string, filters:list<string>}>
     */
    private function v2Routes(): array
    {
        $found = [];
        $base  = config('ApiScope')->basePath;

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            foreach (service('routes')->getRoutes($verb) as $from => $handler) {
                $from = (string) $from;

                if (! str_starts_with($from, $base . '/')) {
                    continue;
                }

                $options = service('routes')->getRoutesOptions($from, $verb);
                $filters = array_map('strval', (array) ($options['filter'] ?? []));

                $scope = null;

                foreach ($filters as $filter) {
                    if (str_starts_with($filter, 'apikey:')) {
                        $scope = substr($filter, strlen('apikey:'));
                    }
                }

                $found[$from] = [
                    'verb'    => $verb,
                    'handler' => (string) $handler,
                    'scope'   => $scope,
                    'filters' => $filters,
                ];
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Peta route API V1 => filter yang dipakai.
     *
     * @return array<string, list<string>>
     */
    private function v1Routes(): array
    {
        $found = [];
        $base  = config('ApiScope')->basePath;

        foreach (['GET', 'POST'] as $verb) {
            foreach (service('routes')->getRoutes($verb) as $from => $handler) {
                $from = (string) $from;

                if (! str_starts_with($from, 'api/') || str_starts_with($from, $base)) {
                    continue;
                }

                $options = service('routes')->getRoutesOptions($from, $verb);
                $found[$from] = array_map('strval', (array) ($options['filter'] ?? []));
            }
        }

        return $found;
    }
}