<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SysDashboard extends BaseController
{

    public function __construct() {}

    public function index()
    {
        // =====================
        // INFO SISTEM
        // =====================
        $systemInfo = [
            'os'        => php_uname(),
            'php'       => PHP_VERSION,
            'server'    => $_SERVER['SERVER_SOFTWARE'] ?? '-',
            'domain'    => $_SERVER['HTTP_HOST'] ?? '-',
            'base_url'  => base_url(),
            'memory'    => ini_get('memory_limit'),
            'timezone'  => date_default_timezone_get(),
            'date'      => date('Y-m-d H:i:s'),
        ];

        $data = [
            'title'        => 'Dashboard',
            'systemInfo'   => $systemInfo,
        ];

        return view('sys-dashboard', $data);
    }
}
