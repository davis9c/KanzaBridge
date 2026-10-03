<?php

namespace App\Models\Access;

use App\Libraries\UserGate\TokenStore;
use App\Libraries\UserGate\UserGateClient;
use App\Libraries\UserGate\UserGateException;
use Config\Access as AccessConfig;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Otot otorisasi lokal KanzaBridge.
 *
 * Dipisah dari model supaya controller tidak perlu tahu detail
 * (UserGate, session, transaksi) hanya untuk memeriksa "apakah user ini
 * SuperAdmin?".
 *
 * Aturan main:
 *
 *   1. UserGate menentukan SIAPA orangnya (autentikasi).
 *   2. DB lokal menentukan APA yang boleh dia lakukan (otorisasi).
 *      Field `roles` dari UserGate diabaikan sepenuhnya.
 *   3. User lokal pertama yang berhasil login otomatis menjadi SuperAdmin.
 *   4. User tanpa role tetap boleh login, tetapi tidak punya menu.
 */
class AccessService
{
    private UserGateClient $gate;
    private TokenStore $tokens;
    private UserModel $users;
    private RoleModel $roles;
    private UserRoleModel $userRoles;
    private AccessConfig $config;

    /**
     * @param UserGateClient|null $gate Dipakai untuk menyuntik client tiruan saat pengujian.
     */
    public function __construct(?UserGateClient $gate = null)
    {
        $this->gate      = $gate ?? new UserGateClient();
        $this->tokens    = new TokenStore();
        $this->users     = new UserModel();
        $this->roles     = new RoleModel();
        $this->userRoles = new UserRoleModel();
        $this->config    = config(AccessConfig::class);
    }

    /* ------------------------------------------------------------------ *
     *  AUTENTIKASI
     * ------------------------------------------------------------------ */

    /**
     * Login lewat UserGate lalu upkeep user + role lokal.
     *
     * @return array{user: array<string,mixed>, roles: list<string>, is_super_admin: bool, is_first_user: bool}
     *
     * @throws UserGateException Bila kredensial ditolak atau UserGate tidak dapat dihubungi.
     * @throws RuntimeException  Bila akun tidak aktif, dinonaktifkan lokal, atau penyimpanan lokal gagal.
     */
    public function authenticate(string $username, string $password): array
    {
        $auth     = $this->gate->login($username, $password);
        $remote   = $auth['user'];
        $remoteId = trim((string) ($remote['id'] ?? ''));

        if ($remoteId === '') {
            throw new UserGateException('UserGate tidak mengembalikan ID user.', 0);
        }

        $remoteStatus = strtoupper(trim((string) ($remote['status'] ?? UserModel::STATUS_ACTIVE)));

        if ($remoteStatus !== UserModel::STATUS_ACTIVE) {
            throw new RuntimeException('Akun Anda di UserGate sedang tidak aktif.');
        }

        $local        = $this->users->findByUserGateId($remoteId);
        $isFirstUser  = false;

        if ($local === null) {
            [$local, $isFirstUser] = $this->createLocalFromUserGate($remote);
        }

        // Selaraskan username/email/nama dengan data terbaru di UserGate.
        // Role dan status lokal tidak pernah ditimpa dari luar.
        $local = $this->users->syncFromUserGate((int) $local['id'], $remote);

        if (strtoupper((string) $local['status']) === UserModel::STATUS_INACTIVE) {
            // Dua kondisi yang sama-sama menolak login, tapi penyebabnya beda
            // dan pemakaiannya juga beda. `markLogin()` baru dipanggil
            // SESUDAH cek ini, jadi `last_login_at` yang null berarti akun ini
            // belum pernah berhasil masuk sama sekali — bukan "pernah aktif
            // lalu dicabut".
            $neverActivated = $local['last_login_at'] === null;

            throw new RuntimeException(
                $neverActivated
                    ? 'Akun Anda belum diaktifkan. Hubungi administrator untuk mengaktifkannya.'
                    : 'Akun Anda dinonaktifkan oleh administrator.'
            );
        }

        $this->users->markLogin((int) $local['id']);

        $roles = $this->userRoles->roleNamesFor((int) $local['id']);

        $this->startSession($local, $roles, $auth);

        return [
            'user'           => $local,
            'roles'          => $roles,
            'is_super_admin' => $this->isSuperAdminRole($roles),
            'is_first_user'  => $isFirstUser,
        ];
    }

