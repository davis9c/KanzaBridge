# V2 Development Constitution — KanzaBridge

## 1. Tujuan Dokumen

Dokumen ini adalah **aturan utama pengembangan V2 KanzaBridge** dan harus dibaca oleh OpenCode sebelum melakukan perubahan apa pun.

V2 boleh direfactor dan dibangun ulang secara menyeluruh karena V2 belum menjadi sistem production yang harus dipertahankan.

Dokumen ini menjadi batasan teknis, arsitektur, keamanan, dan urutan implementasi.

---

# 2. Batasan Mutlak terhadap V1

## 2.1 V1 adalah sistem production

V1 sudah berjalan dan **TIDAK BOLEH DIUBAH**.

V1 berada di luar project/folder V2.

OpenCode:

- tidak boleh mencari dan mengubah source code V1
- tidak boleh memindahkan file V1
- tidak boleh melakukan refactor terhadap V1
- tidak boleh mengubah database V1 hanya demi kebutuhan V2
- tidak boleh mengubah konfigurasi V1
- tidak boleh mengubah route/API V1
- tidak boleh mengubah authentication V1
- tidak boleh menganggap V1 sebagai bagian dari source tree V2

Jika suatu kebutuhan V2 membutuhkan perubahan pada V1, **STOP dan minta persetujuan developer**.

## 2.2 V2 boleh dirombak total

Semua kode yang berada di project V2 boleh:

- di-refactor
- dipindahkan
- dihapus
- diganti
- didesain ulang
- dibuat ulang

Tidak perlu mempertahankan arsitektur V2 lama jika bertentangan dengan arsitektur baru.

Kode V2 lama diperlakukan sebagai referensi, bukan kontrak.

---

# 3. Prinsip Utama Arsitektur

V2 adalah **Control Plane / API Access Management** yang mengelola:

- User
- Authentication
- Role
- Permission
- Tenant
- Application
- API Key
- API Key Scope
- Plan
- Quota
- Rate Limit
- Audit Log
- API Usage

Business data milik tenant berada pada database tenant.

Arsitektur utama:

```text
                    KANZABRIDGE V2
                         |
              +----------+----------+
              |                     |
              v                     v
       MANAGEMENT DB           TENANT DATABASE
       database.default        dynamic connection
              |                     |
       Users / Roles          Business Data
       Permissions            Pegawai
       Tenants                Dokter
       Applications           Petugas
       API Keys               Jabatan
       Plans                  dll.
       Audit Logs
       Usage
```

---

# 4. Database.default

## 4.1 Management Database

`database.default` adalah **Management Database V2**.

Jangan membuat koneksi bernama `management` hanya untuk menggantikan `database.default`, kecuali ada alasan teknis yang disetujui developer.

Model berikut menggunakan `database.default`:

- UserModel
- RoleModel
- PermissionModel
- TenantModel
- ApplicationModel
- ApiKeyModel
- ApiKeyPermissionModel
- PlanModel
- AuditLogModel
- ApiUsageModel
- dan seluruh model Control Plane

## 4.2 Tenant Database

Database tenant **bukan `database.default`**.

Tenant database harus di-resolve secara dinamis.

Konsep:

```text
TenantManager
    |
    +-- Tenant A -> Database A
    +-- Tenant B -> Database B
    +-- Tenant C -> Database C
```

Business model tidak boleh menyimpan nama database tenant secara hard-coded.

Business model tidak boleh membuat koneksi tenant sendiri-sendiri.

Semua resolusi koneksi tenant harus melalui abstraction seperti:

- TenantManager
- TenantContext
- TenantDatabaseResolver

Nama class boleh disesuaikan dengan struktur V2, tetapi tanggung jawab harus tetap terpisah.

---

# 5. Isolasi Tenant

Versi awal menggunakan:

> **1 Tenant = 1 Database**

Contoh:

```text
Management DB
|
+-- Tenant A -> sik_rs_a
+-- Tenant B -> sik_rs_b
+-- Tenant C -> sik_rs_c
```

