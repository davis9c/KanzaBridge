<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SysGuide extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Guide'
        ];
        return view('sys-guide', $data);
    }
}
