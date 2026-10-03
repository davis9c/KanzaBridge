<?php

/**
 * Kartu metrik dashboard — satu-satunya isi halaman ini.
 *
 * Dipisah dari sys-dashboard.php supaya file itu tetap soal tata letak
 * (judul + tombol), bukan soal daftar metrik.
 *
 * Angka di sini selalu "milikmu": SuperAdmin melihat seluruh key, Admin
 * hanya key milik application yang ia buat sendiri. Sifat itu datang dari
 * query di controller, bukan dari penyaringan di view.
 */
$cards = [
    [
        'key'   => 'applications',
        'label' => 'Application',
        'value' => $summary['applications'],
        'class' => 'primary',
        'icon'  => 'fa-cube',
    ],
    [
        'key'   => 'total',
        'label' => 'API Key',
        'value' => $summary['total'],
        'class' => 'secondary',
        'icon'  => 'fa-key',
    ],
    [
        'key'   => 'active',
        'label' => 'Key aktif',
        'value' => $summary['active'],
        'class' => 'success',
        'icon'  => 'fa-circle-check',
    ],
    [
        'key'   => 'revoked',
        'label' => 'Key dicabut',
        'value' => $summary['revoked'],
        'class' => 'danger',
        'icon'  => 'fa-ban',
    ],
    [
        'key'   => 'expired',
        'label' => 'Key kedaluwarsa',
        'value' => $summary['expired'],
        'class' => 'warning',
        'icon'  => 'fa-hourglass-end',
    ],
    [
        'key'   => 'unused',
        'label' => 'Belum pernah dipakai',
        'value' => $summary['unused'],
        'class' => 'dark',
        'icon'  => 'fa-circle-question',
    ],
];
?>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow h-100">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase"><?= esc($card['label']) ?></div>
                    <div class="fs-4 fw-bold text-<?= esc($card['class']) ?>"
                        data-summary="<?= esc($card['key']) ?>">
                        <i class="fas fa-fw <?= esc($card['icon']) ?> me-1"></i><?= (int) $card['value'] ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-info small mb-4">
    <i class="fas fa-fw fa-info-circle me-1"></i>
    Angka di atas mencakup API key yang <strong>boleh Anda kelola</strong>.
    Untuk melihat dan mengubah key-nya, masuk lewat menu
    <a href="<?= base_url('application') ?>" class="alert-link">Application</a>.
    Kolom waktu "terakhir dipakai" diisi paling sering sekali per menit per key,
    jadi ini snapshot posisi key — bukan penghitung request.
</div>