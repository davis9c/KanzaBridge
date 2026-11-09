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
        // Sumber data tabel untuk DataTables server-side.
        $routes->get('data', 'SysUser::data');
        // Form tambah user memakai modal di halaman ini, jadi tidak ada
        // halaman GET-nya — cukup endpoint POST.
        $routes->post('create', 'SysUser::store');
        $routes->get('edit/(:num)', 'SysUser::edit/$1');
        $routes->post('edit/(:num)', 'SysUser::update/$1');
        $routes->post('toggle-status/(:num)', 'SysUser::toggleStatus/$1');
        $routes->post('delete/(:num)', 'SysUser::destroy/$1');
    });

    /*
    |----------------------------------------------------------------------
    | Manajemen Application (API key)
    |----------------------------------------------------------------------
    |
    | Aplikasi pemanggil API beserta key-nya. Tabel manage disimpan di
    | database aplikasi (khanzabridge); sik_beta hanya dibaca sebagai
    | sumber data yang diekspose lewat API V2.
    |
    | SuperAdmin mengelola semua application. Admin hanya application
    | miliknya sendiri — dicek di server oleh AccessService, bukan
    | sekadar disembunyikan di view.
    |
    */
    $routes->group('application', function ($routes) {
        $routes->get('/', 'SysApplication::index');
        $routes->get('usage', 'SysApplication::usage');
        // Sumber data tabel untuk DataTables server-side.
        $routes->get('data', 'SysApplication::data');
        $routes->get('create', 'SysApplication::create');
        $routes->post('create', 'SysApplication::store');
        $routes->get('edit/(:num)', 'SysApplication::edit/$1');
        $routes->post('edit/(:num)', 'SysApplication::update/$1');
        $routes->post('delete/(:num)', 'SysApplication::destroy/$1');

        $routes->get('(:num)/keys', 'SysApplication::keys/$1');
        $routes->post('(:num)/keys', 'SysApplication::storeKey/$1');
        $routes->post('(:num)/keys/(:num)/scopes', 'SysApplication::updateScopes/$1/$2');
        $routes->post('(:num)/keys/(:num)/toggle-status', 'SysApplication::toggleStatus/$1/$2');
        $routes->post('(:num)/keys/(:num)/rotate', 'SysApplication::rotate/$1/$2');
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
$routes->get('kb-admin/setup', 'ApiV2\WebSetup::index');
$routes->post('kb-admin/setup', 'ApiV2\WebSetup::save');
$routes->get('kb-admin/dashboard', 'ApiV2\Dashboard::index');
$routes->get('kb-admin/users', 'ApiV2\WebUser::index');
$routes->get('kb-admin/users/new', 'ApiV2\WebUser::create');
$routes->post('kb-admin/users/save', 'ApiV2\WebUser::save');
$routes->get('kb-admin/users/edit/(:num)', 'ApiV2\WebUser::edit/$1');
$routes->post('kb-admin/users/update/(:num)', 'ApiV2\WebUser::update/$1');
$routes->get('kb-admin/users/delete/(:num)', 'ApiV2\WebUser::delete/$1');
$routes->get('kb-admin/tokens', 'ApiV2\WebToken::index');
$routes->post('kb-admin/tokens/create', 'ApiV2\WebToken::create');
$routes->get('kb-admin/tokens/revoke/(:num)', 'ApiV2\WebToken::revoke/$1');

if (file_exists(APPPATH . 'Config/RoutesApi.php')) {
    require APPPATH . 'Config/RoutesApi.php';
}