    /**
     * Pastikan access token masih valid; refresh bila sudah/mau kedaluwarsa.
     *
     * Dipanggil AuthFilter pada setiap request web.
     *
     * @return bool True bila sesi masih punya token yang bisa dipakai.
     */
    public function ensureFreshToken(): bool
    {
        if (! $this->tokens->hasAccessToken()) {
            return false;
        }

        if (! $this->tokens->accessTokenExpired()) {
            return true;
        }

        $refreshToken = $this->tokens->refreshToken();

        if ($refreshToken === null || $this->tokens->refreshTokenExpired()) {
            return false;
        }

        try {
            $auth = $this->gate->refresh($refreshToken);
        } catch (UserGateException) {
            // Refresh token bersifat one-time-use. Kalau gagal, paksa login
            // ulang daripada mencoba memakai token yang sama lagi.
            return false;
        }

        $this->tokens->put($auth);

        // Sinkronkan data user dari payload `user` yang dikirim UserGate.
        $localId = (int) session('access_user_id');
        $remote  = $auth['user'] ?? [];

        if ($localId > 0 && (string) ($remote['id'] ?? '') === (string) session('access_usergate_id')) {
            $local = $this->users->syncFromUserGate($localId, $remote);

            session()->set([
                'access_username'  => $local['username']    ?? session('access_username'),
                'access_email'     => $local['email']       ?? session('access_email'),
                'access_full_name' => $local['full_name']   ?? session('access_full_name'),
            ]);
        }

        return true;
    }

    /* ------------------------------------------------------------------ *
     *  SESI
     * ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $user
     * @param list<string>        $roles
     * @param array<string,mixed> $auth
     */
    private function startSession(array $user, array $roles, array $auth): void
    {
        $this->tokens->put($auth);

        session()->set([
            'logged_in'          => true,
            'access_user_id'     => (int) $user['id'],
            'access_usergate_id' => (string) $user['usergate_id'],
            'access_username'    => (string) $user['username'],
            'access_email'       => (string) $user['email'],
            'access_full_name'   => (string) $user['full_name'],
            'access_roles'       => $roles,
            'access_is_super'    => $this->isSuperAdminRole($roles),
        ]);

        // Kunci lama masih dipakai topbar. Pertahankan supaya view yang
        // belum ikut disentuh tidak ikut rusak.
        session()->set([
            'user_id' => (string) $user['usergate_id'],
            'nama'    => (string) $user['full_name'],
        ]);
    }

    public function logout(): void
    {
        $token = $this->tokens->accessToken();

        if ($token !== null) {
            $this->gate->logout($token);
        }

        $this->tokens->clear();
        session()->destroy();
    }

    /* ------------------------------------------------------------------ *
     *  OTORISASI
     * ------------------------------------------------------------------ */

    /**
     * @return list<string>
     */
    public function currentRoles(): array
    {
        $roles = session('access_roles');

        return is_array($roles) ? array_values(array_map('strval', $roles)) : [];
    }

    /**
     * Apakah user punya setidaknya satu role?
     *
     * User tanpa role tetap boleh login, tetapi tidak melihat menu apa pun.
     */
    public function hasAnyRole(): bool
    {
        return $this->currentRoles() !== [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->currentRoles(), true);
    }

    /**
     * Apakah user adalah SuperAdmin?
     *
     * DB lokal adalah satu-satunya sumber kebenaran. Nilai `access_is_super`
     * di session SENGAJA tidak dipakai untuk memberi hak: session dibuat saat
     * login, jadi kalau role-nya diubah setelah itu, session masih memegang
     * flag lama. Dengan memercayai session, user yang bar saja di-demote dari
     * SuperAdmin tetap punya hak penuh sampai logout/login.
     *
     * Arah sebaliknya tidak pernah bermasalah — promote langsung berlaku begitu
     * DB berubah — dan sekarang keduanya berlaku seketika.
     *
     * Sengaja TIDAK di-cache. Helper `is_super_admin()` menyimpan satu instance
     * AccessService di `static`, dan instance itu bisa hidup melewati beberapa
     * request pada satu proses PHP — persis yang terjadi di feature test.
     * Cache yang tidak di-reset per request akan membocorkan hak milik user
     * sebelumnya ke user berikutnya yang punya id sama. Query ulang lebih aman,
     * dan ini lookup primary key yang murah.
     */
    public function isSuperAdmin(): bool
    {
        $userId = (int) session('access_user_id');

        if ($userId <= 0) {
            return false;
        }

        return $this->isSuperAdminRole($this->userRoles->roleNamesFor($userId));
    }

