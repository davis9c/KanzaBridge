<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form User</title>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --text:#0f172a; --muted:#64748b; --primary:#2563eb; --border:#e2e8f0; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, sans-serif; background:var(--bg); color:var(--text); }
        .shell { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border:1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 6px 18px rgba(15,23,42,.05); }
        input, select { width:100%; padding:10px 12px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px; }
        button { padding:10px 16px; background:var(--primary); color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
        .link { display:inline-block; margin-bottom: 14px; color:var(--primary); text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <a class="link" href="/kb-admin/users">&larr; Kembali</a>
        <h2><?= $user ? 'Edit User' : 'Tambah User' ?></h2>
        <form method="post" action="<?= $user ? '/kb-admin/users/update/' . $user['id'] : '/kb-admin/users/save' ?>">
            <?= csrf_field() ?>
            <input type="text" name="username" placeholder="Username" value="<?= esc($user['username'] ?? '') ?>" <?= $user ? 'readonly' : 'required' ?>>
            <input type="email" name="email" placeholder="Email" value="<?= esc($user['email'] ?? '') ?>" required>
            <input type="text" name="full_name" placeholder="Nama Lengkap" value="<?= esc($user['full_name'] ?? '') ?>" required>
            <input type="password" name="password" placeholder="Password <?= $user ? '(opsional)' : '' ?>" <?= $user ? '' : 'required' ?>>
            <select name="role_id">
                <?php foreach ($roles as $role): ?>
                    <option value="<?= esc($role['id']) ?>" <?= ($user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>><?= esc($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="active" <?= ($user['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($user['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit">Simpan</button>
        </form>
    </div>
</div>
</body>
</html>
