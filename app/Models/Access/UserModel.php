<?php

namespace App\Models\Access;

use CodeIgniter\Database\BaseBuilder as BuilderInterface;
use CodeIgniter\Model;

/**
 * User lokal.
 *
 * Password TIDAK disimpan di sini — autentikasi dijalankan sepenuhnya oleh
 * UserGate. Tabel ini menyimpan salinan identitas (username, email,
 * nama) untuk tampilan, ditambah status akses dan link NIK.
 *
 * Sengaja terpisah dari App\Models\UserModel (tabel `user` di khanza)
 * yang dipakai lapisan API yang sudah live.
 */
class UserModel extends Model
{
    protected $DBGroup = 'default';

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $allowedFields = [
        'usergate_id',
        'username',
        'email',
        'full_name',
        'status',
        'last_login_at',
    ];

    protected $useSoftDeletes = false;

    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    public function findByUserGateId(string $usergateId): ?array
    {
        if ($usergateId === '') {
            return null;
        }

        return $this->where('usergate_id', $usergateId)->first();
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    /**
     * Daftar user dengan nama role-nya, untuk UI Manajemen User.
     *
     * @param  array{search?:string, status?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function listWithRoles(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        $rows = $this->filteredQuery($search, $status)
            ->orderBy('users.full_name', 'ASC')
            ->get()
            ->getResultArray();

        // Role diambil per user, bukan lewat JOIN + GROUP_CONCAT, supaya
        // urutannya deterministik dan tidak bergantung mode SQL server.
        $userRoles = new UserRoleModel();

        foreach ($rows as $index => $row) {
            $rows[$index]['role_names'] = $userRoles->roleNamesFor((int) $row['id']);
        }

        return $rows;
    }

    /**
     * Peta index kolom tabel di browser -> kolom yang boleh diurutkan.
     *
     * Kolom "Role" (index 3) tidak ada di sini: role diambil lewat tabel
     * terpisah, bukan kolom di `users`, jadi tidak bisa diurutkan di SQL.
     * Kolom "Aksi" (index 6) juga tidak sortable.
     *
     * @var array<int,string>
     */
    public const SORTABLE_COLUMNS = [
        0 => 'users.username',
        1 => 'users.full_name',
        2 => 'users.email',
        4 => 'users.status',
        5 => 'users.last_login_at',
    ];

    /**
     * Jumlah user yang cocok dengan pencarian — `recordsFiltered` DataTables.
     */
    public function countVisibleFiltered(string $search, string $status): int
    {
        return $this->filteredQuery(trim($search), trim($status))->countAllResults();
    }

    /**
     * Jumlah seluruh user — `recordsTotal`.
     */
    public function countAll(): int
    {
        return $this->builder()->countAllResults();
    }

    /**
     * Satu halaman user untuk DataTables server-side.
     *
     * $orderBy harus sudah lolos whitelist self::SORTABLE_COLUMNS; nilai
     * dari request tidak boleh diteruskan ke sini apa adanya karena masuk
     * ke klausa ORDER BY.
     *
     * @param  string       $orderBy Nama kolom yang sudah divalidasi.
     * @param  'asc'|'desc' $dir
     * @return list<array<string,mixed>>
     */
    public function listVisiblePaged(
        string $search,
        string $status,
        int $limit,
        int $offset,
        string $orderBy,
        string $dir = 'asc'
    ): array {
        $builder = $this->filteredQuery(trim($search), trim($status))
            ->orderBy($orderBy, $dir);

        if ($limit > 0) {
            $builder->limit($limit, max(0, $offset));
        }

        $rows = $builder->get()->getResultArray();

        // Role hanya diambil untuk baris di halaman ini. Id dan nama diambil
        // sekali per baris: `role_ids` dipakai client untuk mencentang checkbox
        // di modal ubah, `role_names` untuk kolom Role dan penentuan SuperAdmin.
        $userRoles = new UserRoleModel();

        foreach ($rows as $index => $row) {
            $roles = $userRoles->rolesFor((int) $row['id']);

            $rows[$index]['role_names'] = array_column($roles, 'name');
            $rows[$index]['role_ids']  = array_map(
                static fn (array $role): int => $role['role_id'],
                $roles
            );
        }

        return $rows;
    }

    /**
     * Query dasar dengan pencarian dan filter status.
     *
     * Semua method publik memakai ini supaya tidak mungkin ada jalur yang
     * lupa salah satu filter.
     */
    private function filteredQuery(string $search, string $status): BuilderInterface
    {
        $builder = $this->builder();

        if ($search !== '') {
            $builder->groupStart()
                ->like('users.username', $search)
                ->orLike('users.email', $search)
                ->orLike('users.full_name', $search)
                ->groupEnd();
        }

        if ($status !== '') {
            $builder->where('users.status', $status);
        }

        return $builder;
    }

    /**
     * Buat user lokal baru. Dipakai setelah berhasil membuat akun di
     * UserGate, sehingga UUID UserGate sudah diketahui.
     *
     * Default status adalah NONAKTIF. Akun yang baru dibuat — lewat tombol
     * "Tambah User" maupun hasil login pertama kali dari UserGate — tidak
     * otomatis boleh masuk KanzaBridge; administrator harus mengaktifkannya
     * lebih dulu.
     *
     * Default di model, bukan hanya di call site, supaya jalur kode baru yang
     * lupa mengeset `status` gagal tertutup dan bukan diam-diam memberi akses.
     * Role dan status sengaja independen: user yang diberi role ADMIN pun
     * tetap nonaktif sampai diaktifkan.
     *
     * @param array<string,mixed> $data
     */
    public function createLocal(array $data): int
    {
        $this->insert([
            'usergate_id' => (string) $data['usergate_id'],
            'username'    => (string) $data['username'],
            'email'       => (string) $data['email'],
            'full_name'   => (string) $data['full_name'],
            'status'      => (string) ($data['status'] ?? self::STATUS_INACTIVE),
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * Samakan data lokal dengan data terbaru dari UserGate.
     *
     * Hanya menyalin field identitas — role dan status lokal tidak
     * pernah ditimpa dari luar.
     *
     * @param  array<string,mixed> $user
     * @return array<string,mixed> Baris user lokal setelah pembaruan.
     */
    public function syncFromUserGate(int $localId, array $user): array
    {
        $update = [];

        foreach (['username' => 'username', 'email' => 'email', 'full_name' => 'full_name'] as $local => $remote) {
            $value = trim((string) ($user[$remote] ?? ''));
            if ($value !== '') {
                $update[$local] = $value;
            }
        }

        if ($update !== []) {
            $this->update($localId, $update);
        }

        return $this->find($localId) ?? [];
    }

    public function markLogin(int $localId): void
    {
        $this->update($localId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public function setStatus(int $localId, string $status): void
    {
        $this->update($localId, ['status' => $status]);
    }

    /**
     * Jumlah user lokal. Dipakai aturan "user pertama = SuperAdmin".
     */
    public function total(): int
    {
        return $this->countAllResults();
    }
}
