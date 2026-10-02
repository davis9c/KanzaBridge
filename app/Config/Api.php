<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi khusus lapisan API.
 *
 * Dipisah dari program inti web agar endpoint API tidak perlu membaca
 * setting lewat env() secara scattered, dan agar secret API punya
 * satu sumber kebenaran.
 *
 * Resolusi nilai:
 *   1. `.env` dengan prefix `api.` (mis. `api.jwtSecret`) — menang
 *   2. Fallback ke env lama (`JWT_SECRET`, `JWT_TTL`) — kompatibilitas
 *
 * Contoh di .env:
 *   api.jwtSecret = "..."
 *   api.jwtTtl    = 3600
 */
class Api extends BaseConfig
{
    /**
     * Group koneksi database yang dipakai API.
     * Group ini tetap didefinisikan di Config\Database karena framework
     * hanya membaca koneksi dari sana.
     */
    public ?string $dbGroup = null;

    public ?string $jwtSecret = null;

    public ?int $jwtTtl = null;

    public string $jwtAlgorithm = 'HS256';

    /**
     * Role default untuk user yang tidak terdaftar di tabel petugas.
     */
    public string $defaultRole = 'dokter';

    public string $defaultKdJbtn = 'D1010';

    public string $defaultNmJbtn = 'DOKTER';

    public function __construct()
    {
        parent::__construct();

        $this->dbGroup    ??= 'khanza';
        $this->jwtSecret  ??= env('JWT_SECRET');
        $this->jwtTtl     ??= (int) env('JWT_TTL', 3600);
    }
}
