# Instalasi dengan Docker Compose

Compose menjalankan satu container aplikasi Apache/PHP. Server MySQL dan
database harus sudah tersedia secara terpisah. Image proyek menyertakan
ekstensi `bcmath` untuk fitur Hashids.

## Persyaratan

- Docker Desktop pada Windows/macOS, atau Docker Engine dan Compose plugin
  pada Linux.
- File `.env` di root proyek dengan konfigurasi aplikasi dan database.
- Server MySQL/MariaDB yang bisa dijangkau dari container.

## 1. Siapkan konfigurasi

Jalankan perintah dari root proyek, di folder yang berisi `docker-compose.yml`.
Pastikan `.env` tersedia. Jangan commit atau membagikan file ini.

Apache mendengarkan port `80` di dalam container. Port host default adalah
`8091`, sehingga aplikasi dapat dibuka di `http://localhost:8091/`. Pastikan
nilai `app.baseURL` dan port host pada `.env` sesuai:

```dotenv
app.baseURL = http://localhost:8091/
APP_PORT = 8091
```

Pemetaan port adalah `8091:80` (host:container). Untuk memakai port host lain,
ubah `APP_PORT` dan sesuaikan `app.baseURL`. Dari komputer lain, gunakan
hostname atau IP Windows sebagai ganti `localhost`.

### Koneksi database

Jika MySQL berjalan di host Windows yang sama, `localhost` di `.env` tidak
akan menunjuk ke host dari dalam container. Gunakan:

```dotenv
database.default.hostname = host.docker.internal
database.khanza.hostname = host.docker.internal
```

Jika database berada di server lain, gunakan hostname/IP server tersebut.
Pastikan port MySQL terbuka dan user database mengizinkan koneksi dari Docker.
Compose tidak membuat database maupun kredensialnya.

## 2. Build dan jalankan

```console
docker compose up -d --build
```

Periksa status:

```console
docker compose ps
```

Container siap saat status aplikasi `healthy`. Buka
`http://localhost:8091/` (atau URL yang Anda tetapkan di `app.baseURL`).
Saat startup, entrypoint menyiapkan `writable/`, menunggu database utama, dan
mencoba menjalankan migrasi.

## Operasi container

Lihat log:

```console
docker compose logs -f app
```

Periksa status migrasi:

```console
docker compose exec app php spark migrate:status
```

Jalankan migrasi manual:

```console
docker compose exec app php spark migrate --all
```

Hentikan container tanpa menghapus folder runtime:

```console
docker compose down
```

Rebuild image setelah mengubah kode atau Dockerfile:

```console
docker compose up -d --build
```

## Pengaturan startup

Variabel berikut dapat diatur melalui environment untuk Compose:

| Variabel | Default | Fungsi |
| --- | --- | --- |
| `APP_PORT` | `8091` | Port host yang dipetakan ke port 80 container |
| `WAIT_FOR_DB` | `true` | Menunggu database utama sebelum startup |
| `DB_WAIT_TIMEOUT` | `60` | Batas waktu menunggu database, dalam detik |
| `RUN_MIGRATIONS` | `true` | Menjalankan migrasi otomatis saat startup |

Untuk diagnosis container atau koneksi database, lihat
[Troubleshooting](TROUBLESHOOTING.md).
