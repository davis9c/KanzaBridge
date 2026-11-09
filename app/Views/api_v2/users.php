<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User</title>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --text:#0f172a; --muted:#64748b; --primary:#2563eb; --border:#e2e8f0; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, sans-serif; background:var(--bg); color:var(--text); }
        .shell { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar { background: var(--card); border:1px solid var(--border); border-radius: 16px; padding: 18px 22px; display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; }
        .nav a { margin-left: 12px; color: var(--primary); text-decoration:none; font-weight:600; }
        .card { background: var(--card); border:1px solid var(--border); border-radius: 16px; padding: 20px; box-shadow: 0 6px 18px rgba(15,23,42,.05); }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th, td { border:1px solid var(--border); padding:10px; text-align:left; }
        th { background:#f8fafc; }
        .actions a { margin-right:8px; color:var(--primary); text-decoration:none; }
        .btn { display:inline-block; padding:10px 14px; background:var(--primary); color:#fff; border-radius:8px; text-decoration:none; font-weight:600; }
        .msg { margin-bottom: 12px; padding: 10px; border-radius: 8px; }
        .msg.success { background:#dcfce7; color:#166534; }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div>
            <h2 style="margin:0;">Manajemen User</h2>
            <div style="color:var(--muted);">Kelola akun pengguna dan role.</div>
        </div>
        <div class="nav">
            <a href="/kb-admin/dashboard">Dashboard</a>
            <a href="/kb-admin/users">User</a>
            <a href="/kb-admin/tokens">Token</a>
        </div>
    </div>

    <div class="card">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="msg success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <a class="btn" href="/kb-admin/users/new">Tambah User</a>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= esc($user['id']) ?></td>
                    <td><?= esc($user['username']) ?></td>
                    <td><?= esc($user['full_name']) ?></td>
                    <td><?= esc($user['email']) ?></td>
                    <td><?= esc($user['role_name'] ?? $user['role_slug'] ?? '-') ?></td>
                    <td><?= esc($user['status']) ?></td>
                    <td class="actions">
                        <a href="/kb-admin/users/edit/<?= esc($user['id'], 'attr') ?>">Edit</a>
                        <a href="/kb-admin/users/delete/<?= esc($user['id'], 'attr') ?>" onclick="return confirm('Hapus user ini?')">Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