Tenant A tidak boleh membaca Tenant B.

Tenant B tidak boleh membaca Tenant C.

Jangan menggunakan `tenant_id` pada tabel business sebagai mekanisme isolasi utama.

`tenant_id` digunakan pada Management DB untuk identitas dan relasi tenant.

---

# 6. TenantManager

Semua akses database tenant harus melalui abstraction.

Flow:

```text
Request
  |
  v
Resolve Tenant
  |
  v
TenantManager
  |
  v
Tenant Configuration
  |
  v
Create/Get Database Connection
  |
  v
Tenant Model / Business Logic
```

Jangan melakukan:

```php
db_connect('sik_rs_a');
```

secara langsung di controller atau model business.

Jangan membuat nama database tenant tersebar di source code.

---

# 7. Management Database Schema

Schema minimal yang harus dirancang:

```text
users
roles
permissions
user_roles
role_permissions

tenants

applications

api_keys
api_key_permissions

plans

audit_logs
api_usage
```

Schema boleh berkembang selama implementasi.

Gunakan migration CI4.

Jangan melakukan perubahan schema Management DB secara manual tanpa migration.

---

# 8. Superadmin Bootstrap

Superadmin ditentukan **saat pertama kali aplikasi V2 di-load/dijalankan**.

Bootstrap hanya dilakukan jika Management DB belum memiliki Superadmin.

Konfigurasi awal berasal dari environment:

```env
SUPERADMIN_USERNAME=admin
SUPERADMIN_PASSWORD=admin
```

Nilai tersebut hanya digunakan untuk membuat akun awal.

Password plaintext:

- tidak boleh disimpan
- tidak boleh masuk database
- tidak boleh ditulis ke log
- tidak boleh ditampilkan kembali setelah bootstrap

Password harus menggunakan secure password hashing.

---

# 9. First Login Superadmin

Setelah bootstrap:

```text
SUPERADMIN_USERNAME
        +
SUPERADMIN_PASSWORD
        |
        v
     Login
        |
        v
force_password_change = true
        |
        v
Change Password
        |
        v
force_password_change = false
        |
        v
Dashboard
```

Jika `force_password_change = true`, Superadmin tidak boleh mengakses dashboard sebelum mengganti password.

Route yang diperbolehkan minimal:

```text
login
logout
change-password
```

Setelah password berhasil diganti, akses normal diberikan.

---

# 10. JWT dan Session

JWT dan session digunakan untuk **user/dashboard authentication**.

API Key digunakan untuk **application/external API authentication**.

Jangan mencampurkan kedua konsep tersebut.

```text
Dashboard User
    |
    v
Login
    |
    v
JWT / Session


External Application
    |
    v
X-API-Key
    |
    v
API
```

---

# 11. API Key

API Key adalah credential aplikasi, bukan credential login dashboard.

Relasi:

```text
Tenant
   |
   +-- Application
          |
          +-- API Key
```

API Key harus dibuat menggunakan cryptographically secure random value.

Secret asli hanya boleh ditampilkan **sekali saat pembuatan**.

Database tidak boleh menyimpan secret plaintext.

Minimal simpan:

```text
key_prefix
key_hash
```

API Key harus memiliki lifecycle:

```text
Active
Revoked
Expired
Suspended
```

API Key yang revoked tidak boleh digunakan.

---

# 12. Permission / Scope

API Key tidak otomatis memiliki seluruh akses.

Gunakan scope/permission.

Contoh:

```text
pegawai.read
pegawai.write
dokter.read
petugas.read
```

Flow request:

```text
API Key
  |
  v
Validate Key
  |
  v
Validate Status
  |
  v
Validate Expiration
  |
  v
Resolve Application
  |
  v
Resolve Tenant
  |
  v
Check Permission
  |
  v
Rate Limit
  |
  v
Business API
```

Permission gagal:

```http
403 Forbidden
```

Credential tidak valid:

```http
401 Unauthorized
```

---

# 13. Rate Limit

Rate limit diterapkan setelah API Key berhasil diidentifikasi.

