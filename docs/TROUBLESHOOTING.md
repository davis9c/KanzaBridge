# Troubleshooting

## Database tidak dapat dihubungi

- Pastikan MySQL/MariaDB berjalan dan port yang dikonfigurasi terbuka.
- Periksa nama database, username, password, dan hostname di `.env`.
- Native: `localhost` menunjuk ke mesin aplikasi.
- Docker Desktop: jika database berjalan di Windows host, gunakan
  `host.docker.internal`, bukan `localhost`.
- Pastikan user database diizinkan untuk koneksi dari host/container aplikasi.

Untuk Docker, periksa log dengan:

```console
docker compose logs --tail 100 app
```

## Container restart atau tidak sehat

Periksa status dan log:

```console
docker compose ps --all
docker compose logs --tail 100 app
```

Jika log menampilkan `env: 'sh\r': No such file or directory`, file script
shell memiliki line ending Windows. Pastikan `.gitattributes` proyek
diterapkan, lalu build ulang:

```console
docker compose up -d --build
```

Jika Apache tidak mulai, cari pesan `AH...` pada log; biasanya berisi file dan
baris konfigurasi yang bermasalah.

## Port aplikasi tidak dapat dibuka

- Docker default menggunakan port host `8091` dan port container `80`.
- Pastikan `APP_PORT` dan `app.baseURL` di `.env` sama-sama menunjuk ke port
  host yang dipakai.
- Jika port host sudah digunakan, pilih port lain lalu jalankan ulang
  `docker compose up -d`.
- Dari komputer lain, gunakan IP/hostname mesin host, bukan `localhost`.

## Migrasi database

Jika migrasi dilewati karena database belum terjangkau, perbaiki koneksi lalu
jalankan:

```console
php spark migrate --all
```

Di Docker:

```console
docker compose exec app php spark migrate --all
```

## API mengembalikan error autentikasi

- API v1: login ulang dan kirim `Authorization: Bearer <JWT_TOKEN>`.
- API v2: kirim `X-API-Key: <API_KEY>` dan pastikan key aktif serta memiliki
  scope endpoint tersebut.
- Periksa bahwa URL, HTTP method, dan format JSON sesuai dengan
  [Penggunaan API](API-USAGE.md).

## Hashids meminta ekstensi matematika

Hashids memerlukan ekstensi PHP `bcmath` atau `gmp`. Image Docker proyek
menyertakan `bcmath`; untuk instalasi native, pasang salah satu ekstensi
tersebut dan pastikan aktif pada PHP CLI serta PHP web server.

## Masalah autoload atau cache (native)

```console
composer dump-autoload
php spark cache:clear
```

Untuk langkah instalasi, lihat [Instalasi Native](NATIVE-INSTALLATION.md) dan
[Instalasi Docker](DOCKER-INSTALLATION.md).
