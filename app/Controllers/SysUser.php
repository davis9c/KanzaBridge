<?php

namespace App\Controllers;

use App\Libraries\UserGate\TokenStore;
use App\Libraries\UserGate\UserGateClient;
use App\Libraries\UserGate\UserGateException;
use App\Models\Access\AccessService;
use App\Models\Access\RoleModel;
use App\Models\Access\UserModel;
use App\Models\Access\UserRoleModel;
use RuntimeException;

/**
 * Manajemen User.
 *
 * Pembagian tanggung jawab:
 *
 *   UserGate  -> akun itu sendiri (username, email, nama, status)
 *   DB lokal  -> role, status akses KanzaBridge, link NIK
 *
 * Role TIDAK pernah diambil dari UserGate. Field `roles` di respons
 * UserGate diabaikan; yang menentukan menu adalah tabel `user_roles`.
 *
 * Pembatasan:
 *   - SuperAdmin & Admin boleh melihat dan membuat user.
 *   - Hanya SuperAdmin boleh menetapkan role SuperAdmin dan menghapus user.
 */
class SysUser extends BaseController
{
    private UserGateClient $gate;
    private TokenStore $tokens;
    private UserModel $users;
    private RoleModel $roles;
    private UserRoleModel $userRoles;
    private AccessService $access;

    public function __construct()
    {
        $this->gate      = new UserGateClient();
        $this->tokens    = new TokenStore();
        $this->users     = new UserModel();
        $this->roles     = new RoleModel();
        $this->userRoles = new UserRoleModel();
        $this->access    = new AccessService();
    }

