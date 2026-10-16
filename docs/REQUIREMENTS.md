# Kebutuhan Sistem

## Menjalankan secara native

- PHP **8.2 atau lebih baru**. Dockerfile proyek menggunakan PHP 8.3.
- Composer 2.
- MySQL atau MariaDB yang dapat diakses dari mesin aplikasi.
- Git untuk mengambil kode sumber.
- Ekstensi PHP:
  - `intl`
  - `mbstring`
  - `mysqli` (dengan MySQL Native Driver)
  - `bcmath` atau `gmp` untuk fitur Hashids
  - `json` (umumnya sudah aktif pada PHP 8)

Pastikan ekstensi aktif pada PHP CLI yang digunakan Composer dan `php spark`:

```console
php -v
php -m
composer --version
```

## Menjalankan dengan Docker

- Docker Desktop pada Windows/macOS, atau Docker Engine dan Docker Compose
  plugin pada Linux.
- Server MySQL/MariaDB eksternal beserta database yang digunakan aplikasi.

Image sudah menyertakan PHP 8.3, Apache, Composer dependencies, dan ekstensi
PHP yang dibutuhkan, termasuk `bcmath` untuk Hashids. Compose **tidak** membuat
server database atau database.

## Database

Konfigurasi aplikasi menyediakan dua koneksi MySQL:

- `default`: database utama KanzaBridge.
- `khanza`: database SIMRS untuk data terkait pegawai, dokter, petugas, dan
  jabatan.

Kedua server/database harus sudah tersedia dan kredensial pada `.env` harus
memiliki izin yang sesuai. Kebutuhan koneksi bisa berbeda sesuai fitur yang
digunakan.