Response saat limit terlampaui:

```http
429 Too Many Requests
```

Implementasi awal boleh sederhana.

Redis dapat digunakan pada tahap scaling berikutnya.

Jangan memperkenalkan Redis hanya untuk menyelesaikan kebutuhan dasar V2 jika belum diperlukan.

---

# 14. Audit Log

Aktivitas penting harus dicatat.

Minimal:

- Login
- Logout
- Password Change
- User Created
- User Updated
- User Disabled
- Tenant Created
- Tenant Updated
- Application Created
- API Key Created
- API Key Revoked
- Permission Changed
- Plan Changed
- Quota Changed

Jangan pernah menyimpan:

- password plaintext
- API Key secret
- JWT secret
- database password plaintext dalam metadata audit

---

# 15. API Usage

Sistem harus dapat mencatat minimal:

- API Key
- Application
- Tenant
- Request count
- Success count
- Failed count
- Rate limit hits
- Last used

API Usage tidak boleh menyimpan secret credential.

---

# 16. Security Rules

Wajib:

- Password menggunakan secure hashing
- API Key secret di-hash
- API Key hanya ditampilkan sekali
- JWT memiliki expiration
- API Key memiliki expiration
- API Key dapat di-revoke
- Permission diverifikasi server-side
- Rate limiting
- Input validation
- Audit logging
- CSRF protection untuk dashboard sesuai kebutuhan CI4
- Secret tidak masuk log
- `.env` tidak di-commit
- Jangan hard-code credential
- Jangan hard-code tenant database password
- Jangan mengekspos credential pada response API

Jika ada konflik antara kenyamanan implementasi dan security, prioritaskan security.

---

# 17. Struktur Code

Struktur boleh berubah selama refactor.

Target konseptual:

```text
app/
├── Config/
├── Controllers/
│   ├── Auth/
│   ├── Admin/
│   └── Api/
├── Models/
│   ├── Management/
│   └── Tenant/
├── Services/
│   ├── AuthenticationService.php
│   ├── ApiKeyService.php
│   ├── PermissionService.php
│   ├── TenantManager.php
│   ├── RateLimitService.php
│   └── AuditLogService.php
├── Filters/
│   ├── JwtAuthFilter.php
│   ├── ApiKeyFilter.php
│   ├── PermissionFilter.php
│   └── RateLimitFilter.php
└── Views/
```

Struktur final boleh berbeda jika alasan arsitekturalnya lebih baik.

---

# 18. Routing

V2 harus memiliki routing yang jelas dan tidak ambigu.

Jika API lama V2 belum memiliki kontrak production yang wajib dipertahankan, route V2 boleh didesain ulang.

Gunakan versioning jika diperlukan:

```text
/api/v2/...
```

Jangan mengubah endpoint V1 di luar project V2.

---

# 19. Prinsip Refactoring

Saat menemukan kode V2 lama:

1. pahami fungsinya
2. tentukan apakah masih relevan
3. pertahankan hanya bagian yang sesuai arsitektur baru
4. refactor atau hapus jika perlu

Jangan melakukan refactor kosmetik tanpa tujuan.

Prioritas:

```text
Architecture
Security
Correctness
Testability
Maintainability
Performance
Style
```

---

# 20. Aturan OpenCode

OpenCode harus mengikuti aturan berikut.

## Sebelum coding

- baca dokumen ini
- inspect repository V2
- pahami struktur existing
- identifikasi dependency
- identifikasi konfigurasi
- identifikasi migration existing
- identifikasi database connection existing

## Saat coding

- kerjakan satu milestone pada satu waktu
- jangan mengerjakan seluruh roadmap sekaligus
- jangan membuat asumsi tentang struktur database yang belum diperiksa
- gunakan migration
- gunakan service untuk business logic yang kompleks
- gunakan dependency injection jika sesuai CI4
- jangan menyebarkan tenant connection logic
- jangan menyimpan secret plaintext

## Setelah coding

