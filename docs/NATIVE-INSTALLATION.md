# Instalasi Native

Panduan ini menjalankan KanzaBridge langsung di mesin pengembangan, tanpa
Docker.

## 1. Siapkan perangkat lunak

Pasang PHP, ekstensi yang diperlukan, Composer 2, Git, dan MySQL/MariaDB.
Untuk fitur Hashids, pastikan ekstensi `bcmath` atau `gmp` aktif. Versi dan
ekstensi dijelaskan di [Kebutuhan Sistem](REQUIREMENTS.md).

## 2. Ambil kode dan pasang dependency

```console
git clone <URL-REPOSITORY>
cd KanzaBridge
composer install
```

Di PowerShell, salin template environment jika `.env` belum tersedia:

```powershell
Copy-Item env .env
```

Di Linux/macOS:

```console
cp env .env
```

Periksa setiap nilai di `.env` setelah menyalin template. Ganti nilai
placeholder dengan konfigurasi lokal yang benar, gunakan secret yang kuat,
dan jangan commit atau membagikan `.env`.

## 3. Konfigurasikan aplikasi dan database

Atur setidaknya `CI_ENVIRONMENT`, `app.baseURL`, dan kredensial kedua grup
database. Untuk aplikasi native, hostname MySQL biasanya `localhost` jika
server database berada di mesin yang sama.

Contoh nilai URL untuk server pengembangan:

```dotenv
CI_ENVIRONMENT = development
app.baseURL = http://localhost:8080/
```

Nama database, username, password, dan port harus disesuaikan dengan server
Anda. Rincian pengaturan ada di [Konfigurasi](CONFIGURATION.md).

## 4. Jalankan migrasi

Pastikan koneksi database utama sudah tersedia, lalu jalankan:

```console
php spark migrate --all
```

Untuk melihat status migrasi:

```console
php spark migrate:status
```

## 5. Jalankan aplikasi

```console
php spark serve --host 0.0.0.0 --port 8080
```

Buka URL yang sesuai dengan `app.baseURL`, biasanya
`http://localhost:8080/`.

## Perintah pengembangan

Bersihkan cache:

```console
php spark cache:clear
```

Perbarui autoloader setelah perubahan dependency:

```console
composer dump-autoload
```

Lihat [Troubleshooting](TROUBLESHOOTING.md) jika aplikasi tidak dapat
dijalankan atau tidak tersambung ke database.
