<?php

namespace App\Models\Access;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Application pemanggil API.
 *
 * Berisi tabel `api_applications` di group `default` (khanzabridge),
 * bukan data SIMRS. Tabel `api_*` adalah tabel milik KanzaBridge
 * sehingga diletakkan bersama `users`/`roles`/`user_roles`.
 *
 * `created_by` adalah dasar aturan kepemilikan: SuperAdmin boleh
 * mengelola semua application, Admin hanya application yang ia buat.
 */
class ApiApplicationModel extends Model
{
    protected $DBGroup = 'default';

    protected $table = 'api_applications';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $allowedFields = [
        'name',
        'code',
        'description',
        'created_by',
    ];

    protected $useSoftDeletes = false;

    public function findByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        return $this->where('code', $code)->first();
    }

    public function findByName(string $name): ?array
    {
        if ($name === '') {
            return null;
        }

        return $this->where('name', $name)->first();
    }

    /**
     * Daftar application yang boleh dilihat user tertentu.
     *
     * SuperAdmin (true) melihat semuanya. Admin hanya melihat
     * application yang ia buat sendiri.
     *
     * @return list<array<string,mixed>>
     */
    public function listVisible(bool $isSuperAdmin, int $userId, array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->visibleQuery($isSuperAdmin, $userId, $search)
            ->orderBy('api_applications.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Jumlah application yang boleh dilihat user, TANPA memperhitungkan
     * pencarian.
     *
     * Dipakai sebagai `recordsTotal` DataTables. Penting: persis seperti
     * listVisible(), jadi hanya menghitung application milik user itu
     * sendiri. Kalau dihitung secara global, Admin bisa menebak bahwa
     * ada application milik orang lain hanya dari angkanya.
     */
    public function countVisible(bool $isSuperAdmin, int $userId): int
    {
        return $this->visibleQuery($isSuperAdmin, $userId, '')
            ->countAllResults();
    }

    /**
     * Jumlah application yang lolos pencarian — `recordsFiltered`.
     *
     * Aturan kepemilikan tetap sama, jadi angkanya tidak pernah lebih
     * besar daripada countVisible().
     */
    public function countVisibleFiltered(bool $isSuperAdmin, int $userId, string $search): int
    {
        return $this->visibleQuery($isSuperAdmin, $userId, trim($search))
            ->countAllResults();
    }

    /**
     * Satu halaman application untuk DataTables server-side.
     *
     * $orderBy dan $dir harus SUDAH divalidasi pemanggil lewat
     * self::SORTABLE_COLUMNS. Nilai dari request tidak boleh diteruskan
     * ke sini apa adanya, karena keduanya masuk ke klausa ORDER BY.
     *
     * @param  string             $orderBy Nama kolom yang sudah lolos whitelist.
     * @param  'asc'|'desc'       $dir
     * @return list<array<string,mixed>>
     */
    public function listVisiblePaged(
        bool $isSuperAdmin,
        int $userId,
        string $search,
        int $limit,
        int $offset,
        string $orderBy,
        string $dir = 'asc'
    ): array {
        $builder = $this->visibleQuery($isSuperAdmin, $userId, $search)
            ->orderBy($orderBy, $dir);

        // offset >= 0 dijamin controller; limit === 0 berarti "jangan ambil
        // baris", yang sah untuk halaman di luar jangkauan.
        if ($limit > 0) {
            $builder->limit($limit, max(0, $offset));
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Peta index kolom tabel di browser -> kolom yang boleh diurutkan.
     *
     * Kolom 2 (jumlah API key) dan kolom 5 (aksi) sengaja tidak ada:
     * keduanya tidak disimpan sebagai kolom di `api_applications`, jadi
     * tidak bisa diurutkan di SQL. Kolom yang tidak ada di peta ini akan
     * diabaikan pemanggil dan jatuh ke urutan bawaan.
     *
     * @var array<int,string>
     */
    public const SORTABLE_COLUMNS = [
        0 => 'api_applications.name',
        1 => 'api_applications.code',
        3 => 'users.full_name',
        4 => 'api_applications.created_at',
    ];

    /**
     * Query dasar dengan aturan kepemilikan dan pencarian.
     *
     * Semua method publik di atas memakai ini, jadi tidak mungkin ada
     * jalur yang lupa membatasi application milik user lain.
     */
    private function visibleQuery(bool $isSuperAdmin, int $userId, string $search): BaseBuilder
    {
        $builder = $this->builder()
            ->select('api_applications.*, users.username AS owner_username, users.full_name AS owner_name')
            ->join('users', 'users.id = api_applications.created_by', 'left');

        if (! $isSuperAdmin) {
            // Hanya milik sendiri. Application tanpa owner pun disembunyikan:
            // kalau ditampilkan, Admin akan melihat baris yang tidak bisa
            // ia buka — hanya membingungkan.
            $builder->where('api_applications.created_by', $userId);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('api_applications.name', $search)
                ->orLike('api_applications.code', $search)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * Buat application baru.
     *
     * @param array<string,mixed> $data
     */
    public function createApplication(array $data): int
    {
        $this->insert([
            'name'        => (string) $data['name'],
            'code'        => (string) $data['code'],
            'description' => $data['description'] ?? null,
            'created_by'  => isset($data['created_by']) && $data['created_by'] !== null
                ? (int) $data['created_by']
                : null,
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateApplication(int $id, array $data): void
    {
        $update = [];

        foreach (['name', 'code', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field] === null ? null : (string) $data[$field];
            }
        }

        if ($update !== []) {
            $this->update($id, $update);
        }
    }

    public function deleteApplication(int $id): void
    {
        if ($id > 0) {
            $this->delete($id);
        }
    }

    public function total(): int
    {
        return $this->countAllResults();
    }
}
