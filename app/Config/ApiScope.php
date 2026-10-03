<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Katalog endpoint API V2 + permission (scope) yang mengelompokinya.
 *
 * File ini adalah SATU-SATUNYA sumber kebenaran tentang endpoint mana yang
 * boleh diakses dan dengan kode permission apa. Tiga tempat memakainya:
 *
 *   1. app/Config/RoutesApi.php  -> `['filter' => 'apikey:<scope>']` per route
 *   2. Halaman "Application"     -> checklist endpoint per API key
 *   3. tests/unit/ApiScopeCatalogTest -> memastikan route & katalog tidak melenceng
 *
 * SEMUA endpoint di sini read-only. API V2 tidak pernah menulis ke
 * sik_beta; database SIMRS hanya dibaca. Endpoint tulis (POST/PUT/DELETE
 * yang mengubah data) hanya bisa masuk lewat penambahan eksplisit di sini,
 * dan test akan menolaknya kalau ditandai tulis.
 *
 * Bentuk tiap entri:
 *   group       -> pengelompokan di UI (label diambil dari $groupLabels)
 *   label       -> judul yang dilihat administrator
 *   method      -> HTTP method
 *   path        -> path relatif terhadap baseURL, tanpa leading slash
 *   description -> penjelasan singkat untuk administrator
 *   readonly    -> wajib true; dipakai test sebagai penjaga
 */
class ApiScope extends BaseConfig
{
    /**
     * Versi API. Dipakai untuk membangun path dan ditampilkan di UI.
     */
    public string $version = 'v2';

    /**
     * Path dasar seluruh endpoint V2, relatif terhadap baseURL.
     */
    public string $basePath = 'api/v2';

    /**
     * Katalog endpoint => metadata permission.
     *
     * Kunci array adalah kode scope yang disimpan di tabel `api_key_scopes`.
     *
     * @var array<string, array{group:string, label:string, method:string, path:string, description:string, readonly:bool}>
     */
    public array $endpoints = [
        'meta.read' => [
            'group'       => 'meta',
            'label'       => 'Info key yang sedang dipakai',
            'method'      => 'GET',
            'path'        => 'api/v2/me',
            'description' => 'Nama application, key, dan daftar endpoint yang diizinkan. Dipakai untuk memverifikasi key tanpa mengambil data SIMRS.',
            'readonly'    => true,
        ],

        'users.read' => [
            'group'       => 'user',
            'label'       => 'Daftar user',
            'method'      => 'GET',
            'path'        => 'api/v2/users',
            'description' => 'Seluruh baris tabel `user` kolom id_user + password dalam bentuk terenkripsi AES. Ciphertext tetap perlu dilindungi, jadi beri scope ini hanya kepada integrasi yang benar-benar membutuhkannya.',
            'readonly'    => true,
        ],

        'pegawai.read' => [
            'group'       => 'pegawai',
            'label'       => 'Daftar pegawai',
            'method'      => 'GET',
            'path'        => 'api/v2/pegawai',
            'description' => 'Seluruh pegawai beserta kode jabatan dan nama jabatan.',
            'readonly'    => true,
        ],
        'pegawai.by-ids' => [
            'group'       => 'pegawai',
            'label'       => 'Pegawai berdasarkan id',
            'method'      => 'POST',
            'path'        => 'api/v2/pegawai/by-ids',
            'description' => 'Ambil id + nama pegawai untuk sekumpulan id. POST karena body berisi array, bukan karena mengubah data.',
            'readonly'    => true,
        ],
        'pegawai.by-nik' => [
            'group'       => 'pegawai',
            'label'       => 'Pegawai berdasarkan NIK',
            'method'      => 'POST',
            'path'        => 'api/v2/pegawai/by-nik',
            'description' => 'Ambil id + nama satu pegawai dari NIK-nya.',
            'readonly'    => true,
        ],

        'dokter.read' => [
            'group'       => 'dokter',
            'label'       => 'Daftar dokter',
            'method'      => 'POST',
            'path'        => 'api/v2/dokter',
            'description' => 'Seluruh baris tabel `dokter`.',
            'readonly'    => true,
        ],
        'dokter.by-spesialis' => [
            'group'       => 'dokter',
            'label'       => 'Dokter dan spesialis',
            'method'      => 'POST',
            'path'        => 'api/v2/dokter/dan-spesialis',
            'description' => 'Dokter beserta nama spesialis. Bergabung ke tabel `spesialis` yang belum ada di sik_beta — endpoint ini akan gagal di database yang belum punya tabel spesialis.',
            'readonly'    => true,
        ],

        'jabatan.read' => [
            'group'       => 'jabatan',
            'label'       => 'Daftar jabatan',
            'method'      => 'GET',
            'path'        => 'api/v2/jabatan',
            'description' => 'Seluruh baris tabel `jabatan`.',
            'readonly'    => true,
        ],
        'jabatan.with-petugas' => [
            'group'       => 'jabatan',
            'label'       => 'Jabatan beserta petugas',
            'method'      => 'GET',
            'path'        => 'api/v2/jabatan/with-petugas',
            'description' => 'Jabatan dengan array petugas di bawahnya.',
            'readonly'    => true,
        ],

        'petugas.read' => [
            'group'       => 'petugas',
            'label'       => 'Daftar petugas dan jabatan',
            'method'      => 'POST',
            'path'        => 'api/v2/petugas/dan-jabatan',
            'description' => 'Seluruh petugas beserta nama jabatan. Bisa disaring dengan parameter `jbtn`.',
            'readonly'    => true,
        ],
        'petugas.by-jbtn' => [
            'group'       => 'petugas',
            'label'       => 'Petugas berdasarkan kode jabatan',
            'method'      => 'POST',
            'path'        => 'api/v2/petugas/by-jbtn',
            'description' => 'Petugas pada satu atau beberapa kode jabatan.',
            'readonly'    => true,
        ],
        'petugas.by-nips' => [
            'group'       => 'petugas',
            'label'       => 'Petugas berdasarkan NIPS',
            'method'      => 'POST',
            'path'        => 'api/v2/petugas/by-nips',
            'description' => 'Petugas untuk sekumpulan NIPS.',
            'readonly'    => true,
        ],
        'petugas.by-nip' => [
            'group'       => 'petugas',
            'label'       => 'Petugas berdasarkan NIP',
            'method'      => 'POST',
            'path'        => 'api/v2/petugas/by-nip',
            'description' => 'Satu petugas dari NIP-nya.',
            'readonly'    => true,
        ],
    ];

    /**
     * Label kelompok untuk ditampilkan di UI.
     */
    public array $groupLabels = [
        'meta'     => 'Meta',
        'user'     => 'User',
        'pegawai'  => 'Pegawai',
        'dokter'   => 'Dokter',
        'jabatan'  => 'Jabatan',
        'petugas'  => 'Petugas',
    ];

    /**
     * Urutan kelompok di UI. Kelompok yang tidak disebut di sini
     * ditampilkan setelahnya dengan label hasil title-case.
     *
     * @var list<string>
     */
    public array $groupOrder = ['meta', 'user', 'pegawai', 'petugas', 'dokter', 'jabatan'];

    public function __construct()
    {
        parent::__construct();

        $this->basePath = trim($this->basePath, '/');
    }

    /**
     * Apakah kode scope dikenal.
     */
    public function has(string $scope): bool
    {
        return isset($this->endpoints[$scope]);
    }

    /**
     * @return array{group:string, label:string, method:string, path:string, description:string, readonly:bool}|null
     */
    public function meta(string $scope): ?array
    {
        return $this->endpoints[$scope] ?? null;
    }
}
