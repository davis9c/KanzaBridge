<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KB API Dashboard</title>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --text:#0f172a; --muted:#64748b; --primary:#2563eb; --border:#e2e8f0; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, sans-serif; background:var(--bg); color:var(--text); }
        .shell { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar { background: var(--card); border:1px solid var(--border); border-radius: 16px; padding: 18px 22px; display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; }
        .nav a { margin-left: 12px; color: var(--primary); text-decoration:none; font-weight:600; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap: 18px; }
        .card { background: var(--card); border:1px solid var(--border); border-radius: 16px; padding: 20px; box-shadow: 0 6px 18px rgba(15,23,42,.05); }
        .card h3 { margin-top:0; }
        .card p { color: var(--muted); line-height: 1.6; }
        .btn { display:inline-block; margin-top:10px; padding:10px 14px; background:var(--primary); color:#fff; border-radius: 8px; text-decoration:none; font-weight:600; }
        code { background:#f1f5f9; padding:2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div>
            <h2 style="margin:0;">KB API Management</h2>
            <div style="color:var(--muted);">Panel administratif untuk user, token, dan role.</div>
        </div>
        <div class="nav">
            <a href="/kb-admin/dashboard">Dashboard</a>
            <a href="/kb-admin/users">User</a>
            <a href="/kb-admin/tokens">Token</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Manajemen User</h3>
            <p>Kelola akun pengguna, role, dan status aktif/nonaktif.</p>
            <a class="btn" href="/kb-admin/users">Buka User</a>
        </div>
        <div class="card">
            <h3>Manajemen Token</h3>
            <p>Buat, lihat, dan cabut token akses API.</p>
            <a class="btn" href="/kb-admin/tokens">Buka Token</a>
        </div>
        <div class="card">
            <h3>Endpoint API</h3>
            <p>Gunakan prefix <code>/api/v2</code> untuk akses API.</p>
            <a class="btn" href="/kb-admin/setup">Setup Awal</a>
        </div>
    </div>
</div>
</body>
</html>
