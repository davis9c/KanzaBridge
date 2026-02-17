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

    $routes->post('petugas/by-jbtn', 'Api\Petugas::getByJbtn');
    $routes->post('petugas/by-nips', 'Api\Petugas::getByNips');
    $routes->post('petugas/by-nip', 'Api\Petugas::getByNip');
});
