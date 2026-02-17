<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'user';
    protected $returnType = 'array';

    // Tidak perlu primary key
    protected $primaryKey = null;
    protected $useAutoIncrement = false;

    protected $allowedFields = ['id_user', 'password'];
    protected $protectFields = false;

    /**
     * Ambil semua user (user_id & password saja)
     */
    public function getAllUsers()
    {
        return $this->select('id_user, password')->findAll();
    }

    public function getDecryptedUsers()
    {
        return $this->select("
            CAST(AES_DECRYPT(id_user, '" . env('SECRET_USER') . "') AS CHAR) AS id_user,
            CAST(AES_DECRYPT(password, '" . env('SECRET_PASSWORD') . "') AS CHAR) AS password
        ")->findAll();
    }

    /**
     * Validasi user menggunakan AES_ENCRYPT
     */
    public function validateUser($userId, $password)
    {
        $sql = "
            SELECT COUNT(password) AS total
            FROM user
            WHERE id_user = AES_ENCRYPT(?, '" . env('SECRET_USER') . "')
              AND password = AES_ENCRYPT(?, '" . env('SECRET_PASSWORD') . "')
        ";

        $query = $this->db->query($sql, [$userId, $password]);
        return $query->getRowArray();
    }
}
