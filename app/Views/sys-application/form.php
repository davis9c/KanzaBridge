<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<?php
$isEdit      = ! empty($application);
$action      = $isEdit
    ? base_url('application/edit/' . $application['id'])
    : base_url('application/create');
$oldErrors   = session()->getFlashdata('errors') ?: $errors;
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">
            <a href="<?= base_url('application') ?>">Manajemen Application</a> / <?= esc($title) ?>
        </h1>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?php if (! empty($oldErrors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($oldErrors as $message): ?>
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
                        <h6 class="m-0 font-weight-bold text-primary">Data Application</h6>
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($oldErrors['name']) ? 'is-invalid' : '' ?>"
                                id="name" name="name" required minlength="3" maxlength="100"
                                value="<?= esc(old('name', $application['name'] ?? '')) ?>">
                            <div class="form-text">3-100 karakter, mis. "Modul Registrasi BPM"</div>
                            <?php if (isset($oldErrors['name'])): ?>
                                <div class="invalid-feedback"><?= esc($oldErrors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Kode</label>
                            <input type="text" class="form-control <?= isset($oldErrors['code']) ? 'is-invalid' : '' ?>"
                                id="code" name="code" pattern="[a-z0-9][a-z0-9-]{2,49}" maxlength="50"
                                value="<?= esc(old('code', $application['code'] ?? '')) ?>"
                                placeholder="otomatis dari nama">
                            <div class="form-text">
                                Huruf kecil, angka, dan tanda hubung. Kalau dikosongkan, dibuat dari nama.
                                Dipakai untuk dokumentasi dan mengenali key di log.
                            </div>
                            <?php if (isset($oldErrors['code'])): ?>
                                <div class="invalid-feedback"><?= esc($oldErrors['code']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control <?= isset($oldErrors['description']) ? 'is-invalid' : '' ?>"
                                id="description" name="description" rows="3" maxlength="191"><?= esc(old('description', $application['description'] ?? '')) ?></textarea>
                            <?php if (isset($oldErrors['description'])): ?>
                                <div class="invalid-feedback"><?= esc($oldErrors['description']) ?></div>
                            <?php endif; ?>
                        </div>

                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-fw fa-save me-1"></i> Simpan
                        </button>
                        <a href="<?= base_url('application') ?>" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

            </form>

        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Catatan</h6>
                </div>
                <div class="card-body small">
                    <p>
                        Application adalah identitas pemanggil API. Setelah application dibuat,
                        buat API key di dalamnya, lalu centang endpoint yang boleh diakses.
                    </p>
                    <p class="mb-0">
                        Seluruh endpoint <strong>hanya membaca</strong> data dari
                        <code>sik_beta</code>. Tidak ada endpoint yang menulis ke sana.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>