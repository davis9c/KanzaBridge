<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SysSettings extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Pengaturan'
        ];
        return view('sys-settings', $data);
    }
}
