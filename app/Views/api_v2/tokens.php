<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Token</title>
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
        .actions a { color:var(--primary); text-decoration:none; }
        .msg { margin-bottom: 12px; padding: 10px; border-radius: 8px; }
        .msg.success { background:#dcfce7; color:#166534; }
        input, select { width:100%; padding:10px 12px; margin-bottom:10px; border:1px solid var(--border); border-radius:8px; }
        button { padding:10px 14px; background:var(--primary); color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
        .hint { color:var(--muted); font-size: 13px; margin-top: -6px; margin-bottom: 10px; }
        .postman { background:#f8fafc; border:1px solid var(--border); border-radius: 10px; padding: 12px; margin-top: 16px; }
        .postman code { display:block; background:#0f172a; color:#fff; padding:10px; border-radius:6px; white-space:pre-wrap; }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div>
            <h2 style="margin:0;">Manajemen Token</h2>
            <div style="color:var(--muted);">Buat dan cabut token API.</div>
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
        <form method="post" action="/kb-admin/tokens/create">
            <?= csrf_field() ?>
            <label for="user_id">Pilih User</label>
            <select name="user_id" id="user_id" required>
                <?php foreach ($users as $user): ?>
                    <option value="<?= esc($user['id']) ?>" <?= ($defaultUserId !== null && (int) $user['id'] === (int) $defaultUserId) ? 'selected' : '' ?>><?= esc($user['full_name'] . ' (' . ($user['username'] ?? '') . ') - ' . ($user['role_name'] ?? '-')) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Default dipilih user dengan role Super Admin.</div>
            <label for="expires_at">Tanggal Kadaluarsa</label>
            <input type="datetime-local" name="expires_at" id="expires_at" value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>">
            <div class="hint">Default adalah 1 hari setelah hari ini.</div>
            <button type="submit">Buat Token</button>
        </form>

        <div class="postman">
            <strong>Panduan pakai token di Postman / cURL</strong>
            <p>1. Gunakan domain live dari file <code>.env</code>, misalnya <code>https://api-khanzabridge.ademayem.my.id</code>.</p>
            <p>2. Buat token di halaman ini, lalu salin nilai token yang muncul.</p>
            <p>3. Kirim header berikut ke endpoint yang dilindungi:</p>
            <code>Authorization: Bearer &lt;token&gt;</code>
            <p>4. Contoh cek profil (jika endpoint profil tersedia di lingkungan Anda):</p>
            <code>curl -H "Authorization: Bearer &lt;token&gt;" https://api-khanzabridge.ademayem.my.id/api/v2/auth/me</code>
            <p>5. Contoh ambil daftar tabel:</p>
            <code>curl -H "Authorization: Bearer &lt;token&gt;" https://api-khanzabridge.ademayem.my.id/api/v2/database/tables</code>
            <p><strong>Kalau masih mendapat 401</strong>, cek hal berikut:</p>
            <ul>
                <li>Header ditulis persis <code>Authorization: Bearer &lt;token&gt;</code> dengan spasi setelah <code>Bearer</code>.</li>
                <li>Token belum kadaluarsa dan belum dicabut dari tabel token.</li>
                <li>Anda memakai domain yang sama dengan <code>app.baseURL</code> di <code>.env</code>.</li>
                <li>Endpoint yang dipanggil adalah <code>/api/v2/...</code> dan bukan <code>/api/...</code>.</li>
            </ul>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Token</th>
                    <th>Expired</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $token): ?>
                <tr>
                    <td><?= esc($token['id']) ?></td>
                    <td><?= esc($token['full_name'] ?? $token['username'] ?? '-') ?></td>
                    <td><?= esc($token['token']) ?></td>
                    <td><?= esc($token['expires_at']) ?></td>
                    <td><?= esc($token['status']) ?></td>
                    <td class="actions">
                        <a href="/kb-admin/tokens/revoke/<?= esc($token['id'], 'attr') ?>" onclick="return confirm('Cabut token ini?')">Cabut</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
