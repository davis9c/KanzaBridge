<?php

namespace App\Models\Access;

use CodeIgniter\Model;

/**
 * Scope (permission endpoint) milik sebuah API key.
 *
 * Tabel `api_key_scopes` di group `default` (khanzabridge).
 *
 * Kode scope yang sah didefinisikan di Config\ApiScope. Model ini
 * tetap menyimpan apa adanya; penyaringan kode tak dikenal dilakukan
 * di lapisan controller/helper supaya tidak ada satu pun baris dengan
 * scope yang tidak dikenal di database.
 */
class ApiKeyScopeModel extends Model
{
    protected $DBGroup = 'default';

    protected $table = 'api_key_scopes';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    // Tabel ini hanya punya created_at — baris dicatat sekali dan tidak
    // pernah diubah. Kosongkan updatedField agar CI4 tidak menulis kolom
    // yang tidak ada.
    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $updatedField = '';

    protected $allowedFields = [
        'api_key_id',
        'scope',
    ];

    protected $useSoftDeletes = false;

    /**
     * Scope milik satu key.
     *
     * @return list<string>
     */
    public function forKey(int $keyId): array
    {
        if ($keyId <= 0) {
            return [];
        }

        $rows = $this->where('api_key_id', $keyId)
            ->orderBy('scope', 'ASC')
            ->findAll();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['scope'],
            $rows
        ));
    }

    /**
     * Ganti seluruh scope sebuah key dengan daftar yang diberikan.
     *
     * Idempotent, dan aman dipanggil dengan daftar kosong (artinya cabut
     * semua akses endpoint).
     *
     * @param list<string> $scopes
     */
    public function sync(int $keyId, array $scopes): void
    {
        if ($keyId <= 0) {
            return;
        }

        $scopes = array_values(array_unique(array_map('strval', $scopes)));

        $this->db->transStart();
        $this->where('api_key_id', $keyId)->delete();

        foreach ($scopes as $scope) {
            if ($scope !== '') {
                $this->insert(['api_key_id' => $keyId, 'scope' => $scope]);
            }
        }

        $this->db->transComplete();
    }

    /**
     * Cabut semua scope sebuah key. Dipakai saat key dicabut statusnya,
     * supaya mencabut key benar-benar memutus akses endpoint.
     */
    public function detachAll(int $keyId): void
    {
        if ($keyId > 0) {
            $this->where('api_key_id', $keyId)->delete();
        }
    }
}
