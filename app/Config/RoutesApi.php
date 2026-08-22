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
    //$routes->post('auth/login', 'Api\SysApiAuth::login');
    $routes->post('auth/login', 'Api\Auth::login');
    //$routes->get('auth', 'Api\SysApiAuth::index');
    $routes->post('auth/refresh', 'Api\SysApiAuth::refresh');
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

$routes->group('api/v2', function ($routes) {
    $routes->post('auth/login', 'ApiV2\Auth::login');
    $routes->get('auth/me', 'ApiV2\Auth::me');
    $routes->post('auth/logout', 'ApiV2\Auth::logout');

    $routes->get('users', 'ApiV2\Users::index');
    $routes->get('users/roles', 'ApiV2\Users::roles');
    $routes->get('users/(:num)', 'ApiV2\Users::show/$1');
    $routes->post('users', 'ApiV2\Users::create');
    $routes->put('users/(:num)', 'ApiV2\Users::update/$1');
    $routes->delete('users/(:num)', 'ApiV2\Users::delete/$1');

    $routes->get('tokens', 'ApiV2\Tokens::index');
    $routes->post('tokens', 'ApiV2\Tokens::create');
    $routes->delete('tokens/(:num)', 'ApiV2\Tokens::delete/$1');

    $routes->get('produk', 'ApiV2\Produk::index');
    $routes->get('produk/(:num)', 'ApiV2\Produk::show/$1');

    $routes->get('database/tables', 'ApiV2\DatabaseInfo::tables');
    $routes->get('pegawai/list', 'ApiV2\DatabaseInfo::pegawai');
    $routes->get('pegawai/list/(:num)', 'ApiV2\DatabaseInfo::pegawaiById/$1');
});
