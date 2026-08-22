<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;

class WebToken extends BaseController
{
    public function index()
    {
        $tokenModel = new \App\Models\ApiV2\TokenModel();
        $userModel = new \App\Models\ApiV2\UserModel();
        $tokens = $tokenModel->listWithUsers();
        $users = $userModel->listWithRoles();
        $defaultUserId = null;

        foreach ($users as $user) {
            if (($user['role_slug'] ?? '') === 'super_admin') {
                $defaultUserId = $user['id'];
                break;
            }
        }

        return view('api_v2/tokens', [
            'tokens' => $tokens,
            'users' => $users,
            'defaultUserId' => $defaultUserId,
        ]);
    }

    public function create()
    {
        $tokenModel = new \App\Models\ApiV2\TokenModel();
        $userId = $this->request->getPost('user_id');
        $expiresAtInput = $this->request->getPost('expires_at') ?: date('Y-m-d H:i:s', strtotime('+30 days'));
        $expiresAtTimestamp = strtotime($expiresAtInput);
        $tokenValue = \Firebase\JWT\JWT::encode([
            'iss' => base_url(),
            'sub' => (int) $userId,
            'iat' => time(),
            'exp' => $expiresAtTimestamp,
            'user_id' => (int) $userId,
            'jti' => bin2hex(random_bytes(16)),
        ], env('JWT_SECRET'), 'HS256');

        $tokenModel->createToken((int) $userId, $tokenValue, $expiresAtInput);

        return redirect()->to('/kb-admin/tokens')->with('success', 'Token berhasil dibuat.');
    }

    public function revoke($id)
    {
        $tokenModel = new \App\Models\ApiV2\TokenModel();
        $tokenModel->revokeToken($id);

        return redirect()->to('/kb-admin/tokens')->with('success', 'Token berhasil dicabut.');
    }
}
