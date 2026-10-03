<div class="col-lg-6">
    <div class="card shadow mb-4">

        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Identitas Akun</h6>
        </div>

        <div class="card-body">

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success">
                    <?= esc(session()->getFlashdata('success')) ?>
                </div>
            <?php endif; ?>

            <table class="table table-striped mb-0">
                <tr>
                    <th width="30%">Username</th>
                    <td><?= esc($user['username'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Nama Lengkap</th>
                    <td><?= esc($user['full_name'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= esc($user['email'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td>
                        <?php if (($roles ?? []) === []): ?>
                            <span class="badge bg-secondary">Tanpa role</span>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <span class="badge bg-<?= $role === 'SUPER_ADMIN' ? 'warning text-dark' : 'primary' ?> me-1">
                                    <?= esc(role_label($role)) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge bg-<?= ($user['status'] ?? '') === 'ACTIVE' ? 'success' : 'danger' ?>">
                            <?= esc($user['status'] ?? '-') ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Login Terakhir</th>
                    <td><?= esc($user['last_login_at'] ?? '-') ?></td>
                </tr>
            </table>

        </div>

        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle me-1"></i>
            Data akun bersumber dari UserGate. Hubungi administrator untuk mengubahnya.
        </div>
    </div>
</div>
