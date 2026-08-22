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
*/
$routes->group('', ['filter' => 'auth'], function ($routes) {
    //$routes->group('', function ($routes) {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    $routes->get('/', 'SysDashboard::index');
    $routes->get('dashboard', 'SysDashboard::index');
    $routes->get('pegawai', 'Pegawai::user');

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
    | Diagnostics & System Info (Admin only)
    |--------------------------------------------------------------------------
    */
    $routes->group('diagnose', ['filter' => 'role:admin'], function ($routes) {
        $routes->get('/', 'Diagnose::extensions');
        $routes->get('extensions', 'Diagnose::extensions');
        $routes->get('hashid', 'Diagnose::hashid');
        $routes->get('check-json', 'Diagnose::checkJson');
    });
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
