<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        return view('api_v2/dashboard');
    }
}
