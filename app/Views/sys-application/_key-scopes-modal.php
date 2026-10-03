<?php

/**
 * Modal Hak Akses — dipakai untuk mengubah endpoint yang boleh diakses
 * sebuah key.
 *
 * Satu modal untuk seluruh baris tabel, bukan satu per key. Dulu tiap baris
 * merender modalnya sendiri; kalau sebuah application punya banyak key,
 * halaman itu meledak ukurannya karena setiap modal memuat seluruh checklist.
 *
 * Tombol "Hak Akses" di tabel mengisi modal ini lewat data-scope-* dan
 * data-action, jadi tidak ada endpoint untuk mengambil isi key.
 *
 * @var int  $appId
 * @var bool $autoOpen       Buka otomatis karena validasi gagal.
 * @var int  $autoOpenKeyId  Key yang sedang diedit saat reopen.
 */

// Partial include() tidak menjamin semua variabel terdefinisi, jadi
// setiap data dari luar diambil dengan operator ??.
$appId         = (int) ($appId ?? 0);
$catalog       = $catalog ?? [];
$autoOpenKeyId = (int) ($autoOpenKeyId ?? 0);
$autoOpen      = (bool) ($autoOpen ?? false);
$csrfField     = csrf_field();
?>
<div class="modal fade" id="modalKeyScopes" tabindex="-1" aria-labelledby="modalKeyScopesLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="formKeyScopes" method="POST" action="" data-scope-form class="modal-content">
            <?= $csrfField ?>

            <div class="modal-header">
                <h6 class="modal-title" id="modalKeyScopesLabel">
                    Hak akses endpoint
                    <span id="scopeModalKeyLabel"></span>
                    <code id="scopeModalKeyMask" class="ms-1"></code>
                    <span class="badge bg-danger d-none ms-1" id="scopeModalRevoked">Dicabut</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger py-2 small d-none" id="scopeModalError" role="alert"></div>

                <?= view('sys-application/_scope-checklist', [
                    'catalog'   => $catalog,
                    'selected'  => [],
                    'idPrefix'  => 'scope-key',
                    'showTools' => true,
                ]) ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-fw fa-save me-1"></i> Simpan Hak Akses
                </button>
            </div>
        </form>
    </div>
</div>