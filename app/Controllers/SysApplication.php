<?php

namespace App\Controllers;

use App\Libraries\Api\ApiKeyService;
use App\Models\Access\ApiApplicationModel;
use App\Models\Access\ApiKeyModel;
use App\Models\Access\ApiKeyScopeModel;
use App\Models\Access\AccessService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Manajemen Application = application pemanggil API beserta API key-nya.
 *
 * Tabel yang dikelola (`api_applications`, `api_keys`, `api_key_scopes`)
 * ada di group `default` (khanzabridge), bukan di sik_beta. Data yang
 * diekspose API (pegawai, dokter, petugas, jabatan) tetap hanya dibaca
 * dari sik_beta.
 *
 * Pembagian tanggung jawab:
 *
 *   - Key hanya disimpan sebagai SHA-256. Key penuh ditampilkan SEKALI,
 *     tepat setelah dibuat. Setelah itu tidak bisa dibaca dari mana pun.
 *   - Endpoint yang boleh diakses dicentang per key (semua read-only).
 *     Daftar endpointnya datang dari Config\ApiScope, bukan dari controller.
 *
 * Aturan kepemilikan:
 *   SuperAdmin -> boleh melihat dan mengelola SEMUA application.
 *   Admin      -> hanya application yang ia buat sendiri. Application milik
 *                 orang lain tidak terlihat di daftar dan tetap ditolak
 *                 di server kalau URL-nya diakses langsung.
 */
class SysApplication extends BaseController
{
    /** Batas atas jumlah baris per request dari DataTables. */
    private const MAX_ROWS_PER_PAGE = 100;
    private ApiApplicationModel $applications;
    private ApiKeyModel $keys;
    private ApiKeyScopeModel $keyScopes;
    private AccessService $access;

    public function __construct()
    {
        $this->applications = new ApiApplicationModel();
        $this->keys         = new ApiKeyModel();
        $this->keyScopes    = new ApiKeyScopeModel();
        $this->access       = new AccessService();
    }