- jalankan test
- jalankan lint/static check jika tersedia
- jalankan migration test pada environment development
- periksa perubahan git diff
- jelaskan file yang berubah
- jelaskan risiko perubahan
- jangan melanjutkan milestone berikutnya sebelum milestone saat ini tervalidasi

---

# 21. Stop Conditions

OpenCode **WAJIB berhenti dan meminta konfirmasi developer** jika:

1. menemukan kebutuhan mengubah V1
2. menemukan kebutuhan mengubah database V1
3. menemukan credential production
4. menemukan schema tenant yang tidak jelas
5. perubahan dapat menyebabkan data tenant bocor
6. perubahan authentication berpotensi mem-bypass authorization
7. perubahan database connection berpotensi mengarah ke tenant yang salah
8. terdapat konflik antara requirement lama dan dokumen ini
9. tidak yakin apakah sebuah resource termasuk Management DB atau Tenant DB
10. perubahan membutuhkan destructive migration terhadap data yang sudah ada

---

# 22. Development Milestones

Implementasi wajib dilakukan bertahap.

## Milestone 0 — Audit

Tidak boleh mengubah kode.

Output:

```text
docs/V2_REFACTOR_PLAN.md
```

Berisi:

- existing architecture
- existing database
- existing authentication
- existing routes
- existing models
- existing filters
- existing migrations
- bagian V2 yang dapat dipertahankan
- bagian V2 yang sebaiknya dirombak
- risiko

---

## Milestone 1 — Management Database

- finalisasi schema
- migration
- database.default
- seed dasar
- migration test

Belum membuat API Key.

---

## Milestone 2 — Authentication

- User
- Role
- Permission
- Login
- Logout
- Session/JWT
- Superadmin bootstrap
- Force password change

---

## Milestone 3 — Tenant

- Tenant model
- Tenant CRUD
- TenantManager
- Tenant connection resolver
- Tenant connection test

---

## Milestone 4 — Application

- Application model
- Application CRUD
- hubungan Application → Tenant

---

## Milestone 5 — API Key

- Generate
- Hash
- Show once
- List
- Revoke
- Expiration
- Status

---

## Milestone 6 — Permission

- Scope
- API Key permissions
- Permission middleware/filter
- 401 / 403 handling

---

## Milestone 7 — Rate Limit

- Rate limit
- 429 response
- basic usage counting

---

## Milestone 8 — Audit & Usage

- Audit logs
- API usage
- statistics

---

## Milestone 9 — Dashboard

- Superadmin dashboard
- User management
- Tenant management
- Application management
- API Key management
- Usage
- Audit

---

# 23. Testing Strategy

Setiap milestone harus memiliki test.

Minimal:

```text
Management DB connection
Superadmin bootstrap
Superadmin login
Forced password change
Tenant resolution
Tenant database isolation
API Key validation
API Key revocation
Permission validation
Rate limit
Audit log
```

Sangat penting:

```text
Tenant A request → Tenant A DB
Tenant B request → Tenant B DB
Tenant A request → MUST NOT access Tenant B DB
```

---

# 24. Data Isolation Test

Wajib membuat skenario test:

```text
Tenant A
    |
    +-- data A


Tenant B
    |
    +-- data B
```

Kemudian:

```text
Credential Tenant A
    ↓
Request
    ↓
Expected: data A
```

dan:

```text
Credential Tenant A
    ↓
Attempt access Tenant B
    ↓
Expected: DENIED
```

Tidak boleh ada fallback otomatis ke database tenant lain.

---

# 25. Definition of Done

Sebuah milestone dianggap selesai jika:

- implementasi selesai
- migration berjalan
- test berhasil
- tidak ada credential plaintext
- tidak ada tenant cross-access
- tidak mengubah V1
- git diff telah diperiksa
- dokumentasi milestone diperbarui

---

# 26. Prinsip Terakhir

Jangan mengejar jumlah fitur.

Prioritas utama:

```text
ISOLATION
   ↓
SECURITY
   ↓
CORRECTNESS
   ↓
ARCHITECTURE
   ↓
MAINTAINABILITY
   ↓
FEATURES
```

