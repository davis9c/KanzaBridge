<?php

/**
 * Modal konfirmasi untuk aksi yang mengubah status atau menghapus user.
 *
 * Dipakai untuk toggle-status dan delete. Sengaja `window.confirm()`
 * tidak dipakai supaya tampilannya konsisten dengan modal lain, dan supaya
 * nama user yang Affected bisa ditampilkan dengan jelas.
 */
?>
<div class="modal fade" id="modalConfirmAction" tabindex="-1" aria-labelledby="modalConfirmActionLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmActionLabel">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <p class="mb-0" id="confirmActionMessage"></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmActionSubmit">
                    <i class="fas fa-fw fa-check me-1"></i> Ya, lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>