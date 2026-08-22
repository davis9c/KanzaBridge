# Panduan Program API V2 KanzaBridge

Dokumen ini menjelaskan modul yang telah dibuat untuk manajemen RESTful API pada aplikasi KanzaBridge, termasuk setup awal, manajemen user, token, serta endpoint API untuk database dan pegawai.

## 1. Tujuan Program

Program ini dibuat untuk mendukung pengelolaan API secara terstruktur dengan alur sebagai berikut:

- Setup awal akun super admin melalui halaman web
- Manajemen user dan role
- Manajemen token API
- Endpoint API v2 yang siap dikembangkan oleh developer lain
- Penyediaan endpoint untuk melihat daftar tabel database dan data pegawai

## 2. Struktur Modul yang Dibuat

### Controller API v2
- app/Controllers/ApiV2/Auth.php
  - Login API
  - Ambil profil login
  - Logout

- app/Controllers/ApiV2/Setup.php
  - Digunakan untuk pembuatan super admin (saat ini lebih diarahkan ke halaman web setup)

- app/Controllers/ApiV2/Users.php
  - Manajemen user melalui API

- app/Controllers/ApiV2/Tokens.php
  - Manajemen token melalui API

- app/Controllers/ApiV2/Produk.php
  - Contoh controller API untuk resource produk

- app/Controllers/ApiV2/DatabaseInfo.php
  - Menampilkan daftar tabel database
  - Menampilkan data pegawai
  - Menampilkan detail pegawai

### Controller halaman admin (web)
- app/Controllers/ApiV2/WebSetup.php
  - Halaman setup super admin

- app/Controllers/ApiV2/Dashboard.php
  - Halaman dashboard admin

- app/Controllers/ApiV2/WebUser.php
  - Halaman manajemen user

- app/Controllers/ApiV2/WebToken.php
  - Halaman manajemen token

### Model
- app/Models/ApiV2/RoleModel.php
  - Manajemen role

- app/Models/ApiV2/UserModel.php
  - Manajemen user

- app/Models/ApiV2/TokenModel.php
  - Manajemen token

### Database
- app/Database/Migrations/20260709120000_CreateKbApiV2Tables.php
  - Membuat tabel:
    - tb_kb_role
    - tb_kb_user
    - tb_kb_token

## 3. Alur Penggunaan

### A. Setup Awal
1. Buka halaman:
   - /kb-admin/setup
2. Isi data super admin
3. Simpan
4. Setelah berhasil, akun super admin dibuat

### B. Dashboard Admin
1. Buka halaman:
   - /kb-admin/dashboard
2. Dari halaman ini bisa masuk ke:
   - /kb-admin/users
   - /kb-admin/tokens

### C. Manajemen User
1. Buka:
   - /kb-admin/users
2. Bisa:
   - tambah user
   - edit user
   - hapus user

### D. Manajemen Token
1. Buka:
   - /kb-admin/tokens
2. Bisa:
   - buat token
   - cabut token

## 4. Endpoint API v2

Semua endpoint API v2 berada di prefix:

- /api/v2

### A. Auth
- POST /api/v2/auth/login
- GET /api/v2/auth/me
- POST /api/v2/auth/logout

### B. User
- GET /api/v2/users
- GET /api/v2/users/roles
- GET /api/v2/users/{id}
- POST /api/v2/users
- PUT /api/v2/users/{id}
- DELETE /api/v2/users/{id}

### C. Token
- GET /api/v2/tokens
- POST /api/v2/tokens
- DELETE /api/v2/tokens/{id}

### D. Produk contoh
- GET /api/v2/produk
- GET /api/v2/produk/{id}

### E. Database dan Pegawai
- GET /api/v2/database/tables
- GET /api/v2/pegawai/list
- GET /api/v2/pegawai/list/{id}

## 5. Format Respons API

Semua API dibuat dengan format respons yang konsisten:

```json
{
  "status": 200,
  "message": "OK",
  "data": {}
}
```

Untuk endpoint yang berisi list data, format umumnya adalah:

