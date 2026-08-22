<?php

namespace App\Models\ApiV2;

use CodeIgniter\Model;

class TokenModel extends Model
{
    protected $table = 'tb_kb_token';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = ['user_id', 'token', 'expires_at', 'revoked_at', 'status'];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    public function createToken(int $userId, string $tokenValue, string $expiresAt): int
    {
        $this->insert([
            'user_id' => $userId,
            'token' => $tokenValue,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        return (int) $this->insertID();
    }

    public function findActiveByToken(string $tokenValue): ?array
    {
        return $this->where('token', $tokenValue)
            ->where('status', 'active')
            ->where('revoked_at', null)
            ->first();
    }

    public function listForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->findAll();
    }

    public function listAll(): array
    {
        return $this->findAll();
    }

    public function listWithUsers(): array
    {
        return $this->select('tb_kb_token.*, tb_kb_user.username, tb_kb_user.full_name')
            ->join('tb_kb_user', 'tb_kb_user.id = tb_kb_token.user_id', 'left')
            ->orderBy('tb_kb_token.id', 'ASC')
            ->findAll();
    }

    public function revokeToken(int $id): void
    {
        $this->update($id, ['status' => 'revoked', 'revoked_at' => date('Y-m-d H:i:s')]);
    }
}