    /* ------------------------------------------------------------------ *
     *  READ
     * ------------------------------------------------------------------ */

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));

        return view('sys-user/index', [
            'title'  => 'Manajemen User',
            'users'  => $this->users->listWithRoles([
                'search' => $search,
                'status' => $status,
            ]),
            'roles'  => $this->roles->listRoles(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('sys-user/form', [
            'title'    => 'Tambah User',
            'user'     => null,
            'roles'    => $this->assignableRoles(),
            'assigned' => [],
            'errors'   => [],
        ]);
    }

    public function edit(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return redirect()->to(base_url('user'))->with('error', 'User tidak ditemukan.');
        }

        $assigned = array_column(
            $this->userRoles->assignmentsFor($id),
            'role_id'
        );

        return view('sys-user/form', [
            'title'     => 'Ubah User',
            'user'      => $user,
            'roles'     => $this->assignableRoles(),
            'assigned'  => array_map('intval', $assigned),
            'errors'    => [],
        ]);
    }

    /* ------------------------------------------------------------------ *
     *  WRITE
     * ------------------------------------------------------------------ */

    /**
     * Buat akun di UserGate, lalu catat user + role secara lokal.
     */
    public function store()
    {
        $input = [
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'password'  => (string) $this->request->getPost('password'),
        ];

        $errors = $this->validateNewUser($input);

        if ($errors !== []) {
            return redirect()->back()
                ->with('errors', $errors)
                ->withInput();
        }

        // Role yang diminta, sudah disaring oleh assignableRoles().
        $roleIds = $this->requestedRoleIds();

        try {
            $token = $this->requireAccessToken();

            $remote = $this->gate->createUser($token, [
                'username'  => $input['username'],
                'email'     => $input['email'],
                'full_name' => $input['full_name'],
                'password'  => $input['password'],
            ]);

            $localId = $this->users->createLocal([
                'usergate_id' => (string) $remote['id'],
                'username'    => (string) ($remote['username'] ?? $input['username']),
                'email'       => (string) ($remote['email']    ?? $input['email']),
                'full_name'   => (string) ($remote['full_name'] ?? $input['full_name']),
                'status'      => UserModel::STATUS_ACTIVE,
            ]);

            $this->userRoles->sync($localId, $roleIds);
        } catch (UserGateException $e) {
            return redirect()->back()
                ->with('error', $this->gateErrorMessage($e))
                ->withInput();
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()->to(base_url('user'))
            ->with('success', 'User "' . $input['username'] . '" berhasil dibuat.');
    }

    /**
     * Ubah data user di UserGate + role/status lokal.
     */
    public function update(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return redirect()->to(base_url('user'))->with('error', 'User tidak ditemukan.');
        }

        $input = [
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'full_name' => trim((string) $this->request->getPost('full_name')),
        ];

        $errors = $this->validateProfile($input);

        if ($errors !== []) {
            return redirect()->back()
                ->with('errors', $errors)
                ->withInput();
        }

        $roleIds = $this->requestedRoleIds();

        $current = $this->userRoles->roleNamesFor($id);
        $isSuper = $this->access->isSuperAdminRole($current);

        // SuperAdmin tidak boleh mencabut SuperAdmin dari dirinya sendiri:
        // itu bisa membuat sistem tanpa administrator sama sekali.
        if ($isSuper && $id === current_user_id() && ! $this->contains($roleIds, $this->superAdminRoleIds())) {
            return redirect()->back()
                ->with('error', 'Anda tidak dapat mencabut role SuperAdmin dari akun Anda sendiri.');
        }

        // Actor yang BUKAN SuperAdmin tidak boleh menyentuh role
        // SuperAdmin milik user lain — termasuk mencabutnya. Role milik
        // target yang berada di luar jangkauan actor dipertahankan.
        if (! is_super_admin() && $isSuper) {
            $roleIds = array_values(array_unique(array_merge(
                $roleIds,
                $this->roleIdsOfUser($id, access_service()->superAdminRoles)
            )));
        }

        try {
            $token = $this->requireAccessToken();

            $this->gate->updateUser($token, (string) $user['usergate_id'], [
                'username'  => $input['username'],
                'email'     => $input['email'],
                'full_name' => $input['full_name'],
            ]);

            $this->users->update($id, [
                'username'  => $input['username'],
                'email'     => $input['email'],
                'full_name' => $input['full_name'],
            ]);

            $this->userRoles->sync($id, $roleIds);
        } catch (UserGateException $e) {
            return redirect()->back()
                ->with('error', $this->gateErrorMessage($e))
                ->withInput();
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()->to(base_url('user'))
            ->with('success', 'User "' . $input['username'] . '" berhasil diperbarui.');
    }

    /**
     * Aktifkan/nonaktifkan user.
     *
     * Status lokal KanzaBridge adalah yang menentukan akses ke aplikasi
     * ini, dan best-effort disinkronkan juga ke UserGate supaya kedua
     * sisi tidak berbeda jauh. Kegagalan sinkronisasi ke UserGate tidak
     * membatalkan perubahan lokal — yang penting user tidak bisa masuk
     * ke KanzaBridge.
     */
    public function toggleStatus(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return redirect()->to(base_url('user'))->with('error', 'User tidak ditemukan.');
        }

        if ($id === current_user_id()) {
            return redirect()->to(base_url('user'))
                ->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $next = strtoupper((string) $user['status']) === UserModel::STATUS_ACTIVE
            ? UserModel::STATUS_INACTIVE
            : UserModel::STATUS_ACTIVE;

        $this->users->setStatus($id, $next);

        $message = 'Status user "' . $user['username'] . '" diubah menjadi ' . $next . '.';

        try {
            $this->gate->updateUser($this->requireAccessToken(), (string) $user['usergate_id'], [
                'status' => $next,
            ]);
        } catch (UserGateException | RuntimeException $e) {
            // Akses lokal sudah berubah dan itu yang utama. Catat saja
            // kegagalan sinkronisasi supaya bisa ditindaklanjuti.
            log_message('warning', 'Status lokal user {id} diubah ke {status}, tetapi sinkronisasi ke UserGate gagal: {err}', [
                'id'     => $id,
                'status' => $next,
                'err'    => $e->getMessage(),
            ]);

            $message .= ' Catatan: status di UserGate belum ikut berubah.';
        }

        return redirect()->to(base_url('user'))->with('success', $message);
    }

    /**
     * Hapus user — di UserGate maupun di DB lokal.
     * Hanya SuperAdmin.
     */
    public function destroy(int $id)
    {
        try {
            $this->access->assertSuperAdmin('user.delete');
        } catch (RuntimeException $e) {
            return redirect()->to(base_url('user'))->with('error', $e->getMessage());
        }

        $user = $this->users->find($id);

        if ($user === null) {
            return redirect()->to(base_url('user'))->with('error', 'User tidak ditemukan.');
        }

        if ($id === current_user_id()) {
            return redirect()->to(base_url('user'))
                ->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        try {
            $token = $this->requireAccessToken();
            $this->gate->deleteUser($token, (string) $user['usergate_id']);
        } catch (UserGateException $e) {
            return redirect()->to(base_url('user'))
                ->with('error', $this->gateErrorMessage($e));
        } catch (RuntimeException $e) {
            return redirect()->to(base_url('user'))->with('error', $e->getMessage());
        }

        // Akun sudah hilang dari UserGate; rapikan sisa data lokal.
        $this->userRoles->detachAll($id);
        $this->users->delete($id);

        return redirect()->to(base_url('user'))
            ->with('success', 'User "' . $user['username'] . '" berhasil dihapus.');
    }

    /* ------------------------------------------------------------------ *
     *  INTERNAL
     * ------------------------------------------------------------------ */

    /**
     * @return array<string,string>
     */
    private function validateNewUser(array $input): array
    {
        $errors = $this->validateProfile($input);

        if (strlen($input['password']) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        }

        if (! preg_match('/^[A-Za-z0-9]{3,100}$/', $input['username'])) {
            $errors['username'] = 'Username harus 3-100 karakter alfanumerik.';
        }

        if ($this->users->findByUsername($input['username']) !== null) {
            $errors['username'] = 'Username sudah dipakai user lain.';
        }

        return $errors;
    }

    /**
     * @return array<string,string>
     */
    private function validateProfile(array $input): array
    {
        $errors = [];

        if (strlen($input['username']) < 3 || strlen($input['username']) > 100) {
            $errors['username'] = 'Username harus 3-100 karakter.';
        }

        if (! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }

        $length = strlen($input['full_name']);

        if ($length < 3 || $length > 150) {
            $errors['full_name'] = 'Nama lengkap harus 3-150 karakter.';
        }

        return $errors;
    }

    /**
     * Role yang boleh ditampilkan/diberikan di form.
     *
     * SuperAdmin tidak melihat (dan tidak dapat mengirim) role SuperAdmin
     * bila dia sendiri bukan SuperAdmin — pengaman lapis kedua di samping
     * assertSuperAdmin().
     *
     * @return list<array<string,mixed>>
     */
    private function assignableRoles(): array
    {
        $all = $this->roles->listRoles();

        if (is_super_admin()) {
            return $all;
        }

        $superNames = access_service()->superAdminRoles;

        return array_values(array_filter(
            $all,
            static fn (array $role): bool => ! in_array($role['name'], $superNames, true)
        ));
    }

    /**
     * @return list<int>
     */
    private function requestedRoleIds(): array
    {
        $posted = $this->request->getPost('roles');

        if (! is_array($posted)) {
            return [];
        }

        // Saring hanya role yang memang boleh diberikan.
        $allowed = array_map(
            static fn (array $role): int => (int) $role['id'],
            $this->assignableRoles()
        );

        return array_values(array_intersect(
            array_map('intval', $posted),
            $allowed
        ));
    }

    /**
     * @return list<int>
     */
    private function superAdminRoleIds(): array
    {
        return $this->roleIdsNamed(
            $this->roles->listRoles(),
            access_service()->superAdminRoles
        );
    }

    /**
     * Ambil ID role dari daftar role yang namanya cocok.
     *
     * @param  list<array<string,mixed>> $roles
     * @param  list<string>              $names
     * @return list<int>
     */
    private function roleIdsNamed(array $roles, array $names): array
    {
        $ids = [];

        foreach ($roles as $role) {
            if (in_array($role['name'], $names, true)) {
                $ids[] = (int) $role['id'];
            }
        }

        return $ids;
    }

    /**
     * ID role milik seorang user yang namanya ada di $names.
     *
     * @param list<string> $names
     * @return list<int>
     */
    private function roleIdsOfUser(int $userId, array $names): array
    {
        $ids = [];

        foreach ($this->userRoles->assignmentsFor($userId) as $row) {
            if (in_array($row['name'], $names, true)) {
                $ids[] = (int) $row['role_id'];
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $needles
     */
    private function contains(array $needles, array $haystack): bool
    {
        return array_intersect($needles, $haystack) !== [];
    }

    /**
     * @throws RuntimeException
     */
    private function requireAccessToken(): string
    {
        $token = $this->tokens->accessToken();

        if ($token === null) {
            throw new RuntimeException('Sesi tidak valid. Silakan login kembali.');
        }

        return $token;
    }

    private function gateErrorMessage(UserGateException $e): string
    {
        if ($e->isConfigProblem() || $e->isApiKeyProblem()) {
            log_message('error', 'UserGate API key bermasalah: ' . $e->getMessage());

            return 'Konfigurasi UserGate belum benar. Hubungi administrator sistem.';
        }

        if ($e->isRateLimited()) {
            return 'Terlalu banyak permintaan ke UserGate. Silakan coba lagi sebentar.';
        }

        $errors = $e->getErrors();

        if ($errors !== []) {
            $parts = [];

            foreach ($errors as $field => $message) {
                $parts[] = is_string($message)
                    ? $message
                    : implode(' ', array_map('strval', (array) $message));
            }

            return implode(' ', $parts);
        }

        if ($e->getStatusCode() === 404) {
            return 'Data tidak ditemukan di UserGate.';
        }

        if ($e->getStatusCode() === 409) {
            return $e->getMessage();
        }

        if ($e->isServerProblem()) {
            log_message('error', 'Gagal menghubungi UserGate: ' . $e->getMessage());

            return 'Layanan UserGate sedang tidak tersedia. Silakan coba lagi.';
        }

        return $e->getMessage();
    }
}
