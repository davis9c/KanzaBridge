<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Skema awal KanzaBridge — SATU migration untuk seluruh tabel aplikasi.
 *
 * Semula skema ini tersebar di tujuh migration terpisah. Sekarang semuanya
 * digabung ke satu file karena project ini baru dimulai sebagai v1: belum
 * ada release sebelumnya yang perlu dilayani, jadi tidak ada riwayat yang
 * harus dijaga. Riwayat yang sudah berjalan di database lokal tidak perlu
 * direkonstruksi — yang penting skema akhirnya persis sama.
 *
 * Semua tabel berada di group `default` (khanzabridge) — BUKAN di `khanza`
 * (sik_beta). Tabel-tabel ini milik KanzaBridge; sik_beta hanya dibaca
 * sebagai sumber data yang diekspose lewat API.
 *
 * Catatan penting:
 *
 *   - Urutan tabel di up() mengikuti dependensi foreign key. Tabel anak
 *     harus dibuat setelah induknya, kalau tidak MySQL menolaknya.
 *   - dropColumn('users', 'nik') tidak ada lagi. Kolom itu pernah dibuat
 *     lalu dibuang karena fitur "Data Pegawai" dicabut; sekarang `users`
 *     langsung dibuat tanpanya. down() juga tidak perlu menambahkannya
 *     kembali, karena `users` ikut dihapus di sana.
 *   - Semua createTable() memakai argumen kedua `true` (IF NOT EXISTS).
 *     Itu membuat migration ini aman dijalankan pada database yang sudah
 *     terisi: jalankan ulang tidak akan menghapus data yang sudah ada.
 *   - down() membalik semuanya dalam satu langkah, anak lebih dulu. Karena
 *     ini v1, jalur rollback praktis tidak dipakai — tapi tetap ditulis
 *     supaya migrate:refresh tetap berfungsi.
 */
class CreateKanzaBridgeSchema extends Migration
{
    public function up()
    {
        $this->createRoles();
        $this->createUsers();
        $this->createUserRoles();

        $this->createApiApplications();
        $this->createApiKeys();
        $this->createApiScopes();
    }

    public function down()
    {
        // Child table harus lebih dulu, sama seperti user_roles.
        $this->forge->dropTable('api_key_scopes', true);
        $this->forge->dropTable('api_keys', true);
        $this->forge->dropTable('api_applications', true);
        $this->forge->dropTable('user_roles', true);
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('roles', true);
    }

    /* ------------------------------------------------------------------ *
     *  OTORISASI LOKAL
     * ------------------------------------------------------------------ */

    /**
     * Tabel role lokal.
     *
     * Role bersifat lokal: UserGate tidak berperan sama sekali di sini.
     * UserGate hanya menyimpan identitas akun (username, email, nama),
     * sedangkan tabel ini yang menentukan menu apa yang terlihat.
     */
    private function createRoles(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'is_super' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Catatan: argumen kedua addKey() berarti PRIMARY KEY, bukan unique.
        // Index unique harus lewat addUniqueKey().
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('is_super');

        $this->forge->createTable('roles', true);
    }

    /**
     * Tabel user lokal.
     *
     * Akun & password tetap hidup di UserGate; tabel ini hanya menyimpan
     * identitas yang sudah disalin (username/email/nama) plus keputusan
     * otorisasi lokal (status akses).
     *
     * Kolom `usergate_id` adalah UUID dari UserGate dan menjadi kunci
     * pencocokan saat user login.
     *
     * Tidak ada kolom `nik`: kolom itu pernah dipakai untuk menyambungkan
     * akun ke `pegawai` di khanza agar kartu "Data Pegawai" tampil di
     * halaman Profil. Fitur tersebut sudah dihapus, jadi kolomnya tidak
     * pernah dibuat di sini — bukan dibuat lalu dibuang.
     */
    private function createUsers(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usergate_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'ACTIVE',
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('usergate_id');
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('status');

        $this->forge->createTable('users', true);
    }

