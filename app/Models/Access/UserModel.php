<?php

namespace App\Models\Access;

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
        $builder = $this->orderBy('users.full_name', 'ASC');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $builder->groupStart()
                ->like('users.username', $search)
                ->orLike('users.email', $search)
                ->orLike('users.full_name', $search)
                ->groupEnd();
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $builder->where('users.status', $status);
        }

        $rows = $builder->findAll();

        // Role diambil per user, bukan lewat JOIN + GROUP_CONCAT, supaya
        // urutannya deterministik dan tidak bergantung mode SQL server.
        $userRoles = new UserRoleModel();

        foreach ($rows as $index => $row) {
            $rows[$index]['role_names'] = $userRoles->roleNamesFor((int) $row['id']);
        }

        return $rows;
    }

    /**
     * Buat user lokal baru. Dipakai setelah berhasil membuat akun di
     * UserGate, sehingga UUID UserGate sudah diketahui.
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
            'status'      => (string) ($data['status'] ?? self::STATUS_ACTIVE),
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
