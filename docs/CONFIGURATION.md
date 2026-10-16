# Konfigurasi

Pengaturan lingkungan aplikasi berada di file `.env` pada root proyek.
Buat file tersebut dari template `env` bila belum ada, lalu sesuaikan semua
nilainya dengan lingkungan masing-masing. Jangan commit atau membagikan `.env`.

## Pengaturan aplikasi

Contoh pengaturan lokal:

```dotenv
CI_ENVIRONMENT = development
app.baseURL = http://localhost:8080/
```

Pada deployment, gunakan alamat yang benar-benar dipakai klien. Untuk Docker,
`app.baseURL` harus memakai port host (default `8091`), bukan port internal
Apache `80`.

## Database

Aplikasi memiliki dua grup koneksi MySQL, `default` dan `khanza`. Atur
hostname, nama database, username, password, dan port untuk setiap grup yang
digunakan:

```dotenv
database.default.hostname = localhost
database.default.database = <DATABASE_UTAMA>
database.default.username = <DB_USER>
database.default.password = <DB_PASSWORD>
database.default.port = 3306

database.khanza.hostname = localhost
database.khanza.database = <DATABASE_SIMRS>
database.khanza.username = <DB_USER>
database.khanza.password = <DB_PASSWORD>
database.khanza.port = 3306
```

Untuk Docker Desktop jika MySQL berjalan di mesin host, ganti hostname
`localhost` dengan `host.docker.internal`. Jika database berada di server
terpisah, gunakan hostname/IP server tersebut dan pastikan firewall mengizinkan
koneksi.

## Secret autentikasi

Atur `JWT_SECRET` dengan nilai acak yang panjang dan rahasia. Jaga
`HASHIDS_SALT` serta kredensial administrator dengan perlindungan yang sama.
Jangan gunakan nilai placeholder pada deployment.

Lihat [Instalasi Docker](DOCKER-INSTALLATION.md) atau
[Instalasi Native](NATIVE-INSTALLATION.md) untuk pengaturan lingkungan masing-masing.
