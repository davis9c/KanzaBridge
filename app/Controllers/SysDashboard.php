<?php

namespace App\Controllers;

use App\Libraries\Api\ApiUsageSummary;
use App\Models\Access\ApiKeyModel;

/**
 * Dashboard = gambaran umum kondisi API key.
 *
 * Isinya hanya kartu metrik. Daftar key lengkap tidak ada di sini: itu
 * sengaja, supaya halaman pertama yang dibuka setelah login langsung
 * menunjukkan ringkasannya tanpa harus menggulir tabel panjang. Untuk
 * melihat dan mengelola key, masuk lewat menu Application.
 *
 * Cakupannya mengikuti aturan kepemilikan yang sama dengan halaman
 * Application: SuperAdmin melihat semua key, Admin hanya key milik
 * application yang ia buat sendiri. Jadi angka di kartu berarti "milikmu"
 * untuk Admin, bukan angka global.
 *
 * Catatan akurasi: tidak ada tabel log request. Kolom `last_used_at` diisi
 * paling sering sekali per menit per key, jadi kartu ini adalah snapshot
 * posisi key — bukan penghitung request dan bukan grafik tren. Kalau nanti
 * butuh jumlah request per hari atau endpoint terlaris, itu penambahan
 * tersendiri (butuh tabel log).
 */
class SysDashboard extends BaseController
{
    public function __construct() {}

    public function index()
    {
        $keys = (new ApiKeyModel())->listForOwner(
            is_super_admin(),
            current_user_id()
        );

        return view('sys-dashboard', [
            'title'   => 'Dashboard',
            'summary' => (new ApiUsageSummary())->compute($keys),
        ]);
    }
}