V2 harus dibangun sebagai **platform reusable**, bukan sekadar aplikasi CRUD.

Target akhirnya:

```text
                KANZABRIDGE V2
                       |
             +---------+---------+
             |                   |
       CONTROL PLANE        TENANT LAYER
             |                   |
       Management DB        Tenant DB
             |                   |
        Auth / RBAC        Business Data
        API Keys
        Applications
        Tenants
        Quota
        Rate Limit
        Audit
        Usage
```

**V1 tetap berjalan sendiri dan tidak disentuh.**

**V2 boleh dirombak total.**

**`database.default` adalah Management DB V2.**

**Tenant menggunakan database terpisah melalui TenantManager.**

Dokumen ini adalah baseline dan batasan utama seluruh pekerjaan refactoring V2.


---

# 27. UI/UX dan Developer Experience

## 27.1 Prinsip Utama

V2 harus dibuat agar **mudah dipahami dan mudah dipelajari oleh developer yang mengelolanya**.

Jangan mengejar desain UI/UX atau arsitektur yang terlalu kompleks hanya karena terlihat modern.

Prioritas:

```text
Mudah dipahami
      ↓
Mudah dipelajari
      ↓
Mudah dirawat
      ↓
Konsisten
      ↓
Baru kemudian estetika/kompleksitas
```

---

# 28. UI Framework

UI V2 wajib menggunakan:

> **Bootstrap**

Gunakan komponen Bootstrap yang umum dan familiar.

Prioritaskan:

- Navbar
- Sidebar
- Card
- Table
- Form
- Button
- Modal
- Alert
- Badge
- Dropdown
- Pagination
- Tabs jika memang diperlukan

Hindari penggunaan UI framework tambahan tanpa alasan yang jelas.

Jangan membuat design system yang kompleks jika Bootstrap sudah dapat menyelesaikan kebutuhan.

---

# 29. Prinsip UI/UX

UI harus:

- sederhana
- bersih
- konsisten
- mudah dipahami
- familiar
- responsif
- tidak terlalu banyak animasi
- tidak terlalu banyak efek visual
- tidak membutuhkan pemahaman UI/UX tingkat lanjut untuk digunakan

Jangan menggunakan:

- layout yang terlalu eksperimental
- navigasi yang membingungkan
- terlalu banyak nested menu
- interaksi tersembunyi
- animasi berlebihan
- dashboard yang terlalu padat
- komponen custom yang sebenarnya sudah tersedia di Bootstrap

Jika sebuah kebutuhan dapat dibuat dengan:

```text
Button
Form
Table
Modal
Alert
Card
```

maka gunakan komponen tersebut.

---

# 30. V1 / Program Dasar sebagai Referensi UI/UX

Sebelum membuat halaman V2 baru, OpenCode harus mempelajari **V1 atau program dasar yang tersedia sebagai referensi UI/UX**, jika source/referensi tersebut dapat diakses.

Tujuannya bukan menyalin seluruh implementasi V1.

Tujuannya adalah memahami:

- pola navigasi
- struktur halaman
- cara menampilkan data
- pola form
- pola tombol
- pola tabel
- istilah yang digunakan
- posisi menu
- pola feedback/error
- kebiasaan pengguna

V2 harus terasa familiar bagi developer dan pengguna yang sudah terbiasa dengan aplikasi dasar/V1.

Jika UI V1 memiliki pola yang baik, pertahankan konsepnya.

Jika ada pola V1 yang kurang baik, boleh diperbaiki di V2 dengan tetap mempertahankan prinsip familiar dan sederhana.

**Jangan membuat UI baru yang kompleks hanya karena V2 adalah versi baru.**

---

# 31. Layout V2

Gunakan pola layout yang sederhana.

Contoh:

