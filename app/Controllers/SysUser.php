<?php

namespace App\Controllers;

use App\Libraries\UserGate\TokenStore;
use App\Libraries\UserGate\UserGateClient;
use App\Libraries\UserGate\UserGateException;
use App\Models\Access\AccessService;
use App\Models\Access\RoleModel;
use App\Models\Access\UserModel;
use App\Models\Access\UserRoleModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

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

    /** Batas atas jumlah baris per request dari DataTables. */
    private const MAX_ROWS_PER_PAGE = 100;

    public function __construct()
    {
        $this->gate      = $this->makeUserGateClient();
        $this->tokens    = new TokenStore();
        $this->users     = new UserModel();
        $this->roles     = new RoleModel();
        $this->userRoles = new UserRoleModel();
        $this->access    = new AccessService();
    }

    /* ------------------------------------------------------------------ *
     *  READ
     * ------------------------------------------------------------------ */

    /**
     * Halaman daftar user.
     *
     * Baris tabel tidak diambil di sini — DataTables memintanya sendiri lewat
     * SysUser::data(). Yang dibutuhkan halaman ini hanya daftar role untuk
     * checkbox di modal ubah.
     *
     * Daftar role DISARING dengan assignableRoles(), sama seperti
     * create()/edit(). Sebelumnya halaman ini memakai listRoles() sehingga
     * modal ubah menampilkan opsi SuperAdmin bahkan kepada Admin — padahal
     * assignableRoles() justru punya tugas menyembunyikannya. Server tetap
     * menyaring ulang di requestedRoleIds(), jadi ini soal tampilan saja.
     */
    public function index()
    {
        return view('sys-user/index', [
            'title' => 'Manajemen User',
            'roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * Sumber data tabel user untuk DataTables server-side.
     *
     * Bentuk respons mengikuti kontrak DataTables: `draw`, `recordsTotal`,
     * `recordsFiltered`, dan `data`. `draw` wajib dikembalikan apa adanya,
     * tanpa itu respons lama bisa menimpa yang baru saat user mengetik cepat
     * di kotak pencarian.
     *
     * Setiap baris ikut membawa flag hak akses (canDelete, canToggle,
     * isSelf) supaya tombol aksi di tabel/client Decisions sama dengan
     * aturan server — bukan hanya disembunyikan.
     */
    public function data()
    {
        $draw   = max(0, (int) $this->request->getGet('draw'));
        $start  = max(0, (int) $this->request->getGet('start'));
        $length = (int) $this->request->getGet('length');

        // DataTables mengirim `search` sebagai objek (value + regex). Yang
        // dipakai hanya `value`; `regex` sengaja tidak didukung supaya pola
        // regex tidak pernah sampai ke query.
        $rawSearch = $this->request->getGet('search');
        $search    = is_array($rawSearch)
            ? trim((string) ($rawSearch['value'] ?? ''))
            : trim((string) $rawSearch);

        $status = trim((string) $this->request->getGet('status'));

        // Length dari browser tidak dipercaya: tanpa batas atas, satu request
        // bisa menarik seluruh tabel.
        $perPage = $length <= 0 ? self::MAX_ROWS_PER_PAGE : min($length, self::MAX_ROWS_PER_PAGE);

        // Index kolom dan arah urutan datang dari browser, jadi keduanya
        // hanya boleh dipakai setelah lolos peta.
        $orderColumn = (int) ($this->request->getGet('order[0][column]') ?? -1);
        $orderDir    = strtolower((string) ($this->request->getGet('order[0][dir]') ?? 'asc'));
        $orderBy     = UserModel::SORTABLE_COLUMNS[$orderColumn] ?? 'users.full_name';
        $direction   = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'asc';

        $rows = $this->users->listVisiblePaged(
            $search,
            $status,
            $perPage,
            $start,
            $orderBy,
            $direction
        );

        $currentId  = current_user_id();
        $canDelete  = can('user.delete');

        $data = [];

        foreach ($rows as $row) {
            $id      = (int) $row['id'];
            $isSelf  = $id === $currentId;
            $roles   = array_values((array) $row['role_names']);
            $roleIds = array_values(array_map('intval', (array) $row['role_ids']));
            $isSuper = $this->access->isSuperAdminRole($roles);

            // Jenjang-based, bukan hanya "bukan SuperAdmin": actor hanya boleh
            // mengubah role dan status akun yang ADA DI BAWAHNYA. Jadi Admin
            // tidak bisa menyentuh akun Admin lain (role setara), dan tidak
            // bisa menyentuh Supervisor maupun Petugas yang lebih rendah.
            $mayManage = $this->mayManageRolesOf($roles);

            $data[] = [
                'id'         => $id,
                'username'   => (string) $row['username'],
                'fullName'   => (string) $row['full_name'],
                'email'      => (string) $row['email'],
                'roleNames'  => $roles,
                'roleIds'    => $roleIds,
                'isSuper'    => $isSuper,
                'status'     => (string) $row['status'],
                'lastLoginAt' => $row['last_login_at'] === null ? null : (string) $row['last_login_at'],

                // Flag hak akses ikut ke client supaya tombol yang tampil
                // sama persis dengan yang diizinkan server.
                'isSelf'   => $isSelf,
                'canToggle' => ! $isSelf && $mayManage,
                'canDelete' => $canDelete && ! $isSelf,

                // Checkbox role dikunci kalau actor tidak berwenang mengubah
                // role akun ini. Profil (username/email/nama) tetap boleh
                // diedit — yang dikunci hanya role-nya.
                'canEditRoles' => $mayManage,

                'editUrl'   => base_url('user/edit/' . $id),
                'toggleUrl' => base_url('user/toggle-status/' . $id),
                'deleteUrl' => base_url('user/delete/' . $id),
            ];
        }

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => $this->users->countAll(),
                'recordsFiltered' => $this->users->countVisibleFiltered($search, $status),
                'data'            => $data,
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
     *
     * Dipanggil dari modal di halaman /user, jadi jalur utamanya Ajax. POST
     * biasa tetap ditangani (form tetap punya action dan method POST): kalau
     * JavaScript-nya gagal, halaman dimuat ulang dengan modal tambah yang
     * terbuka lagi — lihat failStore()/failStoreMessage().
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
            return $this->failStore($errors);
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

                // Akun dibuat dari aplikasi ini selalu nonaktif dulu, meski
                // role ADMIN langsung diberikan. Role dan status tidak saling
                // memengaruhi: yang menentukan boleh masuk adalah status.
                'status'      => UserModel::STATUS_INACTIVE,
            ]);

            $this->userRoles->sync($localId, $roleIds);
        } catch (UserGateException $e) {
            return $this->failStoreMessage($this->gateErrorMessage($e));
        } catch (RuntimeException $e) {
            return $this->failStoreMessage($e->getMessage());
        }

        // Pesan menyebut statusnya karena akun baru sengaja dibuat NONAKTIF.
        // Tanpa ini administrator akan mengira user bisa langsung masuk, lalu
        // bingung ketika login-nya ditolak.
        $message = 'User "' . $input['username'] . '" berhasil dibuat, tetapi statusnya masih NONAKTIF. '
            . 'Aktifkan dulu sebelum dia bisa login.';

        if ($this->request->isAJAX()) {
            return $this->jsonOk($message);
        }

        return redirect()->to(base_url('user'))->with('success', $message);
    }

    /**
     * Ubah data user di UserGate + role/status lokal.
     */
    public function update(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return $this->failMissing();
        }

        $input = [
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'full_name' => trim((string) $this->request->getPost('full_name')),
        ];

        $errors = $this->validateProfile($input);

        if ($errors !== []) {
            return $this->failUpdate($errors);
        }

        $roleIds = $this->requestedRoleIds();

        $current = $this->userRoles->roleNamesFor($id);
        $isSuper = $this->access->isSuperAdminRole($current);

        // SuperAdmin tidak boleh mencabut SuperAdmin dari dirinya sendiri:
        // itu bisa membuat sistem tanpa administrator sama sekali.
        if ($isSuper && $id === current_user_id() && ! $this->contains($roleIds, $this->superAdminRoleIds())) {
            return $this->failUpdateMessage('Anda tidak dapat mencabut role SuperAdmin dari akun Anda sendiri.');
        }

        // Actor yang BUKAN SuperAdmin tidak boleh mengubah role akun yang
        // jenjangnya setingkat atau lebih tinggi dari miliknya. Isian
        // `roles[]` diabaikan sepenuhnya dan role target dipertahankan apa
        // adanya.
        //
        // Aturan ini menggantikan cabang "kecuali SuperAdmin" yang lebih sempit.
        // Selain menutup jalur eskalasi (memberikan role setara atau lebih tinggi),
        // ini juga menutup pelucutan role: tanpa itu, Admin bisa menghapus semua
        // role milik Admin lain tanpa jejaknya.
        //
        // Profil tetap boleh diubah — yang dikunci hanya role.
        if (! $this->mayManageRolesOf($current)) {
            $roleIds = $this->roleIdsOfUser($id, $current);
        }

        try {
            $token = $this->requireAccessToken();

            $this->gate->updateUser($token, (string) $user['usergate_id'], [
                'username'  => $input['username'],
                'email'     => $input['email'],
                'full_name' => $input['full_name'],

                // UserGate menolak PUT tanpa field `status`
                // ("The status field is required."), jadi status saat ini
                // harus ikut dikirim. Form ubah tidak punya field status —
                // status hanya berubah lewat toggle, jadi nilai yang dikirim
                // adalah nilai yang sudah ada.
                'status'    => (string) $user['status'],
            ]);

            $this->users->update($id, [
                'username'  => $input['username'],
                'email'     => $input['email'],
                'full_name' => $input['full_name'],
            ]);

            $this->userRoles->sync($id, $roleIds);
        } catch (UserGateException $e) {
            return $this->failUpdateMessage($this->gateErrorMessage($e));
        } catch (RuntimeException $e) {
            return $this->failUpdateMessage($e->getMessage());
        }

        $message = 'User "' . $input['username'] . '" berhasil diperbarui.';

        if ($this->request->isAJAX()) {
            return $this->jsonOk($message);
        }

        return redirect()->to(base_url('user'))->with('success', $message);
    }

    /**
     * Aktifkan/nonaktifkan user.
     *
     * Status lokal KanzaBridge adalah yang menentukan akses ke aplikasi
     * ini, dan best-effort disinkronkan juga ke UserGate supaya kedua
     * sisi tidak berbeda jauh. Kegagalan sinkronisasi ke UserGate tidak
     * membatalkan perubahan lokal — yang penting user tidak bisa masuk
     * ke KanzaBridge.
     *
     * Menonaktifkan sebuah akun hanya boleh dilakukan oleh SuperAdmin atau oleh
     * actor yang jenjangnya lebih tinggi. Tanpa guard ini satu Admin bisa
     * mengunci akun Admin lain — dan bahkan seluruh akun SuperAdmin, karena
     * destroy() memakai assertSuperAdmin() sementara toggleStatus() tidak.
     * Aturan jenjang yang sama sudah dipakai update() untuk role.
     */
    public function toggleStatus(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return $this->failMissing();
        }

        if ($id === current_user_id()) {
            return $this->failAction('Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if (! $this->mayManageRolesOf($this->userRoles->roleNamesFor($id))) {
            return $this->failAction(
                'Anda hanya dapat mengubah status user di bawah jenjang Anda.'
            );
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

        if ($this->request->isAJAX()) {
            return $this->jsonOk($message);
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
            return $this->failAction($e->getMessage());
        }

        $user = $this->users->find($id);

        if ($user === null) {
            return $this->failMissing();
        }

        if ($id === current_user_id()) {
            return $this->failAction('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        try {
            $token = $this->requireAccessToken();
            $this->gate->deleteUser($token, (string) $user['usergate_id']);
        } catch (UserGateException $e) {
            return $this->failAction($this->gateErrorMessage($e));
        } catch (RuntimeException $e) {
            return $this->failAction($e->getMessage());
        }

        // Akun sudah hilang dari UserGate; rapikan sisa data lokal.
        $this->userRoles->detachAll($id);
        $this->users->delete($id);

        $message = 'User "' . $user['username'] . '" berhasil dihapus.';

        if ($this->request->isAJAX()) {
            return $this->jsonOk($message);
        }

        return redirect()->to(base_url('user'))->with('success', $message);
    }

    /* ------------------------------------------------------------------ *
     *  BALASAN JSON UNTUK MODAL
     * ------------------------------------------------------------------ */

    /**
     * Sukses untuk request AJAX.
     *
     * `csrf` wajib disertakan. Config\Security::$regenerate = true, jadi
     * token di-regenerate SETIAP POST sukses. Cookie-nya sendiri
     * httpOnly (Config\Cookie::$httponly), jadi JavaScript tidak bisa
     * membacanya sendiri — tanpa token baru di sini, request kedua dari
     * modal akan selalu gagal dengan error CSRF.
     *
     * @param array<string,mixed> $extra
     */
    private function jsonOk(string $message, array $extra = []): ResponseInterface
    {
        $security = service('security');

        return $this->response
            ->setStatusCode(200)
            ->setJSON($extra + [
                'ok'      => true,
                'message' => $message,
                'csrf'    => [
                    'name'  => $security->getTokenName(),
                    'value' => (string) $security->getHash(),
                ],
            ]);
    }

    /**
     * Kegagalan untuk request AJAX: HTTP 422 supaya JavaScript tidak
     * menganggapnya sukses. `errors` opsional — untuk penolakan dari
     * server (bukan validasi field) pesannya saja yang penting.
     *
     * @param array<string,string> $errors
     */
    private function jsonFail(string $message, array $errors = []): ResponseInterface
    {
        $security = service('security');

        return $this->response
            ->setStatusCode(422)
            ->setJSON([
                'ok'      => false,
                'message' => $message,
                'errors'  => $errors,
                'csrf'    => [
                    'name'  => $security->getTokenName(),
                    'value' => (string) $security->getHash(),
                ],
            ]);
    }

    /* ------------------------------------------------------------------ *
     *  BALASAN JSON UNTUK MODAL
     * ------------------------------------------------------------------ */

    /**
     * Validasi field gagal.
     *
     * @param array<string,string> $errors
     */
    private function failUpdate(array $errors): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('Periksa kembali isian form.', $errors);
        }

        return redirect()->back()->with('errors', $errors)->withInput();
    }

    /**
     * Penolakan dari server — UserGate menolak, atau aturan akses.
     *
     * Field form sudah valid, jadi isian lama tidak dikembalikan: user
     * tidak mengubah apa pun.
     */
    private function failUpdateMessage(string $message): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail($message);
        }

        return redirect()->back()->with('error', $message)->withInput();
    }

    /**
     * Validasi field gagal pada tambah user.
     *
     * Sama seperti failUpdate(), hanya bedanya tujuan non-Ajax: form tambah
     * berada di modal, jadi kembali ke /user sambil membawa `errorForm`.
     * Tanpa penanda itu user akan melihat halaman yang terlihat utuh,
     * padahal form-nya tidak pernah muncul dan seluruh isian yang sudah
     * diketik hilang tanpa penjelasan.
     *
     * @param array<string,string> $errors
     */
    private function failStore(array $errors): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('Periksa kembali isian form.', $errors);
        }

        return redirect()->to(base_url('user'))
            ->with('errorForm', 'create')
            ->with('errors', $errors)
            ->withInput();
    }

    /**
     * Penolakan dari server saat tambah — UserGate menolak, atau aturan akses.
     *
     * Pesan dan isian lamanya dikembalikan ke dalam modal yang terbuka
     * kembali, bukan ke halaman, supaya user tidak perlu mengetik ulang.
     */
    private function failStoreMessage(string $message): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail($message);
        }

        return redirect()->to(base_url('user'))
            ->with('errorForm', 'create')
            ->with('error', $message)
            ->withInput();
    }

    /**
     * Aksi gagal (toggle / delete).
     */
    private function failAction(string $message): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail($message);
        }

        return redirect()->to(base_url('user'))->with('error', $message);
    }

    /**
     * Target tidak ada.
     */
    private function failMissing(): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('User tidak ditemukan.');
        }

        return redirect()->to(base_url('user'))->with('error', 'User tidak ditemukan.');
    }

    /* ------------------------------------------------------------------ *
     *  INTERNAL
     * ------------------------------------------------------------------ */

    /**
     * Titik pembuatan UserGateClient.
     *
     * Dipisah supaya test bisa menimpanya dengan FakeUserGateClient lewat
     * Services::injectMock(). Tanpa ini, test yang menyentuh SysUser akan
     * benar-benar memanggil UserGate produksi.
     */
    protected function makeUserGateClient(): UserGateClient
    {
        try {
            $client = service('userGateClient');
        } catch (Throwable) {
            $client = null;
        }

        return $client instanceof UserGateClient ? $client : new UserGateClient();
    }

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
     * SuperAdmin melihat semuanya — termasuk role-nya sendiri.
     *
     * Actor lain hanya boleh memberikan role yang STRICTLY lebih rendah dari
     * jenjang tertinggi miliknya (Config\Access::$roleRank). Contohnya:
     *
     *   ADMIN (30)  -> boleh memberi SUPERVISOR (20) dan PETUGAS (10)
     *   ADMIN (30)  -> TIDAK boleh memberi ADMIN (30): tidak ada promote lateral
     *   ADMIN (30)  -> TIDAK boleh memberi SUPER_ADMIN (40)
     *
     * Aturan ini menggantikan penyaringan biner "kecuali SuperAdmin" yang
     * sebelumnya, dan sekaligus menutup jalur eskalasi: tanpa itu, role baru
     * yang lebih rendah pun bisa menaikkan dirinya sendiri menjadi ADMIN.
     *
     * @return list<array<string,mixed>>
     */
    private function assignableRoles(): array
    {
        $all = $this->roles->listRoles();

        if (is_super_admin()) {
            return $all;
        }

        $rank    = access_service()->roleRank;
        $myRank  = $this->highestRank();

        return array_values(array_filter(
            $all,
            static fn (array $role): bool => ($rank[$role['name']] ?? 0) < $myRank
        ));
    }

    /**
     * Jenjang tertinggi dari sekumpulan role.
     *
     * Dipakai untuk aturan "hanya boleh menyentuh yang di bawahnya" dan
     * "hanya boleh memberikan yang lebih rendah". Tanpa argumen memakai role
     * milik user yang sedang login.
     *
     * @param list<string>|null $roles Null = role user yang sedang login.
     */
    private function highestRank(?array $roles = null): int
    {
        $rank  = access_service()->roleRank;
        $roles = $roles ?? (array) session('access_roles');

        $highest = 0;

        foreach ($roles as $name) {
            $highest = max($highest, (int) ($rank[$name] ?? 0));
        }

        return $highest;
    }

    /**
     * Apakah actor boleh mengubah role dan status sebuah akun?
     *
     * SuperAdmin boleh untuk semua akun. Actor lain hanya untuk akun yang
     * jenjangnya lebih rendah — jadi role setara tidak bisa saling disentuh,
     * dan role yang lebih tinggi juga tidak.
     *
     * @param list<string> $targetRoles
     */
    private function mayManageRolesOf(array $targetRoles): bool
    {
        return is_super_admin() || $this->highestRank($targetRoles) < $this->highestRank();
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