```json
{
  "status": 200,
  "message": "Data berhasil diambil",
  "items": [],
  "count": 0,
  "total": 0,
  "pagination": {
    "limit": 50,
    "offset": 0,
    "has_more": false
  },
  "meta": {
    "resource": "pegawai",
    "generated_at": "2026-07-09T00:00:00+07:00"
  }
}
```

## 6. Catatan Penting

- Modul halaman admin dibuat untuk kebutuhan administratif yang jarang dipakai.
- Fokus utama adalah API untuk manajemen user, role, token, serta data pegawai dan database.
- Struktur tabel untuk modul manajemen user/token menggunakan prefix:
  - tb_kb_user
  - tb_kb_role
  - tb_kb_token

## 7. Panduan Pengujian di Postman

### A. Persiapan Postman
1. Buka Postman.
2. Buat collection baru misalnya "KanzaBridge API V2".
3. Atur environment dengan variabel:
   - base_url = http://localhost:8080
   - token = (kosong dulu)

### B. Setup Super Admin
1. Buat request method POST.
2. URL: {{base_url}}/kb-admin/setup
3. Pada tab Body pilih form-data atau x-www-form-urlencoded.
4. Isi field:
   - username
   - email
   - full_name
   - password
5. Kirim request.

### C. Login API
1. Buat request method POST.
2. URL: {{base_url}}/api/v2/auth/login
3. Pada tab Body pilih raw -> JSON.
4. Contoh body:

```json
{
  "username": "superadmin",
  "password": "SuperAdmin123!"
}
```

5. Kirim request.
6. Salin nilai token dari response lalu simpan ke variabel token di Postman.

### D. Menggunakan Token untuk Endpoint Terproteksi
1. Pilih request yang membutuhkan auth.
2. Pada tab Headers tambahkan:
   - Authorization: Bearer {{token}}
3. Contoh endpoint:
   - GET {{base_url}}/api/v2/auth/me
   - GET {{base_url}}/api/v2/database/tables
   - GET {{base_url}}/api/v2/pegawai/list

### E. Contoh Request Lainnya

#### Login
```http
POST /api/v2/auth/login
Content-Type: application/json

{
  "username": "superadmin",
  "password": "SuperAdmin123!"
}
```

#### Ambil profil
```http
GET /api/v2/auth/me
Authorization: Bearer {{token}}
```

#### Ambil daftar tabel
```http
GET /api/v2/database/tables
Authorization: Bearer {{token}}
```

#### Ambil daftar pegawai
```http
GET /api/v2/pegawai/list?limit=10&offset=0&search=adi
Authorization: Bearer {{token}}
```

### F. Tips Pengujian
- Pastikan token disimpan di environment Postman.
- Jika mendapatkan response 401, token kemungkinan expired atau tidak valid.
- Jika mendapatkan 403, akun tidak memiliki hak akses yang cukup.
- Gunakan tab Tests di Postman untuk menyimpan token otomatis jika ingin dipermudah.

## 8. Langkah Pengujian

### A. Jalankan migrasi database
```bash
php spark migrate --all
```

### B. Buka halaman setup
```text
http://localhost:8080/kb-admin/setup
```

### C. Buat super admin
Isi form setup lalu simpan.

### D. Coba endpoint API
Contoh:
```bash
curl -X GET http://localhost:8080/api/v2/database/tables
```

### E. Coba endpoint pegawai
```bash
curl -X GET http://localhost:8080/api/v2/pegawai/list
```

## 8. Rencana Pengembangan Selanjutnya

Yang bisa dikembangkan berikutnya:
- autentikasi JWT yang lebih terintegrasi
- role-based access control yang lebih detail
- CRUD penuh untuk tabel produk dan tabel lain
- dokumentasi OpenAPI/Swagger
- log aktivitas admin
- pagination dan filter yang lebih kompleks

## 9. Penutup

Program ini sudah disiapkan sebagai fondasi untuk sistem manajemen RESTful API yang terstruktur, mudah dikembangkan, dan siap digunakan untuk pengujian lanjutan besok pagi.
