<?php

namespace App\Models\Access;

use CodeIgniter\Model;

/**
 * API key untuk autentikasi API V2.
 *
 * Tabel `api_keys` di group `default` (khanzabridge).
 *
 * Yang disimpan hanya hash SHA-256 dari key. Key plaintext hidup paling
 * lama sesaat setelah dibuat dan tidak pernah bisa dibaca ulang dari
 * database — kalau hilang, yang bisa dilakukan adalah membuat key baru.
 */
class ApiKeyModel extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $DBGroup = 'default';

    protected $table = 'api_keys';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $allowedFields = [
        'application_id',
        'label',
        'key_prefix',
        'key_hash',
        'status',
        'expires_at',
        'rate_limit_per_minute',
        'last_used_at',
    ];

    protected $useSoftDeletes = false;

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_REVOKED];
    }

    /**
     * Baris key + nama application pemiliknya.
     */
    public function findWithApplication(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->builder()
            ->select('api_keys.*, api_applications.name AS application_name, api_applications.code AS application_code, api_applications.created_by AS application_owner_id')
            ->join('api_applications', 'api_applications.id = api_keys.application_id', 'inner')
            ->where('api_keys.id', $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Pencocokan saat autentikasi request.
     *
     * Hanya consult index unik `key_hash`; tidak ada pemindaian tabel.
     */
    public function findByHash(string $hash): ?array
    {
        if ($hash === '') {
            return null;
        }

        return $this->where('key_hash', $hash)->first();
    }

    /**
 * Semua key milik sebuah application, lengkap dengan scope-nya.
     *
     * @return list<array<string,mixed>>
     */
    public function listForApplication(int $applicationId): array
    {
        if ($applicationId <= 0) {
            return [];
        }

        $rows = $this->where('application_id', $applicationId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return $this->attachScopes($rows);
    }

    /**
     * Semua key yang boleh dilihat user tertentu, lengkap dengan nama
     * application-nya dan daftar scope-nya.
     *
     * Dipakai halaman "Penggunaan" yang mencakup semua application,
     * bukan cuma satu. Aturan kepemilikannya sama persis dengan
     * ApiApplicationModel::listVisible(): SuperAdmin melihat semua,
     * Admin hanya application miliknya sendiri.
     *
     * @param  array{search?:string, status?:string, unusedOnly?:bool} $filters
     * @return list<array<string,mixed>>
     */
    public function listForOwner(bool $isSuperAdmin, int $userId, array $filters = []): array
    {
        $builder = $this->builder()
            ->select('api_keys.*, api_applications.name AS application_name, api_applications.code AS application_code')
            ->join('api_applications', 'api_applications.id = api_keys.application_id', 'inner')
            ->orderBy('api_applications.name', 'ASC')
            ->orderBy('api_keys.label', 'ASC');

        if (! $isSuperAdmin) {
            $builder->where('api_applications.created_by', $userId);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $builder->groupStart()
                ->like('api_applications.name', $search)
                ->orLike('api_applications.code', $search)
                ->orLike('api_keys.label', $search)
                ->orLike('api_keys.key_prefix', $search)
                ->groupEnd();
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status !== '') {
            $builder->where('api_keys.status', $status);
        }

        if (! empty($filters['unusedOnly'])) {
            $builder->where('api_keys.last_used_at IS NULL');
        }

        return $this->attachScopes($builder->get()->getResultArray());
    }

    /**
     * Ringkasan jumlah key per application untuk halaman daftar.
     *
     * @param  list<int> $applicationIds
     * @return array<int, array{total:int, active:int}>
     */
    public function countsForApplications(array $applicationIds): array
    {
        $applicationIds = array_values(array_unique(array_map('intval', $applicationIds)));

        if ($applicationIds === []) {
            return [];
        }

        $rows = $this->builder()
            ->select('application_id, status, COUNT(*) AS total')
            ->whereIn('application_id', $applicationIds)
            ->groupBy(['application_id', 'status'])
            ->get()
            ->getResultArray();

        $counts = [];

        foreach ($applicationIds as $id) {
            $counts[$id] = ['total' => 0, 'active' => 0];
        }

        foreach ($rows as $row) {
            $id = (int) $row['application_id'];

            $counts[$id]['total'] += (int) $row['total'];

            if ((string) $row['status'] === self::STATUS_ACTIVE) {
                $counts[$id]['active'] += (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function createKey(array $data): int
    {
        $this->insert([
            'application_id'       => (int) $data['application_id'],
            'label'                => (string) $data['label'],
            'key_prefix'           => (string) $data['key_prefix'],
            'key_hash'             => (string) $data['key_hash'],
            'status'               => (string) ($data['status'] ?? self::STATUS_ACTIVE),
            'expires_at'           => $data['expires_at'] ?? null,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
        ]);

        return (int) $this->getInsertID();
    }

    public function setStatus(int $id, string $status): void
    {
        $this->update($id, ['status' => $status]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateMeta(int $id, array $data): void
    {
        $update = [];

        foreach (['label', 'expires_at', 'rate_limit_per_minute'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($update !== []) {
            $this->update($id, $update);
        }
    }

    public function touchLastUsed(int $id): void
    {
        // Bypass proteksi kolom tanggal: kita memang hanya ingin mengisi
        // satu kolom tanpa menyentuh updated_at.
        $this->builder()
            ->where('id', $id)
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function deleteKey(int $id): void
    {
        if ($id > 0) {
            $this->delete($id);
        }
    }

    /**
     * Key aktif dan belum kedaluwarsa milik sebuah application.
     *
     * @return list<array<string,mixed>>
     */
    public function activeForApplication(int $applicationId): array
    {
        return $this->where('application_id', $applicationId)
            ->where('status', self::STATUS_ACTIVE)
            ->findAll();
    }

    /**
     * Tambahkan daftar scope ke tiap baris key.
     *
     * Scope diambil per key (bukan GROUP_CONCAT) supaya urutannya
     * deterministik dan tidak bergantung mode SQL server.
     *
     * @param  list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function attachScopes(array $rows): array
    {
        $scopes = new ApiKeyScopeModel();

        foreach ($rows as $index => $row) {
            $rows[$index]['scopes'] = $scopes->forKey((int) $row['id']);
        }

        return $rows;
    }
}
