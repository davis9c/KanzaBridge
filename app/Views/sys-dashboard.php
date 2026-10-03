<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Dashboard</h1>
        <?php // Halaman ini sudah di balik filter access, jadi user yang
              // sampai sini pasti punya role. ?>
        <a href="<?= base_url('application') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-fw fa-cubes me-1"></i> Kelola Application
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php /* App\Filters\AccessFilter mengarahkan user yang tidak berhak ke
             halaman ini dengan flash `error` — jadi flash itu WAJIB ditampilkan,
             kalau tidak user mendarat tanpa penjelasan apa pun. */ ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <!-- Kartu metrik. Daftar key ada di menu Application, bukan di sini. -->
    <?= $this->include('sys-dashboard/ringkasan') ?>
</div>

<?= $this->endSection() ?>