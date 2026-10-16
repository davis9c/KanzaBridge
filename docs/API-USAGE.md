# Penggunaan API

Base URL API adalah URL aplikasi diikuti path endpoint, misalnya
`http://localhost:8091/api/...` pada konfigurasi Docker default.

Terdapat dua mekanisme autentikasi API:

- **API v1 (`/api`)** menggunakan JWT bearer token.
- **API v2 (`/api/v2`)** menggunakan API key pada header `X-API-Key`.

Contoh di bawah menggunakan PowerShell. Ganti alamat server, kredensial, dan
placeholder token/key dengan nilai Anda sendiri.

## API v1 — JWT

### Login

`POST /api/auth/login` menerima `user_id` dan `password`:

```powershell
$body = @{ user_id = "<USER_ID>"; password = "<PASSWORD>" } | ConvertTo-Json
$response = Invoke-RestMethod -Method Post `
  -Uri "http://localhost:8091/api/auth/login" `
  -ContentType "application/json" `
  -Body $body
$response
```

Respons sukses berisi `token`, waktu kedaluwarsa, dan data pengguna. Kirim
token pada request ke endpoint yang dilindungi:

```http
Authorization: Bearer <JWT_TOKEN>
```

Contoh:

```powershell
$headers = @{ Authorization = "Bearer <JWT_TOKEN>" }
Invoke-RestMethod -Method Get `
  -Uri "http://localhost:8091/api/pegawai" `
  -Headers $headers
```

Route `POST /api/auth/refresh` tersedia dan menerima JSON
`{"refresh_token":"<REFRESH_TOKEN>"}`. Namun, respons login v1 saat ini tidak
mengembalikan refresh token; integrator perlu memiliki refresh token melalui
mekanisme lain sebelum menggunakan route ini.

### Endpoint v1

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| POST | `/api/auth/login` | Login dan menerbitkan JWT |
| POST | `/api/auth/refresh` | Memperbarui JWT dengan refresh token |
| GET | `/api/users` | Daftar user |
| GET | `/api/pegawai` | Daftar pegawai |
| POST | `/api/pegawai/by-ids` | Mencari pegawai berdasarkan ID |
| POST | `/api/pegawai/by-nik` | Mencari pegawai berdasarkan NIK |
| POST | `/api/pegawai/dokter` | Data pegawai dokter |
| POST | `/api/dokter` | Data dokter |
| POST | `/api/dokter/danSpesialis` | Dokter beserta spesialis |
| POST | `/api/petugas/DanJabatan` | Petugas beserta jabatan |
| POST | `/api/petugas/by-jbtn` | Petugas berdasarkan kode jabatan |
| POST | `/api/petugas/by-nips` | Petugas berdasarkan beberapa NIP |
| POST | `/api/petugas/by-nip` | Petugas berdasarkan NIP |
| GET | `/api/jabatan` | Daftar jabatan |
| GET | `/api/jabatan/with-petugas` | Jabatan beserta petugas |

Selain login dan refresh, endpoint v1 memerlukan JWT.

## API v2 — API key

API v2 menggunakan API key pada setiap request, termasuk endpoint login:

```http
X-API-Key: <API_KEY>
```

Key dan scope dikelola oleh administrator melalui halaman **Application** di
aplikasi. Berikan hanya scope yang dibutuhkan integrasi. Endpoint
`GET /api/v2/me` dapat digunakan untuk memeriksa informasi key dan daftar
scope yang diberikan.

Contoh pemeriksaan key:

```powershell
$headers = @{ "X-API-Key" = "<API_KEY>" }
Invoke-RestMethod -Method Get `
  -Uri "http://localhost:8091/api/v2/me" `
  -Headers $headers
```

Contoh endpoint pegawai:

```powershell
Invoke-RestMethod -Method Get `
  -Uri "http://localhost:8091/api/v2/pegawai" `
  -Headers $headers
```

Login aplikasi v2 menerima `user_id` dan `password`; request tetap memerlukan
API key:

```powershell
$body = @{ user_id = "<USER_ID>"; password = "<PASSWORD>" } | ConvertTo-Json
Invoke-RestMethod -Method Post `
  -Uri "http://localhost:8091/api/v2/auth/login" `
  -Headers $headers `
  -ContentType "application/json" `
  -Body $body
```

Endpoint login v2 memvalidasi kredensial dan mengembalikan profil pegawai; ia
tidak menerbitkan JWT. API v2 dirancang read-only untuk data SIMRS. POST pada
beberapa endpoint digunakan untuk mengirim parameter pencarian.

### Endpoint v2 dan scope

| Method | Endpoint | Scope |
| --- | --- | --- |
| GET | `/api/v2/me` | `meta.read` |
| POST | `/api/v2/auth/login` | `auth.login` |
| GET | `/api/v2/users` | `users.read` |
| GET | `/api/v2/pegawai` | `pegawai.read` |
| POST | `/api/v2/pegawai/by-ids` | `pegawai.by-ids` |
| POST | `/api/v2/pegawai/by-nik` | `pegawai.by-nik` |
| POST | `/api/v2/pegawai/dokter` | `dokter.read` |
| POST | `/api/v2/dokter` | `dokter.read` |
| POST | `/api/v2/dokter/dan-spesialis` | `dokter.by-spesialis` |
| GET | `/api/v2/jabatan` | `jabatan.read` |
| GET | `/api/v2/jabatan/with-petugas` | `jabatan.with-petugas` |
| POST | `/api/v2/petugas/dan-jabatan` | `petugas.read` |
| POST | `/api/v2/petugas/by-jbtn` | `petugas.by-jbtn` |
| POST | `/api/v2/petugas/by-nips` | `petugas.by-nips` |
| POST | `/api/v2/petugas/by-nip` | `petugas.by-nip` |

## Catatan

- JSON request harus menggunakan `Content-Type: application/json`.
- Lindungi JWT, refresh token, API key, dan kredensial login seperti password.
- Respons `401` umumnya berarti autentikasi tidak ada atau tidak valid;
  `403` berarti key tidak memiliki scope yang diminta; `429` berarti batas
  request key tercapai.
- Untuk parameter dan bentuk respons yang spesifik, lihat dokumentasi route
  terkait atau implementasi controller API.
