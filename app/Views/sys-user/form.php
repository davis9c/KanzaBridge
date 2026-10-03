<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<?php
/*
 * Halaman ubah sebagai fallback kalau JavaScript tidak jalan.
 *
 * Tambah user memakai modal di halaman /user, jadi form ini tidak lagi
 * punya cabang create: field password dan action create sudah dihapus
 * bersama route GET user/create.
 */
$action = base_url('user/edit/' . $user['id']);
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">
            <a href="<?= base_url('user') ?>">Manajemen User</a> / <?= esc($title) ?>
        </h1>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?php if (! empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $message): ?>
                    <li><?= esc($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7">

            <form action="<?= $action ?>" method="POST">
                <?= csrf_field() ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Data Akun (UserGate)</h6>
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                id="username" name="username" required
                                pattern="[A-Za-z0-9]{3,100}"
                                value="<?= esc(old('username', $user['username'] ?? '')) ?>">
                            <div class="form-text">3-100 karakter, alfanumerik saja.</div>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= esc($errors['username']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                id="email" name="email" required
                                value="<?= esc(old('email', $user['email'] ?? '')) ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= esc($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                id="full_name" name="full_name" required minlength="3" maxlength="150"
                                value="<?= esc(old('full_name', $user['full_name'] ?? '')) ?>">
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= esc($errors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            UserGate tidak menyediakan endpoint ubah password. Password hanya bisa
                            diubah dengan menghapus user lalu membuatnya kembali.
                        </div>

                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Role (disimpan lokal)</h6>
                    </div>
                    <div class="card-body">

                        <?php if ($roles === []): ?>
                            <p class="text-muted small mb-0">
                                Belum ada role yang tersedia. Jalankan
                                <code>php spark db:seed RoleSeeder</code>.
                            </p>
                        <?php else: ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" disabled checked>
                                <label class="form-check-label">
                                    <strong>Tanpa role</strong>
                                    <span class="d-block small text-muted">
                                        User dapat login, tetapi tidak melihat menu apa pun.
                                    </span>
                                </label>
                            </div>
                            <hr>

                            <?php foreach ($roles as $role): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="roles[]"
                                        value="<?= (int) $role['id'] ?>" id="role-<?= (int) $role['id'] ?>"
                                        <?= in_array((int) $role['id'], $assigned ?? [], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="role-<?= (int) $role['id'] ?>">
                                        <strong><?= esc(role_label($role['name'])) ?></strong>
                                        <?php if ((int) $role['is_super'] === 1): ?>
                                            <span class="badge bg-warning text-dark ms-1">SuperAdmin</span>
                                        <?php endif; ?>
                                        <span class="d-block small text-muted"><?= esc($role['description'] ?? '') ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (! can('user.promote')): ?>
                            <div class="alert alert-secondary small mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Penetapan role SuperAdmin hanya dapat dilakukan oleh SuperAdmin.
                            </div>
                        <?php endif; ?>

                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-fw fa-save me-1"></i> Simpan
                        </button>
                        <a href="<?= base_url('user') ?>" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<?= $this->endSection() ?>
