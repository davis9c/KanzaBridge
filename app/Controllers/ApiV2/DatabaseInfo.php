<?php

namespace App\Controllers\ApiV2;

use CodeIgniter\HTTP\ResponseInterface;

class DatabaseInfo extends BaseController
{
    public function tables()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $db = \Config\Database::connect();
        $tables = $db->listTables();

        $payload = array_map(static function (string $table) {
            return [
                'name' => $table,
            ];
        }, $tables);

        return $this->respondSuccess([
            'tables' => $payload,
            'count' => count($payload),
            'meta' => [
                'resource' => 'database.tables',
                'generated_at' => date('c'),
            ],
        ], 'Daftar tabel database berhasil diambil');
    }

    public function pegawai()
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $offset = (int) ($this->request->getGet('offset') ?? 0);
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $model = new \App\Models\PegawaiModel();
        $builder = $model->builder();
        $builder->select('id, nik, nama');

        if ($search !== '') {
            $builder->groupStart()
                ->like('nama', $search)
                ->orLike('nik', $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $builder->limit($limit, $offset);
        $rows = $builder->get()->getResultArray();

        return $this->respondSuccess([
            'items' => $rows,
            'count' => count($rows),
            'total' => $total,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
            'meta' => [
                'resource' => 'pegawai',
                'generated_at' => date('c'),
            ],
        ], 'Data pegawai berhasil diambil');
    }

    public function pegawaiById($id)
    {
        $auth = $this->requireAuth();

        if ($auth instanceof ResponseInterface) {
            return $auth;
        }

        $model = new \App\Models\PegawaiModel();
        $row = $model->select('id, nik, nama')->find($id);

        if (! $row) {
            return $this->respondError('Data pegawai tidak ditemukan', 404);
        }

        return $this->respondSuccess([
            'item' => $row,
            'meta' => [
                'resource' => 'pegawai.detail',
                'generated_at' => date('c'),
            ],
        ], 'Detail pegawai berhasil diambil');
    }
}