    /**
     * Tabel penghubung user <-> role.
     *
     * Mengganti pola ENUM pada tabel `users` supaya role bisa bertambah
     * tanpa perlu migrasi ulang, dan supaya satu user secara teori bisa punya
     * lebih dari satu role.
     */
    private function createUserRoles(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Satu user hanya boleh punya satu jenis role yang sama.
        // Argumen kedua addKey() berarti PRIMARY KEY — unique lewat addUniqueKey().
        $this->forge->addUniqueKey(['user_id', 'role_id']);
        $this->forge->addKey('user_id');
        $this->forge->addKey('role_id');

        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('user_roles', true);
    }

    /* ------------------------------------------------------------------ *
     *  APLIKASI PEMANGGIL API (API V2)
     * ------------------------------------------------------------------ */

    /**
     * Tabel aplikasi pemanggil API (register client integrasi).
     *
     * `created_by` menyimpan user lokal yang membuat aplikasi ini. Kolom itu
     * adalah dasar aturan kepemilikan: SuperAdmin boleh mengelola semua
     * aplikasi, sedangkan Admin hanya boleh mengelola aplikasi miliknya
     * sendiri (lihat AccessService::assertCanManageApiApplication).
     *
     * Nilai NULL berarti aplikasi dibuat di luar KanzaBridge (mis. diisi
     * manual di DB); aplikasi seperti itu hanya bisa dikelola SuperAdmin.
     */
    private function createApiApplications(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            // Slug singkat untuk dokumentasi dan pengecekan, mis. "simrs-khanza".
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'created_by' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Catatan: argumen kedua addKey() berarti PRIMARY KEY, bukan unique.
        // Index unique harus lewat addUniqueKey().
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('created_by');

        $this->forge->createTable('api_applications', true);
    }

    /**
     * Tabel API key untuk API V2.
     *
     * Yang disimpan HANYA hash SHA-256 dari key. Key plaintext tidak pernah
     * disimpan dan tidak pernah bisa dibaca lagi — satu-satunya kesempatan
     * administrator melihat key penuh adalah sesaat setelah key dibuat.
     * `key_prefix` menyimpan 16 karakter pertama supaya key masih bisa
     * dikenali di daftar tanpa menyimpan rahasia yang bisa dipakai.
     */
    private function createApiKeys(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'application_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // Nama alkohol dari key, mis. "Produksi", "Staging", "Modul Registrasi".
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'key_prefix' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
            ],
            // hash('sha256', key) — satu-satunya bahan untuk pencocokan.
            'key_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'ACTIVE',
            ],
            // NULL = key tidak pernah kedaluwarsa.
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            // NULL atau 0 = tanpa batas request per menit.
            'rate_limit_per_minute' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Satu hash satu key. Ini yang membuat pencarian O(1) saat autentikasi.
        $this->forge->addUniqueKey('key_hash');
        $this->forge->addKey('key_prefix');
        $this->forge->addKey('application_id');
        $this->forge->addKey('status');

        $this->forge->addForeignKey('application_id', 'api_applications', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('api_keys', true);
    }

    /**
     * Tabel permission (scope) per API key.
     *
     * Satu baris = satu endpoint yang boleh diakses oleh satu key. Daftar
     * kode scope yang valid didefinisikan di Config\ApiScope, bukan di sini,
     * supaya tabel ini hanya berisi pilihan yang sudah disetujui aplikasi.
     *
     * Sengaja dipisah dari `api_keys`: rotasi key (cabut yang lama, buat
     * yang baru) tidak boleh mengubah permission yang sudah disepakati —
     * jadi key baru disalin scope-nya secara eksplisit.
     */
    private function createApiScopes(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'api_key_id' => [
                'type'       => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // Kode scope, mis. "dokter.read". Rujukan: Config\ApiScope::$endpoints.
            'scope' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Satu key hanya boleh punya satu baris untuk scope yang sama.
        // Argumen kedua addKey() berarti PRIMARY KEY — unique lewat addUniqueKey().
        $this->forge->addUniqueKey(['api_key_id', 'scope']);
        $this->forge->addKey('api_key_id');

        $this->forge->addForeignKey('api_key_id', 'api_keys', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('api_key_scopes', true);
    }
}