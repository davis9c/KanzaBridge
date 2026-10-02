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
    | Settings
    |--------------------------------------------------------------------------
    */
    $routes->get('settings', 'SysSettings::index');


});

/*
        |--------------------------------------------------------------------------
        | Load API Routes
        |--------------------------------------------------------------------------
*/
if (file_exists(APPPATH . 'Config/RoutesApi.php')) {
    require APPPATH . 'Config/RoutesApi.php';
}