    /**
     * @param list<string> $roles
     */
    public function isSuperAdminRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $this->config->superAdminRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guard server untuk aksi yang hanya boleh SuperAdmin.
     *
     * View menyembunyikan kontrolnya; ini yang benar-benar memastikan.
     *
     * @throws RuntimeException
     */
    public function assertSuperAdmin(string $action = 'user.promote'): void
    {
        if (! in_array($action, $this->config->superAdminOnly, true)) {
            throw new RuntimeException('Aksi "' . $action . '" tidak dikenal.');
        }

        if (! $this->isSuperAdmin()) {
            throw new RuntimeException('Aksi ini hanya dapat dilakukan oleh SuperAdmin.');
        }
    }

    /**
     * Apakah user boleh mengelola aplikasi API tertentu.
     *
     * SuperAdmin mengelola semua aplikasi. Admin hanya aplikasi yang
     * ia buat sendiri — inilah bedanya dengan `assertSuperAdmin`, yang
     * selalu menyisakan SuperAdmin dengan akses penuh.
     *
     * Aplikasi tanpa owner (`created_by` NULL, diisi manual di DB) hanya
     * boleh dikelola SuperAdmin: tidak ada akun yang bisa dimintai
     * pertanggungjawabannya.
     *
     * @param array<string,mixed> $application
     */
    public function canManageApiApplication(array $application): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $ownerId = $application['created_by'] ?? null;

        if ($ownerId === null) {
            return false;
        }

        return (int) $ownerId === (int) session('access_user_id');
    }

    /**
     * @param array<string,mixed> $application
     *
     * @throws RuntimeException
     */
    public function assertCanManageApiApplication(array $application): void
    {
        if ($this->canManageApiApplication($application)) {
            return;
        }

        throw new RuntimeException(
            'Anda hanya dapat mengelola application yang Anda buat sendiri.'
        );
    }

    /* ------------------------------------------------------------------ *
     *  INTERNAL
     * ------------------------------------------------------------------ */

    /**
     * Buat baris user lokal untuk akun UserGate yang belum pernah login.
     *
     * Kalau tabel `users` masih kosong, user ini sekaligus menjadi
     * SuperAdmin. Kalau tidak, user dibuat TANPA role dan NONAKTIF, sehingga
     * dia bisa login tapi ditolak sampai administrator mengaktifkannya dan
     * memberikan role.
     *
     * Status default INACTIVE bukan sekadar formalitas: `isFirstUser`
     * ditentukan dari `users->total() === 0`. Kalau tabel `users` pernah
     * dikosongkan — restore dump yang tidak lengkap, misalnya — siapa pun yang
     * login berikutnya akan otomatis menjadi SuperAdmin. Dengan default
     * nonaktif, setidaknya akun itu harus ditolak lebih dulu sebelum dia
     * dipakai, dan test bootstrap tidak bisa lolos diam-diam.
     *
     * @param  array<string,mixed> $remote
     * @return array{0: array<string,mixed>, 1: bool} User lokal + status "user pertama".
     */
    private function createLocalFromUserGate(array $remote): array
    {
        $db = Database::connect(config('UserGate')->dbGroup);

        $db->transStart();
        $isFirstUser = false;

        try {
            if ($this->users->total() === 0) {
                $isFirstUser = true;
            }

            // Pastikan seluruh role bawaan ada SEBELUM user pertama dibuat, supaya
            // role yang baru dibuat user pertama memakai id yang sudah benar.
            $this->roles->ensureDefaults();

            $localId = $this->users->createLocal([
                'usergate_id' => (string) $remote['id'],
                'username'    => (string) ($remote['username'] ?? ''),
                'email'       => (string) ($remote['email'] ?? ''),
                'full_name'   => (string) ($remote['full_name'] ?? ''),

                // Hanya user pertama yang otomatis aktif. Dia satu-satunya
                // SuperAdmin sistem, jadi kalau juga nonaktif tidak akan ada
                // yang bisa mengaktifkan siapa pun.
                'status'      => $isFirstUser ? UserModel::STATUS_ACTIVE : UserModel::STATUS_INACTIVE,
            ]);

            if ($isFirstUser) {
                $roleId = $this->roles->ensure(
                    $this->config->superAdminRoles[0] ?? 'SUPER_ADMIN',
                    'Akses penuh, termasuk menetapkan SuperAdmin dan menghapus user.',
                    1
                );

                $this->userRoles->attach($localId, $roleId);
            }
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new RuntimeException('Gagal menyimpan data user lokal.');
        }

        $db->transComplete();

        $local = $this->users->findByUserGateId((string) $remote['id']);

        if ($local === null) {
            throw new RuntimeException('Gagal membuat data user lokal.');
        }

        return [$local, $isFirstUser];
    }
}