    /* ------------------------------------------------------------------ *
     *  DAFTAR APLIKASI
     * ------------------------------------------------------------------ */

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));

        $applications = $this->applications->listVisible(
            is_super_admin(),
            current_user_id(),
            ['search' => $search]
        );

        $counts = $this->keys->countsForApplications(
            array_map(static fn (array $row): int => (int) $row['id'], $applications)
        );

        return view('sys-application/index', [
            'title'        => 'Manajemen Application',
            'applications' => $applications,
            'counts'       => $counts,
            'search'       => $search,
        ]);
    }

    /**
     * Sumber data tabel application untuk DataTables server-side.
     *
     * Bentuk respons mengikuti kontrak DataTables: `draw`, `recordsTotal`,
     * `recordsFiltered`, dan `data`. `draw` wajib dikembalikan apa adanya,
     * tanpa itu respons lama bisa menimpa yang baru ketika user mengetik
     * cepat di kotak pencarian.
     *
     * Semua angka dihitung dengan aturan kepemilikan yang sama seperti
     * halaman utama — `recordsTotal` untuk Admin hanya menghitung
     * application miliknya sendiri, jadi tidak membocorkan keberadaan
     * application orang lain.
     */
    public function data()
    {
        $draw   = max(0, (int) $this->request->getGet('draw'));
        $start  = max(0, (int) $this->request->getGet('start'));
        $length = (int) $this->request->getGet('length');
        $isSuper = is_super_admin();
        $userId  = current_user_id();

        // DataTables mengirim `search` sebagai objek (value + regex), bukan
        // string. Yang dipakai hanya `value`; `regex` sengaja tidak didukung
        // supaya pola regex tidak pernah sampai ke query.
        $rawSearch = $this->request->getGet('search');
        $search    = is_array($rawSearch)
            ? trim((string) ($rawSearch['value'] ?? ''))
            : trim((string) $rawSearch);

        // Length dari browser tidak dipercaya: tanpa batas atas, satu
        // request bisa menarik seluruh tabel beserta JOIN users.
        $perPage = $length <= 0 ? self::MAX_ROWS_PER_PAGE : min($length, self::MAX_ROWS_PER_PAGE);

        // Index kolom dan arah urutan datang dari browser, jadi keduanya
        // hanya boleh dipakai setelah lolos peta. Nilai di luar peta
        // diabaikan dan jatuh ke urutan bawaan.
        $orderColumn = (int) ($this->request->getGet('order[0][column]') ?? -1);
        $orderDir    = strtolower((string) ($this->request->getGet('order[0][dir]') ?? 'asc'));
        $orderBy     = ApiApplicationModel::SORTABLE_COLUMNS[$orderColumn] ?? 'api_applications.name';
        $direction   = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'asc';

        $rows = $this->applications->listVisiblePaged(
            $isSuper,
            $userId,
            $search,
            $perPage,
            $start,
            $orderBy,
            $direction
        );

        // Jumlah API key hanya untuk baris di halaman ini — bukan seluruh
        // tabel — supaya query-nya tetap murah.
        $counts = $this->keys->countsForApplications(
            array_map(static fn (array $row): int => (int) $row['id'], $rows)
        );

        $data = [];

        foreach ($rows as $row) {
            $id     = (int) $row['id'];
            $count  = $counts[$id] ?? ['total' => 0, 'active' => 0];

            $data[] = [
                'id'          => $id,
                'name'        => (string) $row['name'],
                'code'        => (string) $row['code'],
                'description' => $row['description'] === null ? null : (string) $row['description'],
                'ownerName'   => $row['owner_name'] === null ? null : (string) $row['owner_name'],
                'ownerless'   => $row['created_by'] === null,
                'createdAt'   => $row['created_at'] === null ? null : (string) $row['created_at'],
                'keyTotal'    => (int) $count['total'],
                'keyActive'   => (int) $count['active'],

                // URL dibentuk di server supaya base_url() tidak perlu
                // ditebak ulang di JavaScript.
                'keysUrl'   => base_url('application/' . $id . '/keys'),
                'editUrl'   => base_url('application/edit/' . $id),
                'deleteUrl' => base_url('application/delete/' . $id),
            ];
        }

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setJSON([
                'draw'            => $draw,
                'recordsTotal'    => $this->applications->countVisible($isSuper, $userId),
                'recordsFiltered' => $this->applications->countVisibleFiltered($isSuper, $userId, $search),
                'data'            => $data,
            ]);
    }

    /* ------------------------------------------------------------------ *
     *  PENGGUNAAN
     * ------------------------------------------------------------------ */

    /**
     * Halaman penggunaan sudah jadi dashboard (lihat SysDashboard::index).
     *
     * URL lama ini sengaja dipertahankan supaya bookmark dan tautan yang
     * sudah tersebar tidak jadi tautan mati. Filter dari query string ikut
     * diteruskan supaya bookmark dengan filter tertentu tetap jalan.
     */
    public function usage()
    {
        $query = $this->request->getGet();

        return redirect()->to(base_url('dashboard') . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    /* ------------------------------------------------------------------ *
     *  APLIKASI: BUAT / UBAH / HAPUS
     * ------------------------------------------------------------------ */

    public function create()
    {
        return view('sys-application/form', [
            'title'       => 'Tambah Application',
            'application' => null,
            'errors'      => [],
        ]);
    }

    public function store()
    {
        $input = $this->applicationInput();
        $code  = $input['code'] !== '' ? $input['code'] : $this->slug($input['name']);

        $errors = $this->validateApplication($input, $code, 0);

        if ($errors !== []) {
            return $this->failCreate($errors);
        }

        $id = $this->applications->createApplication([
            'name'        => $input['name'],
            'code'        => $code,
            'description' => $input['description'] === '' ? null : $input['description'],
            'created_by'  => current_user_id(),
        ]);

        $message = 'Application "' . $input['name'] . '" dibuat. Buat API key untuk mulai dipakai.';

        // Modal AJAX: balas JSON supaya halaman tidak reload.
        if ($this->request->isAJAX()) {
            return $this->jsonOk($message, [
                'id'   => $id,
                'keys' => base_url('application/' . $id . '/keys'),
            ]);
        }

        return redirect()->to(base_url('application/' . $id . '/keys'))
            ->with('success', $message);
    }

    public function edit(int $id)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        return view('sys-application/form', [
            'title'       => 'Ubah Application',
            'application' => $application,
            'errors'      => [],
        ]);
    }

    public function update(int $id)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return $this->failMissing();
        }

        $input = $this->applicationInput();
        $code  = $input['code'] !== '' ? $input['code'] : $this->slug($input['name']);

        $errors = $this->validateApplication($input, $code, $id);

        if ($errors !== []) {
            return $this->failUpdate($id, $errors);
        }

        $this->applications->updateApplication($id, [
            'name'        => $input['name'],
            'code'        => $code,
            'description' => $input['description'] === '' ? null : $input['description'],
        ]);

        $message = 'Application "' . $input['name'] . '" diperbarui.';

        if ($this->request->isAJAX()) {
            return $this->jsonOk($message, [
                'id'   => $id,
                'keys' => base_url('application/' . $id . '/keys'),
            ]);
        }

        return redirect()->to(base_url('application/' . $id . '/keys'))
            ->with('success', $message);
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
     * Validasi gagal untuk request AJAX: HTTP 422 supaya JavaScript
     * tidak menganggapnya sukses.
     *
     * @param array<string,string> $errors
     */
    private function jsonFail(string $message, array $errors): ResponseInterface
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

    /**
     * @param array<string,string> $errors
     */
    private function failCreate(array $errors): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('Periksa kembali isian form.', $errors);
        }

        return redirect()->back()->with('errors', $errors)->withInput();
    }

    /**
     * @param array<string,string> $errors
     */
    private function failUpdate(int $id, array $errors): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('Periksa kembali isian form.', $errors);
        }

        return redirect()->back()->with('errors', $errors)->withInput();
    }

    /**
     * Target tidak ada / tidak boleh dikelola.
     */
    private function failMissing(): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->jsonFail('Application tidak ditemukan.', []);
        }

        return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
    }

    /**
     * Hapus application. Foreign key CASCADE ikut menghapus key dan scope-nya.
     */
    public function destroy(int $id)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        $keyCount = count($this->keys->listForApplication($id));

        $this->applications->deleteApplication($id);

        $message = 'Application "' . $application['name'] . '" dihapus';

        if ($keyCount > 0) {
            $message .= ' beserta ' . $keyCount . ' API key';
        }

        return redirect()->to(base_url('application'))
            ->with('success', $message . '. Integrasi yang masih memakai key ini akan langsung gagal.');
    }

    /* ------------------------------------------------------------------ *
     *  API KEY
     * ------------------------------------------------------------------ */

    /**
     * Halaman utama fitur ini: daftar key milik sebuah application,
     * lengkap dengan checklist endpoint per key.
     */
    public function keys(int $id)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        return view('sys-application/keys', [
            'title'       => 'API Key — ' . $application['name'],
            'application' => $application,
            'keys'        => $this->keys->listForApplication($id),
            'catalog'     => api_scope_catalog(),
            'statuses'    => ApiKeyModel::statuses(),
        ]);
    }

    /**
     * Buat key baru. Key penuh hanya dikembalikan di sini, sekali.
     */
    public function storeKey(int $id)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        $label     = trim((string) $this->request->getPost('label'));
        $expiresAt = $this->expiresInput();
        $rateLimit = $this->rateLimitInput();
        $scopes    = api_scope_requested($this->request->getPost('scopes'));

        $errors = [];

        if ($label === '') {
            $errors['label'] = 'Nama key wajib diisi.';
        } elseif (mb_strlen($label) > 100) {
            $errors['label'] = 'Nama key maksimal 100 karakter.';
        }

        if ($scopes === []) {
            $errors['scopes'] = 'Pilih minimal satu endpoint yang boleh diakses.';
        }

        if ($errors !== []) {
            // Form buat key berada di dalam modal, jadi halaman perlu tahu
            // modal mana yang harus dibuka ulang lengkap dengan isian lama.
            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('errors', $errors)
                ->with('errorForm', 'create')
                ->withInput();
        }

        $generated = (new ApiKeyService())->generate();

        $keyId = $this->keys->createKey([
            'application_id'        => $id,
            'label'                 => $label,
            'key_prefix'            => $generated['prefix'],
            'key_hash'              => $generated['hash'],
            'expires_at'            => $expiresAt,
            'rate_limit_per_minute' => $rateLimit,
        ]);

        $this->keyScopes->sync($keyId, $scopes);

        // Flashdata dibaca sekali oleh halaman keys, lalu hilang. Ini
        // satu-satunya jalan key plaintext keluar dari server.
        return redirect()
            ->to(base_url('application/' . $id . '/keys'))
            ->with('success', 'API key "' . $label . '" dibuat.')
            ->with('new_api_key', $generated['plain'])
            ->with('new_api_key_label', $label);
    }

    /**
     * Simpan ulang checklist endpoint sebuah key.
     */
    public function updateScopes(int $id, int $keyId)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        $key = $this->keys->find((int) $keyId);

        if ($key === null || (int) $key['application_id'] !== (int) $id) {
            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('error', 'API key tidak ditemukan pada application ini.');
        }

        $scopes = api_scope_requested($this->request->getPost('scopes'));

        if ($scopes === []) {
            // withInput() dipakai supaya centang yang dikirim kembali ke
            // modal hak akses, bukan hilang begitu saja.
            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('error', 'Key harus punya minimal satu endpoint yang boleh diakses.')
                ->with('errorForm', 'scopes')
                ->with('errorKeyId', (int) $key['id'])
                ->withInput();
        }

        $this->keyScopes->sync((int) $key['id'], $scopes);

        return redirect()
            ->to(base_url('application/' . $id . '/keys'))
            ->with('success', 'Hak akses endpoint untuk key "' . $key['label'] . '" diperbarui.');
    }

    /**
     * Aktifkan / nonaktifkan key.
     *
     * Menonaktifkan dicabut dengan melepas seluruh scope-nya, supaya
     * menyalakan kembali tidak diam-diam memberi akses yang lebih lebar
     * daripada yang disetujui terakhir kali.
     */
    public function toggleStatus(int $id, int $keyId)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        $key = $this->keys->find((int) $keyId);

        if ($key === null || (int) $key['application_id'] !== (int) $id) {
            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('error', 'API key tidak ditemukan pada application ini.');
        }

        $revoking = (string) $key['status'] === ApiKeyModel::STATUS_ACTIVE;

        if ($revoking) {
            $this->keys->setStatus((int) $key['id'], ApiKeyModel::STATUS_REVOKED);
            $this->keyScopes->detachAll((int) $key['id']);

            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('success', 'API key "' . $key['label'] . '" dicabut. Endpoint yang boleh diakses ikut dilepas; centang ulang sebelum menyalakannya.');
        }

        $this->keys->setStatus((int) $key['id'], ApiKeyModel::STATUS_ACTIVE);

        return redirect()
            ->to(base_url('application/' . $id . '/keys'))
            ->with('success', 'API key "' . $key['label'] . '" aktif kembali. Jangan lupa centang endpoint yang boleh diakses.');
    }

    /**
     * Cabut key lama dan buat key baru dengan nama + scope yang sama.
     *
     * Ini jalur yang harus dipakai kalau key pernah bocor: key lama
     * langsung tidak berlaku, pemanggil cukup mengganti credential-nya.
     */
    public function rotate(int $id, int $keyId)
    {
        $application = $this->findManageable($id);

        if ($application === null) {
            return redirect()->to(base_url('application'))->with('error', 'Application tidak ditemukan.');
        }

        $key = $this->keys->find((int) $keyId);

        if ($key === null || (int) $key['application_id'] !== (int) $id) {
            return redirect()
                ->to(base_url('application/' . $id . '/keys'))
                ->with('error', 'API key tidak ditemukan pada application ini.');
        }

        $scopes = $this->keyScopes->forKey((int) $key['id']);
        $label  = (string) $key['label'];

        $this->keys->setStatus((int) $key['id'], ApiKeyModel::STATUS_REVOKED);
        $this->keyScopes->detachAll((int) $key['id']);

        $generated = (new ApiKeyService())->generate();

        $newKeyId = $this->keys->createKey([
            'application_id'        => $id,
            'label'                 => $label,
            'key_prefix'            => $generated['prefix'],
            'key_hash'              => $generated['hash'],
            'expires_at'            => $key['expires_at'] ?? null,
            'rate_limit_per_minute' => $key['rate_limit_per_minute'] ?? null,
        ]);

        $this->keyScopes->sync($newKeyId, $scopes);

        return redirect()
            ->to(base_url('application/' . $id . '/keys'))
            ->with('success', 'API key "' . $label . '" diganti. Key lama langsung tidak berlaku.')
            ->with('new_api_key', $generated['plain'])
            ->with('new_api_key_label', $label);
    }

    /* ------------------------------------------------------------------ *
     *  INTERNAL
     * ------------------------------------------------------------------ */

    /**
     * Ambil application yang BOLEH dikelola user sekarang.
     *
     * Mengembalikan null untuk application milik orang lain supaya
     * controller tidak pernah bisa dilewati hanya dengan mengetik URL.
     *
     * @return array<string,mixed>|null
     */
    private function findManageable(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $application = $this->applications->find($id);

        if ($application === null) {
            return null;
        }

        try {
            $this->access->assertCanManageApiApplication($application);

            return $application;
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{name:string, code:string, description:string}
     */
    private function applicationInput(): array
    {
        return [
            'name'        => trim((string) $this->request->getPost('name')),
            'code'        => trim((string) $this->request->getPost('code')),
            'description' => trim((string) $this->request->getPost('description')),
        ];
    }

    /**
     * @param array{name:string, code:string, description:string} $input
     * @return array<string,string>
     */
    private function validateApplication(array $input, string $code, int $ignoreId): array
    {
        $errors = [];

        if (mb_strlen($input['name']) < 3 || mb_strlen($input['name']) > 100) {
            $errors['name'] = 'Nama application harus 3-100 karakter.';
        } elseif ($this->applications->findByName($input['name']) !== null) {
            $errors['name'] = 'Nama application sudah dipakai.';
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]{2,49}$/', $code)) {
            $errors['code'] = 'Kode harus 3-50 karakter: huruf kecil, angka, dan tanda hubung.';
        } else {
            $existing = $this->applications->findByCode($code);

            if ($existing !== null && (int) $existing['id'] !== $ignoreId) {
                $errors['code'] = 'Kode sudah dipakai application lain.';
            }
        }

        if (mb_strlen($input['description']) > 191) {
            $errors['description'] = 'Deskripsi maksimal 191 karakter.';
        }

        return $errors;
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $slug = trim((string) preg_replace('/-{2,}/', '-', $slug), '-');

        return $slug === '' ? 'app' : substr($slug, 0, 50);
    }

    /**
     * Masa berlaku opsional. String kosong = key tidak pernah kedaluwarsa.
     */
    private function expiresInput(): ?string
    {
        $value = trim((string) $this->request->getPost('expires_at'));

        if ($value === '') {
            return null;
        }

        $time = strtotime($value);

        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Batas request per menit. 0 = tanpa batas.
     */
    private function rateLimitInput(): int
    {
        $value = trim((string) $this->request->getPost('rate_limit_per_minute'));

        if ($value === '' || ! ctype_digit($value)) {
            return 0;
        }

        return max(0, min(1000000, (int) $value));
    }
}