# KanzaBridge

KanzaBridge adalah aplikasi web dan REST API berbasis CodeIgniter 4 untuk
mengelola akses pengguna serta menyediakan data pegawai, dokter, petugas, dan
jabatan. Aplikasi ini menghubungkan antarmuka administrasi dengan database
internal dan database SIMRS yang sudah tersedia.

## Fitur

- Login web dan pengelolaan pengguna dengan kontrol akses berbasis role.
- API terautentikasi JWT pada `/api`.
- API integrasi versi 2 dengan API key dan scope akses pada `/api/v2`.
- Akses data pegawai, dokter, petugas, dan jabatan.
- Pengelolaan aplikasi integrasi dan API key dari antarmuka administrasi.

## Teknologi

- PHP dan CodeIgniter 4
- MySQL/MariaDB melalui ekstensi MySQLi
- JWT dan API key dengan scope
- Apache untuk deployment Docker

## Dokumentasi

| Panduan | Isi |
| --- | --- |
| [Kebutuhan sistem](docs/REQUIREMENTS.md) | Versi PHP, ekstensi, database, dan perangkat lunak |
| [Instalasi native](docs/NATIVE-INSTALLATION.md) | Menjalankan aplikasi langsung di Windows, Linux, atau macOS |
| [Instalasi Docker](docs/DOCKER-INSTALLATION.md) | Build, menjalankan Compose, port, database, dan operasi container |
| [Penggunaan API](docs/API-USAGE.md) | Endpoint, autentikasi JWT/API key, dan contoh request |
| [Konfigurasi](docs/CONFIGURATION.md) | Pengaturan `.env` dan koneksi database |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | Diagnosis masalah instalasi, database, dan Docker |

## Struktur utama

```text
app/                 Kode aplikasi, konfigurasi, route, dan migrasi
public/              Document root aplikasi web
tests/               Pengujian otomatis
docs/                Dokumentasi proyek
docker-compose.yml   Konfigurasi Docker Compose
Dockerfile           Build image Apache dan PHP
```

## Lisensi

Proyek ini menggunakan lisensi MIT. Lihat [LICENSE](LICENSE).
