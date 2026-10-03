<?php

/**
 * Modal Buat API Key.
 *
 * POST biasa (bukan Ajax) supaya key plaintext tetap keluar lewat flashdata
 * seperti sebelumnya: halaman reload, lalu panel "Simpan sekarang" tampil
 * sekali. Secret tidak pernah masuk ke respons JSON.
 *
 * Buka ulang otomatis kalau controller gagal memvalidasi form ini —
 * lihat penanda `errorForm` di view utama.
 *
 * @var int                                    $appId
 * @var list<string>                           $errors
 * @var string                                 $baseUrl
 * @var bool                                   $autoOpen
 */

// Partial include() tidak menjamin semua variabel terdefinisi, jadi
// setiap data dari luar diambil dengan operator ??.
$errors     = $errors ?? [];
$appId      = (int) ($appId ?? 0);
$catalog    = $catalog ?? [];
$autoOpen   = (bool) ($autoOpen ?? false);
$oldLabel   = (string) old('label');
$oldExpires = (string) old('expires_at');
$oldLimit   = (string) old('rate_limit_per_minute', '0');
$oldScopes  = (array) old('scopes', []);
?>
<div class="modal fade" id="modalCreateKey" tabindex="-1" aria-labelledby="modalCreateKeyLabel"
    aria-hidden="true" data-auto-open="<?= $autoOpen ? '1' : '0' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="<?= base_url('application/' . $appId . '/keys') ?>" method="POST" class="modal-content">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateKeyLabel">Buat API Key Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <?php if (isset($errors['label'])): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <?= esc($errors['label']) ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="newKeyLabel" class="form-label">Nama Key <span class="text-danger">*</span></label>
                        <input type="text"
                            class="form-control <?= isset($errors['label']) ? 'is-invalid' : '' ?>"
                            id="newKeyLabel" name="label" required maxlength="100"
                            value="<?= esc($oldLabel) ?>"
                            placeholder="Produksi / Staging / Uji Integrasi">
                        <?php if (isset($errors['label'])): ?>
                            <div class="invalid-feedback"><?= esc($errors['label']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="newKeyExpires" class="form-label">Masa Berlaku</label>
                        <input type="datetime-local" class="form-control" id="newKeyExpires"
                            name="expires_at" value="<?= esc($oldExpires) ?>">
                        <div class="form-text">Kosong = tidak pernah kedaluwarsa.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="newKeyLimit" class="form-label">Batas Request / Menit</label>
                        <input type="number" class="form-control" id="newKeyLimit"
                            name="rate_limit_per_minute" min="0" max="1000000" step="1"
                            value="<?= esc($oldLimit) ?>">
                        <div class="form-text">0 = tanpa batas. Melebihi batas dibalas 429.</div>
                    </div>
                </div>

                <hr>

                <label class="form-label fw-bold">
                    Endpoint yang Boleh Diakses <span class="text-danger">*</span>
                </label>
                <p class="small text-muted">
                    Semua endpoint hanya membaca data dari sik_beta. Berikan hanya yang benar-benar
                    dipakai integrasi ini.
                </p>

                <?php if (isset($errors['scopes'])): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <?= esc($errors['scopes']) ?>
                    </div>
                <?php endif; ?>

                <?= view('sys-application/_scope-checklist', [
                    'catalog'   => $catalog,
                    'selected'  => $oldScopes,
                    'idPrefix'  => 'new-key',
                    'showTools' => false,
                ]) ?>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-fw fa-plus me-1"></i> Buat Key
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>