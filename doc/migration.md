# Migration Database

Dokumentasi lengkap migrasi database **KanzaBridge**.

---

## Overview

Aplikasi menggunakan **1 file migration** yang menggabungkan seluruh skema:

```
app/Database/Migrations/2026-10-02-000001_CreateKanzaBridgeSchema.php
```

Semua tabel dibuat di group `default` (database `kanza_bridge`), bukan di database `khanza`.

---

## Tabel yang Dibuat

### 1. Tabel Otorisasi Lokal

| Tabel | Primary Key | Relasi | Keterangan |
| ----- | ----------- | ------ | ---------- |
| `roles` | `id` | — | Role lokal (RBAC). Unique: `name`. Index: `is_super` |
| `users` | `id` | — | User lokal. Unique: `usergate_id`, `username`, `email`. Index: `status` |
| `user_roles` | `id` | `users.id`, `roles.id` | Relasi user ↔ role. Unique: (`user_id`, `role_id`). FK CASCADE |

### 2. Tabel API V2

| Tabel | Primary Key | Relasi | Keterangan |
| ----- | ----------- | ------ | ---------- |
| `api_applications` | `id` | — | Registrasi aplikasi pemanggil API. Unique: `name`, `code`. Index: `created_by` |
| `api_keys` | `id` | `api_applications.id` | API key (hash SHA-256). Unique: `key_hash`. FK CASCADE |
| `api_key_scopes` | `id` | `api_keys.id` | Permission per API key. Unique: (`api_key_id`, `scope`). FK CASCADE |

---

## Urutan Ekspansi

Tabel dibuat berurutan mengikuti dependensi foreign key:

```
roles
  └─ users
       └─ user_roles (FK → roles, users)

api_applications
  └─ api_keys (FK → api_applications)
       └─ api_key_scopes (FK → api_keys)
```

---

## Perintah Migration

### Jalankan Semua Migration

```bash
php spark migrate
```

### Cek Status Migration

```bash
php spark migrate:status
```

Menampilkan daftar migration dan statusnya (done/pending).

### Rollback Migration Terakhir

```bash
php spark migrate:rollback
```

Rollback satu batch terakhir. Untuk rollback semua:

```bash
php spark migrate:rollback all
```

### Refresh (Rollback + Re-migrate)

```bash
php spark migrate:refresh
```

**⚠️ Perhatian:** Perintah ini akan **menghapus semua data** lalu membuat ulang skema dari awal.

### Reset Semua Migration

```bash
php spark migrate:reset
```

Rollback semua migration yang sudah dijalankan.

---

## Fitur Migration

### Idempotent

Semua `createTable()` menggunakan argumen kedua `true` (IF NOT EXISTS), sehingga migration aman dijalankan berkali-kali tanpa menghapus data yang sudah ada.

### Rollback Otomatis

Method `down()` sudah diimplementasikan — tabel dihapus berurutan (anak lebih dulu) untuk menghindari pelanggaran foreign key.

### Urutan Dijaga

Urutan tabel di `up()` mengikuti dependensi foreign key. Tabel anak dibuat setelah induknya.

---

## Contoh Output Migrasi

```bash
$ php spark migrate

CodeIgniter v4.x.x Command Line Tool - Server Time: 2026-10-05 10:30:00 UTC+00:00

Running migrations...
    Running: App\Database\Migrations\2026-10-02-000001_CreateKanzaBridgeSchema
Migrations complete.
```

---

## Menambah Migration Baru

Jika diperlukan perubahan skema di masa depan:

```bash
php spark make:migration AddNewTable
```

File baru akan dibuat di `app/Database/Migrations/`. Edit file tersebut, lalu jalankan:

```bash
php spark migrate
```
