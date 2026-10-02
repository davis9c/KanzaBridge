<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi koneksi ke UserGate API.
 *
 * Terpisah dari `Config\Api` (milik lapisan API KanzaBridge yang sudah
 * live) dan dari `Config\Database` (yang hanya memuat definisi koneksi).
 *
 * Nilai dibaca dari `.env` dengan prefix `usergate.`:
 *
 *   usergate.baseUrl   = "https://usergate.sandalgurun.web.id/api/v1"
 *   usergate.apiKey    = "..."
 *   usergate.timeout   = 15
 *   usergate.refreshSkew = 60
 *
 * Lihat plan/API_REFERENCE_COMPLETE.md untuk detail endpoint.
 */
class UserGate extends BaseConfig
{
    /**
     * Base URL UserGate, TANPA trailing slash.
     */
    public string $baseUrl = 'https://usergate.sandalgurun.web.id/api/v1';

    /**
     * Nilai header `X-API-Key`.
     * Wajib diisi di .env. Kosong = client akan menolak semua request
     * dengan pesan jelas (bukan diam-diam terkirim tanpa key).
     */
    public ?string $apiKey = null;

    /**
     * Timeout dalam detik untuk koneksi + transfer.
     */
    public int $timeout = 15;

    /**
     * Seberapa awal (detik) access token di-refresh sebelum benar-benar
     * expired. Menghindari request gagal di tengah jalan.
     */
    public int $refreshSkew = 60;

    /**
     * Group database tempat user/role lokal disimpan.
     * `default` = khanzabridge (tabel aplikasi, BUKAN data SIMRS).
     */
    public ?string $dbGroup = null;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl      = rtrim($this->baseUrl, '/');
        $this->apiKey       ??= env('usergate.apiKey');
        $this->timeout      = (int) env('usergate.timeout', $this->timeout);
        $this->refreshSkew  = (int) env('usergate.refreshSkew', $this->refreshSkew);
        $this->dbGroup      ??= 'default';
    }

    /**
     * Apakah client-api key sudah siap dipakai.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && $this->apiKey !== 'CHANGE_ME';
    }
}
