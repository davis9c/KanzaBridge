<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <div class="row justify-content-center">
        <div class="col-lg-7">

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">

                    <i class="fas fa-user-lock fa-4x text-muted mb-4"></i>

                    <h1 class="h4 mb-3">Akun Anda Belum Memiliki Role</h1>

                    <p class="text-muted mb-4">
                        Login Anda berhasil, tetapi administrator belum memberikan role
                        sehingga belum ada menu yang dapat Anda akses.
                    </p>

                    <div class="card border-0 text-start mx-auto mb-4" style="max-width: 32rem;">
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4 text-muted">Username</dt>
                                <dd class="col-sm-8 mb-2"><?= esc(session()->get('access_username') ?: '-') ?></dd>

                                <dt class="col-sm-4 text-muted">Email</dt>
                                <dd class="col-sm-8 mb-2"><?= esc(session()->get('access_email') ?: '-') ?></dd>

                                <dt class="col-sm-4 text-muted">Role</dt>
                                <dd class="col-sm-8 mb-0">
                                    <span class="badge bg-secondary">Tidak ada</span>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <p class="text-muted small mb-0">
                        Hubungi SuperAdmin untuk meminta akses.
                    </p>

                </div>
            </div>

        </div>
    </div>
</div>

<?= $this->endSection() ?>
