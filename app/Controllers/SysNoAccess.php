<?php

namespace App\Controllers;

/**
 * Halaman tujuan untuk user yang login tapi tidak punya role.
 *
 * Sengaja tidak memakai filter `access` secara khusus: route `no-access`
 * terdaftar di `Config\Access::$alwaysAllowed`, sehingga tidak pernah
 * dialihkan balik ke dirinya sendiri.
 */
class SysNoAccess extends BaseController
{
    public function index()
    {
        return view('no-access', [
            'title' => 'Tidak Ada Akses',
        ]);
    }
}
