<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">
            <a class="dropdown-item" href="<?= base_url('profile') ?>">Profil</a> / <?= $title ?><?= !empty($edit) ? '/ ' . $user['name'] : null ?>
        </h1>
    </div>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success'); ?></div>
    <?php endif; ?>
    <div class="row">
        <?= $this->include('sys-profile/profile') ?>
    </div>
</div>

<?= $this->endSection() ?>
