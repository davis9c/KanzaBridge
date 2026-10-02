<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Manajemen User</h1>
        <a href="<?= base_url('user/create') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-fw fa-plus me-1"></i> Tambah User
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form action="<?= base_url('user') ?>" method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label small mb-1">Cari</label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                        value="<?= esc($search) ?>" placeholder="Username, email, atau nama">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="">Semua</option>
                        <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Aktif</option>
                        <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-fw fa-filter me-1"></i> Terapkan
                    </button>
                    <a href="<?= base_url('user') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Daftar User <span class="badge bg-secondary"><?= count($users) ?></span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Tidak ada user.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $u): ?>
                        <?php $isSelf = (int) $u['id'] === current_user_id(); ?>
                        <tr>
                            <td>
                                <i class="fas fa-user me-1 text-muted"></i><?= esc($u['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span class="badge bg-info text-dark ms-1">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($u['full_name']) ?></td>
                            <td><?= esc($u['email']) ?></td>
                            <td>
                                <?php if ($u['role_names'] === []): ?>
                                    <span class="badge bg-secondary">Tanpa role</span>
                                <?php else: ?>
                                    <?php foreach ($u['role_names'] as $role): ?>
                                        <span class="badge bg-<?= $role === 'SUPER_ADMIN' ? 'warning text-dark' : 'primary' ?> me-1">
                                            <?= esc(role_label($role)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'ACTIVE' ? 'success' : 'danger' ?>">
                                    <?= esc($u['status']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= esc($u['last_login_at'] ?? '-') ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= base_url('user/edit/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-fw fa-pen"></i>
                                </a>

                                <form action="<?= base_url('user/toggle-status/' . $u['id']) ?>" method="POST"
                                    class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        <?= $isSelf ? 'disabled' : '' ?>
                                        title="<?= $u['status'] === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="fas fa-fw <?= $u['status'] === 'ACTIVE' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                    </button>
                                </form>

                                <?php if (can('user.delete')): ?>
                                    <form action="<?= base_url('user/delete/' . $u['id']) ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            <?= $isSelf ? 'disabled' : '' ?>
                                            onclick="return confirm('Hapus user &quot;<?= esc($u['username']) ?>&quot; dari UserGate? Tindakan ini tidak dapat dibatalkan.')"
                                            title="Hapus">
                                            <i class="fas fa-fw fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle me-1"></i>
            Akun dibuat di UserGate. Role di bawah disimpan di database lokal
            KanzaBridge dan tidak berasal dari UserGate.
            <?php if (! can('user.delete')): ?>
                <br>Penghapusan user dan penetapan role SuperAdmin hanya dapat dilakukan oleh SuperAdmin.
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
