# Instalasi KanzaBridge

Panduan lengkap untuk memasang dan menjalankan aplikasi **KanzaBridge** dari awal.

---

## 1. Persyaratan Sistem

| Komponen | Versi Minimum |
| -------- | ------------- |
| PHP      | 8.1+          |
| MySQL    | 5.7+          |
| Composer | 2.0+          |
| Git      | Latest        |

### Ekstensi PHP yang Diperlukan

Pastikan ekstensi berikut aktif di `php.ini`:

```ini
extension=intl
extension=mbstring
extension=json
extension=mysqli
extension=mysqlnd
extension=curl
```

Verifikasi dengan perintah:

```bash
php -m
```

---

## 2. Clone Repository

```bash
git clone https://github.com/username/kanza-bridge.git
cd kanza-bridge
```

---

## 3. Install Dependencies

```bash
composer install
```

Perintah ini akan mengunduh semua dependency ke folder `vendor/`.

---

## 4. Konfigurasi Environment

### 4.1 Copy File Environment

```bash
# Linux / macOS
cp env .env

# Windows (Command Prompt)
copy env .env

# Windows (PowerShell)
Copy-Item env .env
```

### 4.2 Edit `.env`

Buka `.env` dan sesuaikan konfigurasi berikut:

```env
# Environment
CI_ENVIRONMENT = development

# Base URL
app.baseURL = 'http://localhost:8080'

# ====== DATABASE DEFAULT (KanzaBridge) ======
database.default.hostname = localhost
database.default.database = kanza_bridge
database.default.username = root
database.default.password = your_password
database.default.DBDriver = MySQLi
database.default.port = 3306

# ====== DATABASE KHANZA (SIMRS - hanya dibaca) ======
database.khanza.hostname = localhost
database.khanza.database = sik_beta
database.khanza.username = root
database.khanza.password = your_password
database.khanza.DBDriver = MySQLi
database.khanza.port = 3306

# ====== JWT Configuration ======
JWT_SECRET = "your_strong_random_secret_key_here"
JWT_TTL = 3600

# ====== Hashids ======
HASHIDS_SALT = "your_unique_hashids_salt_here"

# ====== Admin Credentials (UserGate) ======
SECRET_USER = admin
SECRET_PASSWORD = your_admin_password
ROLE_ADMIN = J002
```

> **Tips:** Gunakan secret key yang kuat dan acak. Anda bisa generate dengan:
> ```bash
> openssl rand -base64 32
> ```

---

## 5. Siapkan Database

### 5.1 Buat Database (Opsional)

Jika database belum ada, buat melalui MySQL:

```sql
CREATE DATABASE kanza_bridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Untuk database Khanza (SIMRS), database `sik_beta` biasanya sudah tersedia dari instalasi SIMRS Khanza.

### 5.2 Buat User Database (Opsional)

```sql
CREATE USER 'kanza_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON kanza_bridge.* TO 'kanza_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 6. Jalankan Migration

Migration akan membuat semua tabel yang dibutuhkan aplikasi:

```bash
php spark migrate
```

Output yang diharapkan:

```
Running migrations...
    Running: App\Database\Migrations\2026-10-02-000001_CreateKanzaBridgeSchema
Migrations complete.
```

### Tabel yang Dibuat

| Tabel | Keterangan |
| ----- | ---------- |
| `roles` | Role lokal (RBAC) |
| `users` | User lokal (identitas + status) |
| `user_roles` | Relasi user ↔ role |
| `api_applications` | Registrasi aplikasi pemanggil API |
| `api_keys` | API key untuk integrasi (hash only) |
| `api_key_scopes` | Permission/scope per API key |

> **Catatan:** Migration bersifat **idempotent** — aman dijalankan berkali-kali tanpa menghapus data.

---

## 7. Jalankan Seeder (Opsional)

Seeder mengisi data awal (role default):

```bash
php spark db:seed RoleSeeder
```

> **Catatan:** Role default juga otomatis dibuat saat user pertama kali login melalui `RoleModel::ensureDefaults()`. Seeder ini hanya diperlukan jika Anda ingin menyiapkan data tanpa harus login terlebih dahulu.

---

## 8. Jalankan Server

### Development Server (Spark)

```bash
php spark serve
```

Akses aplikasi di: **http://localhost:8080**

### Dengan Apache/Nginx

Pastikan `DocumentRoot` menunjuk ke folder `public/`:

```apache
<VirtualHost *:80>
    ServerName kanza-bridge.local
    DocumentRoot /var/www/kanza-bridge/public

    <Directory /var/www/kanza-bridge/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 9. Verifikasi Instalasi

| Langkah | Cara Verifikasi |
| ------- | --------------- |
| Web app | Buka `http://localhost:8080`, halaman login harus tampil |
| Database | Cek `SHOW TABLES;` — harus ada 6 tabel |
| Login | Login dengan kredensial UserGate |
| API | Login via `/api/auth/login` untuk mendapatkan JWT token |

---

## Troubleshooting Umum

### Database Connection Failed

```bash
# Test koneksi MySQL
mysql -u root -p kanza_bridge
```

- Pastikan MySQL sudah running
- Cek credentials di `.env`
- Pastikan database sudah dibuat

### Class Not Found / 404

```bash
# Clear cache
php spark cache:clear

# Regenerate autoloader
composer dump-autoload
```

### Permission Denied (writable/)

```bash
# Linux / macOS
chmod -R 755 writable/
```

### Port 8080 Sudah Dipakai

```bash
php spark serve --port 8081
```

---

## Langkah Selanjutnya

- Baca [migration.md](migration.md) untuk detail tabel dan perintah migrasi lainnya
- Baca [seeding.md](seeding.md) untuk detail seeding data