```text
+------------------------------------------------------+
| Navbar                                               |
+----------------+-------------------------------------+
| Sidebar        | Content                             |
|                |                                     |
| Dashboard      | Page Title                          |
| Users          |                                     |
| Tenants        | [Action Button]                     |
| Applications   |                                     |
| API Keys       | +-------------------------------+   |
| Permissions    | | Table                         |   |
| Plans          | |                               |   |
| Usage          | |                               |   |
| Audit Logs     | +-------------------------------+   |
+----------------+-------------------------------------+
```

Navigasi harus mudah ditemukan.

Menu utama tidak boleh terlalu dalam.

---

# 32. CRUD UI

Untuk halaman CRUD, gunakan pola yang konsisten.

Contoh:

```text
Users
-------------------------------------------------
[ Search ] [ Filter ]                 [ + Add ]

-------------------------------------------------
| Username | Name | Role | Status | Action      |
-------------------------------------------------
| admin    | ...  | ...  | Active | Edit Detail |
| user01   | ...  | ...  | Active | Edit Detail |
-------------------------------------------------

Pagination
```

Action umum:

- View
- Edit
- Delete/Disable

Jangan membuat pola action yang berbeda-beda tanpa alasan.

---

# 33. Form UI

Form harus jelas.

Gunakan:

```text
Label
Input
Help text jika diperlukan
Validation message
```

Jangan menyembunyikan informasi penting di balik banyak modal atau step.

Untuk proses sederhana, gunakan halaman/form biasa.

Gunakan modal hanya jika memang membuat proses lebih sederhana.

---

# 34. Error dan Feedback

User harus selalu mendapatkan feedback yang jelas.

Gunakan Bootstrap Alert/Toast/Modal sesuai kebutuhan.

Contoh:

```text
Success:
"Tenant berhasil dibuat."

Error:
"Tenant gagal dibuat. Periksa koneksi database."

Validation:
"Username wajib diisi."

Authorization:
"Anda tidak memiliki akses ke halaman ini."
```

Hindari pesan error teknis mentah kepada user.

Contoh yang tidak boleh ditampilkan:

```text
SQLSTATE[HY000]...
Call to undefined method...
Stack trace...
```

Detail teknis hanya untuk log/development.

---

# 35. Dashboard

Dashboard jangan dibuat terlalu rumit.

Prioritaskan informasi penting.

Contoh:

```text
+----------------+----------------+
| Total Users    | Total Tenants  |
| 120            | 8              |
+----------------+----------------+

+----------------+----------------+
| Active API Key | Requests Today |
| 35             | 12,432         |
+----------------+----------------+

Recent Activity
----------------------------------
| Time | User | Action |
----------------------------------
```

Chart hanya digunakan jika benar-benar membantu memahami data.

Jangan membuat dashboard penuh grafik hanya untuk terlihat modern.

---

# 36. Struktur Controller

Controller harus mudah dipelajari.

Utamakan alur sederhana:

```text
Request
   ↓
Controller
   ↓
Validation
   ↓
Model / Service
   ↓
Response / View
```

Controller tidak boleh dipenuhi business logic kompleks.

Namun jangan membuat abstraction berlebihan.

Untuk CRUD sederhana, pola berikut diperbolehkan:

```text
Controller
    ↓
Model
    ↓
Database
```

Tidak wajib:

```text
Controller
 ↓
Interface
 ↓
Factory
 ↓
Repository
 ↓
Service
 ↓
Manager
 ↓
Adapter
 ↓
Model
 ↓
Database
```

Gunakan abstraction hanya jika memang memberikan manfaat nyata.

---

# 37. Struktur Model

Model harus mudah dipahami.

Model bertanggung jawab terhadap:

- database table
- allowed fields
- validation yang relevan
- query database
- relasi/query helper yang jelas

Hindari model yang terlalu "ajaib".

Nama method harus jelas.

Contoh yang baik:

```php
getById($id)
getByUsername($username)
getActiveUsers()
findByTenant($tenantId)
```

Hindari nama method yang tidak menjelaskan maksudnya.

---

# 38. Service Layer

Service digunakan ketika business logic memang cukup kompleks.

Contoh yang layak menggunakan Service:

