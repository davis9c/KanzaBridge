# Seeding Database

Dokumentasi lengkap seeding data awal **KanzaBridge**.

---

## Overview

Aplikasi memiliki **2 seeder**:

| Seeder | Lokasi | Keterangan |
| ------ | ---------- | ---------- |
| `RoleSeeder` | `app/Database/Seeds/RoleSeeder.php` | Mengisi role default (idempotent) |
| `DatabaseSeeder` | `app/Database/Seeds/DatabaseSeeder.php` | Placeholder kosong |

---

## Perintah Seed

### Jalankan Seeder Tertentu

```bash
php spark db:seed RoleSeeder
php spark db:seed DatabaseSeeder
```

### Jalankan Semua Seeder

```bash
php spark db:seed DatabaseSeeder
```

> **Catatan:** `DatabaseSeeder` saat ini merupakan placeholder kosong. Jalankan `RoleSeeder` secara terpisah jika diperlukan.

---

## RoleSeeder

### Apa yang Dilakukan

- Mengisi tabel `roles` dengan role default dari `RoleModel::DEFAULT_ROLES`
- **Tidak membuat user** — user pertama yang login otomatis menjadi SuperAdmin
- **Idempotent** — aman dijalankan berkali-kali

### Daftar Role Default

Role default didefinisikan di `App\Models\Access\RoleModel::DEFAULT_ROLES`. Setiap role memiliki:

| Field | Keterangan |
| ----- | ---------- |
| `name` | Nama role (unique) |
| `description` | Deskripsi role |
| `is_super` | 1 = SuperAdmin, 0 = regular admin |

### Kapan Menjalankan

Seeder ini **opsional** karena role default juga dibuat otomatis saat user pertama kali login melalui `RoleModel::ensureDefaults()`.

Jalankan seeder ini jika:
- Anda ingin menyiapkan data tanpa harus login terlebih dahulu
- Setelah restore backup database
- Saat setup lingkungan baru

---

## DatabaseSeeder

### Status Saat Ini

`DatabaseSeeder` adalah **placeholder kosong** — method `run()` belum melakukan apa pun.

```php
class DatabaseSeeder extends Seeder
{
    public function run() {}
}
```

### Pengembangan Masa Depan

Jika diperlukan seeding data lain (data jabatan, data petugas, dll), Anda bisa:

1. Tambahkan logika di `DatabaseSeeder::run()`
2. Atau buat seeder baru dan panggil dari sini

Contoh pengembangan:

```php
public function run()
{
    $this->call(RoleSeeder::class);
    // $this->call(JabatanSeeder::class);
}
```

---

## Perbandingan: Seeder vs Auto-Create

| Aspek | RoleSeeder | Auto-Create (saat login) |
| ----- | ---------- | ------------------------ |
| Kapan | Manual via CLI | Saat user pertama login |
| Target DB | `database.khanza` (UserGate) | `database.khanza` (UserGate) |
| Idempotent | Ya | Ya |
| Membuat user | Tidak | Tidak |

---

## Contoh Output Seed

```bash
$ php spark db:seed RoleSeeder

CodeIgniter v4.x.x Command Line Tool - Server Time: 2026-10-05 10:35:00 UTC+00:00

Seeding: App\Database\Seeds\RoleSeeder
Seeded:  App\Database\Seeds\RoleSeeder
```

---

## Verifikasi Setelah Seed

Cek role sudah terisi:

```sql
SELECT * FROM roles;
```

Output yang diharapkan:

| id | name | description | is_super |
| -- | ---- | ----------- | -------- |
| 1 | SuperAdmin | Super Administrator | 1 |
| 2 | Admin | Administrator | 0 |

> **Catatan:** Nilai `is_super` dan `description` akan disamakan dengan `RoleModel::DEFAULT_ROLES` jika role sudah ada.

---

## Troubleshooting

### Error "Table tidak ditemukan"

Pastikan migration sudah dijalankan terlebih dahulu:

```bash
php spark migrate
```

### Error "Column tidak ditemukan"

RoleSeeder menggunakan kolom `roles.description` dan `roles.is_super`. Pastikan skema sudah sesuai dengan migration terbaru.

### Seeder tidak melakukan apa pun

Itu normal untuk `DatabaseSeeder` — method `run()` memang sengaja dikosongkan sebagai placeholder.
