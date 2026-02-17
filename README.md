# Kanza Bridge

**Kanza Bridge** - Sistem manajemen pengguna dan karyawan berbasis web dengan autentikasi JWT, RBAC, dan API RESTful menggunakan CodeIgniter 4.

---

## 📋 Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi](#konfigurasi)
- [Penggunaan](#penggunaan)
- [API Documentation](#api-documentation)
- [Upload ke GitHub](#upload-ke-github)
- [Troubleshooting](#troubleshooting)

---

## ✨ Fitur Utama

- ✅ **Autentikasi JWT** - Secure token-based authentication untuk API
- ✅ **RBAC (Role-Based Access Control)** - Pengelolaan role dan permission
- ✅ **Manajemen Pegawai** - CRUD operasi untuk data karyawan
- ✅ **Manajemen Dokter** - Pengelolaan dokter dengan spesialis
- ✅ **Manajemen Petugas** - Pengelolaan staf dan jabatan
- ✅ **API RESTful** - 13 endpoint production-ready
- ✅ **Session Management** - Pengelolaan sesi pengguna
- ✅ **Dashboard** - Antarmuka web yang user-friendly
- ✅ **Enkripsi Data** - Data sensitif tersimpan dengan aman
- ✅ **Database Migrations** - Versionable database schema

---

## 📦 Persyaratan Sistem

| Komponen | Versi  |
| -------- | ------ |
| PHP      | 8.1+   |
| MySQL    | 5.7+   |
| Composer | 2.0+   |
| Git      | Latest |

### Ekstensi PHP Diperlukan

- `intl` - Internationalization
- `mbstring` - Multi-byte string
- `json` - JSON support
- `mysqlnd` - MySQL native driver
- `curl` - HTTP client

---

## 🚀 Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/kanza-bridge.git
cd kanza-bridge
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Environment

```bash
# Copy environment template
cp env .env

# Atau di Windows
copy env .env
```

### 4. Konfigurasi Database

Edit `.env`:

```env
database.default.hostname = localhost
database.default.database = sik
database.default.username = sikuser
database.default.password = sikuser
database.default.port = 3306
```

Buat database MySQL:

```sql
CREATE DATABASE sik;
CREATE USER 'sikuser'@'localhost' IDENTIFIED BY 'sikuser';
GRANT ALL PRIVILEGES ON sik.* TO 'sikuser'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Run Migrations

```bash
php spark migrate
```

### 6. Start Server

```bash
php spark serve
```

Aplikasi akan berjalan di `http://localhost:8080`

---

## ⚙️ Konfigurasi

### File `.env` - Konfigurasi Penting

```env
# Environment
CI_ENVIRONMENT = development

# Base URL
app.baseURL = 'http://localhost:8080'

# Database
database.default.hostname = localhost
database.default.database = sik
database.default.username = sikuser
database.default.password = sikuser
database.default.port = 3306

# JWT Configuration
JWT_SECRET = "your_jwt_secret_key_here"
JWT_TTL = 3600

# Admin Credentials
SECRET_USER = admin
SECRET_PASSWORD = password
ROLE_ADMIN = J002

# Hashids (untuk encode ID)
HASHIDS_SALT = "your_hashids_salt_here"
```

### File Konfigurasi Penting

| File                       | Tujuan                |
| -------------------------- | --------------------- |
| `app/Config/Routes.php`    | Web routes            |
| `app/Config/RoutesApi.php` | API routes            |
| `app/Config/Database.php`  | Database config       |
| `app/Config/Auth.php`      | Auth config           |
| `.env`                     | Environment variables |
| `.gitignore`               | Git ignore rules      |

---

## 📖 Penggunaan

### Login

1. Buka `http://localhost:8080/login`
2. Masuk dengan kredensial dari `.env`
3. Klik "Login"

### Dashboard

Setelah login, akses:

- `http://localhost:8080/dashboard` - Dashboard utama
- `http://localhost:8080/pegawai` - Manajemen pegawai
- `http://localhost:8080/profile` - Profile pengguna

---

## 🔌 API Documentation

### Authentication (Public)

#### Login & Dapatkan Token

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "your_password"
  }'
```

**Response:**

```json
{
    "status": true,
    "message": "Login berhasil",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600,
    "user": {
        "id": "M01",
        "username": "admin",
        "role": "admin"
    }
}
```

### Protected Endpoints (Butuh JWT Token)

Gunakan header: `Authorization: Bearer {JWT_TOKEN}`

#### 1. Users API

```
GET /api/users
```

List semua users.

#### 2. Pegawai (Employees) API

| Method | Endpoint              | Purpose            |
| ------ | --------------------- | ------------------ |
| GET    | `/api/pegawai`        | List semua pegawai |
| POST   | `/api/pegawai/by-ids` | Get pegawai by IDs |
| POST   | `/api/pegawai/by-nik` | Get pegawai by NIK |

**Example - Get by NIK:**

```bash
curl -X POST http://localhost:8080/api/pegawai/by-nik \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nik":"1234567890"}'
```

#### 3. Dokter (Doctors) API

| Method | Endpoint                   | Purpose                 |
| ------ | -------------------------- | ----------------------- |
| POST   | `/api/dokter`              | List dokter             |
| POST   | `/api/dokter/danSpesialis` | Dokter dengan spesialis |
| POST   | `/api/pegawai/dokter`      | Employee dokter         |

**Example - Get Dokter:**

```bash
curl -X POST http://localhost:8080/api/dokter \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"page":1,"limit":10}'
```

#### 4. Petugas (Staff) API

| Method | Endpoint                  | Purpose              |
| ------ | ------------------------- | -------------------- |
| POST   | `/api/petugas/by-jbtn`    | Staff by position    |
| POST   | `/api/petugas/by-nip`     | Staff by NIP         |
| POST   | `/api/petugas/by-nips`    | Multiple staff       |
| POST   | `/api/petugas/DanJabatan` | Staff dengan jabatan |

#### 5. Jabatan (Position) API

```
GET /api/jabatan
```

List semua jabatan/posisi.

---

## API Summary Table

| Method | Endpoint                   | Auth | Purpose            |
| ------ | -------------------------- | ---- | ------------------ |
| POST   | `/api/auth/login`          | ❌   | Login & token      |
| GET    | `/api/users`               | ✅   | List users         |
| GET    | `/api/pegawai`             | ✅   | List pegawai       |
| POST   | `/api/pegawai/by-ids`      | ✅   | Pegawai by IDs     |
| POST   | `/api/pegawai/by-nik`      | ✅   | Pegawai by NIK     |
| POST   | `/api/dokter`              | ✅   | List dokter        |
| POST   | `/api/dokter/danSpesialis` | ✅   | Dokter + spesialis |
| POST   | `/api/pegawai/dokter`      | ✅   | Employee dokter    |
| POST   | `/api/petugas/by-jbtn`     | ✅   | Staff by position  |
| POST   | `/api/petugas/by-nip`      | ✅   | Staff by NIP       |
| POST   | `/api/petugas/by-nips`     | ✅   | Multiple staff     |
| POST   | `/api/petugas/DanJabatan`  | ✅   | Staff + jabatan    |
| GET    | `/api/jabatan`             | ✅   | List jabatan       |

**Auth:** ✅ = Butuh JWT Token | ❌ = Public

---

## 📤 Upload ke GitHub

### 1. Buat Repository

1. Login ke [GitHub](https://github.com)
2. Klik **New repository**
3. Nama: `kanza-bridge`
4. Deskripsi: Sistem manajemen pengguna dan karyawan
5. Klik **Create repository**

### 2. Configure Git

```bash
cd kanza-bridge
git config --local user.name "Your Name"
git config --local user.email "your.email@example.com"
git remote add origin https://github.com/USERNAME/kanza-bridge.git
```

### 3. Commit & Push

```bash
git add .
git commit -m "Initial commit: Kanza Bridge v1.0"
git branch -M main
git push -u origin main
```

### 4. First Push Only

```bash
# Setup personal access token atau SSH key
# HTTPS: Gunakan personal access token sebagai password
# SSH: Setup SSH key di GitHub
```

**Common Issues:**

- **"repository not found"** → Check repository URL
- **"Permission denied"** → Setup SSH key atau Personal Access Token
- **".env ter-upload"** → Cek `.gitignore` sudah benar

---

## 🐛 Troubleshooting

### Database Connection Failed

```bash
# Check MySQL running
mysql -u sikuser -p sik

# Update .env dengan credentials benar
# Pastikan database & user sudah dibuat
```

### Class Not Found / 404 Error

```bash
# Clear cache
php spark cache:clear

# Regenerate autoloader
composer dump-autoload
```

### Permission Denied (writable/)

```bash
# Linux/Mac
chmod -R 755 writable/

# Windows (Command Prompt as Admin)
icacls writable /grant Users:F /T
```

### JWT Token Invalid

1. Pastikan `JWT_SECRET` di `.env` sudah set
2. Login ulang untuk dapatkan token baru
3. Check token belum expired (TTL 1 jam default)

### "The key is too short or not secure"

Pastikan `JWT_SECRET` di `.env` cukup panjang (min 16 karakter).

---

## 📁 Project Structure

```
kanza-bridge/
├── app/
│   ├── Config/
│   │   ├── Routes.php
│   │   ├── RoutesApi.php
│   │   └── Database.php
│   ├── Controllers/
│   │   ├── Auth.php
│   │   ├── Pegawai.php
│   │   ├── SysDashboard.php
│   │   └── Api/
│   │       ├── Auth.php
│   │       ├── User.php
│   │       ├── Pegawai.php
│   │       ├── Dokter.php
│   │       ├── Petugas.php
│   │       └── Jabatan.php
│   ├── Models/
│   ├── Views/
│   └── Filters/
├── public/
│   └── index.php
├── .env
├── .gitignore
├── composer.json
└── README.md
```

---

## ✅ Pre-Launch Checklist

Sebelum go live:

- [ ] Database sudah migrasi: `php spark migrate`
- [ ] Login test di web interface
- [ ] Test API endpoints dengan token
- [ ] `.env` sudah dikonfigurasi dengan production values
- [ ] `.env` ada di `.gitignore` ✅
- [ ] `vendor/` ada di `.gitignore` ✅
- [ ] GitHub repository setup
- [ ] First commit & push successful

---

## 📚 Technology Stack

| Layer            | Technology             |
| ---------------- | ---------------------- |
| Framework        | CodeIgniter 4          |
| Backend Language | PHP 8.1+               |
| Database         | MySQL 5.7+             |
| Authentication   | JWT (Firebase PHP-JWT) |
| ID Encoding      | Hashids                |
| Access Control   | RBAC (Role-Based)      |

---

## 🔐 Security Notes

- ✅ `.env` tidak di-track git (sensitif)
- ✅ JWT_SECRET harus kuat & unik
- ✅ Database password tidak hard-coded
- ✅ Admin credentials di `.env`
- ✅ CORS & Security headers configured

---

## 📞 Support & Help

- 📖 Read [CodeIgniter 4 Docs](https://codeigniter.com/user_guide/)
- 🔑 JWT docs: [Firebase PHP-JWT](https://github.com/firebase/php-jwt)
- 🎛️ Hashids: [Official Site](http://hashids.org/)

---

## 📄 Lisensi

Project ini dilisensikan di bawah MIT License. Lihat [LICENSE](LICENSE) file.

---

**Status: ✅ Production Ready**

Last Updated: February 17, 2026
