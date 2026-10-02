<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/*
|--------------------------------------------------------------------------
| Authentication Routes (No auth filter needed)
|--------------------------------------------------------------------------
*/
$routes->get('login', 'Auth::login');
$routes->post('auth/attempt', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (Require login)
|--------------------------------------------------------------------------
|
| `auth`   : session login + refresh token UserGate
| `access` : user tanpa role hanya boleh melihat /no-access
|
*/
$routes->group('', ['filter' => ['auth', 'access']], function ($routes) {
    //$routes->group('', function ($routes) {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    $routes->get('/', 'SysDashboard::index');
    $routes->get('dashboard', 'SysDashboard::index');

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    $routes->group('profile', function ($routes) {
        $routes->get('/', 'SysProfile::index');
    });

    /*
    |--------------------------------------------------------------------------
    | System Guide
    |--------------------------------------------------------------------------
    */
    $routes->get('guide', 'SysGuide::index');

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    $routes->get('settings', 'SysSettings::index');

    /*
    |----------------------------------------------------------------------
    | Manajemen User
    |----------------------------------------------------------------------
    |
    | Akun dibuat di UserGate, role disimpan di DB lokal.
    | Promote ke SuperAdmin dan hapus user: hanya SuperAdmin
    | (dicek ulang di controller, bukan sekadar disembunyikan di view).
    |
    */
    $routes->group('user', function ($routes) {
        $routes->get('/', 'SysUser::index');
        $routes->get('create', 'SysUser::create');
        $routes->post('create', 'SysUser::store');
        $routes->get('edit/(:num)', 'SysUser::edit/$1');
        $routes->post('edit/(:num)', 'SysUser::update/$1');
        $routes->post('toggle-status/(:num)', 'SysUser::toggleStatus/$1');
        $routes->post('delete/(:num)', 'SysUser::destroy/$1');
    });

    /*
    |----------------------------------------------------------------------
    | Tanpa Akses
    |----------------------------------------------------------------------
    |
    | Tujuan user yang berhasil login tetapi tidak punya role. Route ini
    | selalu boleh diakses, jadi tidak tertahan AccessFilter.
    |
    */
    $routes->get('no-access', 'SysNoAccess::index');

});

/*
        |--------------------------------------------------------------------------
        | Load API Routes
        |--------------------------------------------------------------------------
*/
if (file_exists(APPPATH . 'Config/RoutesApi.php')) {
    require APPPATH . 'Config/RoutesApi.php';
}