```text
ApiKeyService
TenantManager
AuthenticationService
RateLimitService
AuditLogService
```

CRUD sederhana tidak harus dipaksa menggunakan Service.

Tujuannya:

> **Abstraction harus membantu developer, bukan menambah beban belajar.**

---

# 39. Naming Convention

Gunakan nama yang jelas dan konsisten.

Contoh:

```text
UserModel
TenantModel
ApplicationModel
ApiKeyModel

AuthenticationService
ApiKeyService
TenantManager

UserController
TenantController
ApplicationController
ApiKeyController
```

Hindari singkatan yang tidak umum.

Nama harus menjelaskan fungsi class.

---

# 40. Dokumentasi Kode

Kode kompleks harus memiliki komentar secukupnya.

Komentar harus menjelaskan:

- mengapa sesuatu dilakukan
- batasan tertentu
- alasan security
- alasan arsitektural

Jangan memenuhi kode dengan komentar yang hanya mengulang nama method.

Contoh yang berguna:

```php
// Tenant connection harus dibuat melalui TenantManager
// agar business model tidak dapat secara tidak sengaja
// menggunakan database tenant lain.
```

---

# 41. Prinsip "Boring Technology"

Untuk V2:

> **Gunakan teknologi yang sederhana, stabil, dan familiar.**

Jangan menambahkan dependency hanya karena populer.

Sebelum menambahkan library baru, pertimbangkan:

1. Apakah Bootstrap/CI4/PHP sudah bisa menyelesaikan?
2. Apakah library benar-benar diperlukan?
3. Apakah library menambah kompleksitas maintenance?
4. Apakah developer lain akan mudah memahaminya?

Jika jawabannya tidak jelas, jangan tambahkan dependency.

---

# 42. Konsistensi Lebih Penting daripada Keunikan

Semua halaman V2 harus mengikuti pola yang sama.

Contoh:

```text
Users
Tenants
Applications
API Keys
Permissions
```

sebisa mungkin memiliki:

```text
Page Title
Toolbar
Search/Filter
Table
Action
Pagination
```

Dengan demikian developer tidak perlu mempelajari pola UI baru pada setiap halaman.

---

# 43. Prinsip untuk OpenCode

Ketika OpenCode membuat UI atau struktur kode, selalu tanyakan:

```text
Apakah ini mudah dipahami?
Apakah ini familiar?
Apakah ini perlu?
Apakah Bootstrap/CI4 sudah menyediakan solusi?
Apakah abstraction ini benar-benar membantu?
Apakah developer baru dapat memahami alurnya?
```

Jika solusi lebih kompleks tetapi manfaatnya kecil:

> pilih solusi yang lebih sederhana.

---

# 44. Definition of Good V2

V2 yang baik bukan V2 yang memiliki kode paling canggih.

V2 yang baik adalah V2 yang:

```text
Aman
  +
Terisolasi
  +
Benar
  +
Mudah dipahami
  +
Mudah dirawat
  +
Mudah dikembangkan
```

Developer harus dapat membuka sebuah Controller dan memahami alurnya tanpa harus membaca banyak abstraction terlebih dahulu.

Developer harus dapat membuka sebuah halaman UI dan langsung memahami cara menggunakannya.

---

# 45. Aturan Tambahan Sebelum Implementasi UI

Sebelum membuat UI baru:

1. Periksa UI/pola dari V1 atau program dasar yang tersedia.
2. Identifikasi pola yang sudah familiar.
3. Gunakan Bootstrap.
4. Buat versi paling sederhana terlebih dahulu.
5. Jangan menambahkan komponen kompleks tanpa kebutuhan.
6. Pastikan desktop dan mobile tetap usable.
7. Pastikan action utama mudah ditemukan.
8. Gunakan istilah yang konsisten dengan aplikasi yang sudah ada.

Jika OpenCode tidak dapat mengakses V1/program dasar, **jangan mengarang pola UI yang kompleks**. Gunakan pola Bootstrap standar yang sederhana dan laporkan bahwa referensi V1 tidak tersedia.
