<?php

use CodeIgniter\Router\RouteCollection;

/**
 * API Routes
 *
 * @var RouteCollection $routes
 * 
 */
$routes->group('api', function ($routes) {

    /*
    |----------------------------------------------------------------------
    | AUTH (PUBLIC)
    |----------------------------------------------------------------------
    */
    $routes->post('auth/login', 'Api\Auth::login');
    $routes->post('auth/refresh', 'Api\Auth::refresh');
});

$routes->group('api', ['filter' => 'jwt'], function ($routes) {

    /*
    |----------------------------------------------------------------------
    | USER API (JWT PROTECTED)
    |----------------------------------------------------------------------
    */
    $routes->get('users', 'Api\User::index');
    $routes->get('pegawai', 'Api\Pegawai::index');
    $routes->post('pegawai/by-ids', 'Api\Pegawai::getByIds');
    $routes->post('pegawai/by-nik', 'Api\Pegawai::getByNik');
    $routes->post('pegawai/dokter', 'Api\Dokter::index');
    $routes->post('petugas/DanJabatan', 'Api\Petugas::danJabatan');
    $routes->post('dokter', 'Api\Dokter::index');
    $routes->post('dokter/danSpesialis', 'Api\Dokter::danSpesialis');
    $routes->get('jabatan', 'Api\Jabatan::index');
    $routes->get('jabatan/with-petugas', 'Api\Jabatan::withPetugas');

    $routes->post('petugas/by-jbtn', 'Api\Petugas::getByJbtn');
    $routes->post('petugas/by-nips', 'Api\Petugas::getByNips');
    $routes->post('petugas/by-nip', 'Api\Petugas::getByNip');
});

/*
|----------------------------------------------------------------------
| API V2 (API KEY)
|----------------------------------------------------------------------
| Berdampingan dengan V1 di atas, bukan menggantikannya: klien yang
| sudah memakai JWT (Authorization: Bearer) tetap jalan sampai migrasi
| sisi klien selesai.
|
| Autentikasi: header X-API-Key, atau Authorization: Bearer <key>.
|
| Setiap route menyebut scope-nya sebagai argumen filter
| `apikey:<scope>`. Scope itu dibaca ApiKeyAuthFilter, dan kode scope
| yang sah didefinisikan di Config\ApiScope — yang juga menjadi sumber
| checklist endpoint di halaman "Application". Karena scope ditulis di
| route, tabel permission tidak mungkin berbeda dengan route sebenarnya.
|
| Kode scope pada setiap baris WAJIB ada di Config\ApiScope::$endpoints;
| tests/unit/ApiScopeCatalogTest menjaga hal itu.
*/
$routes->group('api/v2', function ($routes) {

    $routes->get('me', 'Api\V2\Meta::me', ['filter' => 'apikey:meta.read']);

    $routes->get('users', 'Api\V2\User::index', ['filter' => 'apikey:users.read']);

    $routes->get('pegawai', 'Api\V2\Pegawai::index', ['filter' => 'apikey:pegawai.read']);
    $routes->post('pegawai/by-ids', 'Api\V2\Pegawai::getByIds', ['filter' => 'apikey:pegawai.by-ids']);
    $routes->post('pegawai/by-nik', 'Api\V2\Pegawai::getByNik', ['filter' => 'apikey:pegawai.by-nik']);
    $routes->post('pegawai/dokter', 'Api\V2\Pegawai::dokter', ['filter' => 'apikey:dokter.read']);

    $routes->post('dokter', 'Api\V2\Dokter::index', ['filter' => 'apikey:dokter.read']);
    $routes->post('dokter/dan-spesialis', 'Api\V2\Dokter::danSpesialis', ['filter' => 'apikey:dokter.by-spesialis']);

    $routes->get('jabatan', 'Api\V2\Jabatan::index', ['filter' => 'apikey:jabatan.read']);
    $routes->get('jabatan/with-petugas', 'Api\V2\Jabatan::withPetugas', ['filter' => 'apikey:jabatan.with-petugas']);

    $routes->post('petugas/dan-jabatan', 'Api\V2\Petugas::danJabatan', ['filter' => 'apikey:petugas.read']);
    $routes->post('petugas/by-jbtn', 'Api\V2\Petugas::getByJbtn', ['filter' => 'apikey:petugas.by-jbtn']);
    $routes->post('petugas/by-nips', 'Api\V2\Petugas::getByNips', ['filter' => 'apikey:petugas.by-nips']);
    $routes->post('petugas/by-nip', 'Api\V2\Petugas::getByNip', ['filter' => 'apikey:petugas.by-nip']);
});
