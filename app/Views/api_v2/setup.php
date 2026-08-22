<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KB API Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; background: #f7f7f7; }
        .card { max-width: 480px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        input { width: 100%; padding: 10px; margin-bottom: 12px; }
        button { padding: 10px 16px; background: #2563eb; color: #fff; border: none; cursor: pointer; }
        .msg { margin-bottom: 12px; padding: 10px; border-radius: 4px; }
        .msg.success { background: #dcfce7; color: #166534; }
        .msg.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="card">
    <h2>Setup Super Admin</h2>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="msg success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="msg error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>
    <form method="post" action="/kb-admin/setup">
        <?= csrf_field() ?>
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="full_name" placeholder="Nama Lengkap" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Buat Super Admin</button>
    </form>
</div>
</body>
</html>
