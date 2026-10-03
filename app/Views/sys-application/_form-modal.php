<?php

/**
 * Modal tambah / ubah application.
 *
 * Satu partial untuk dua keperluan: yang membedakan cuma `data-action`
 * dan isi field-nya, yang diisi JavaScript dari baris tabel saat tombol
 * Ubah diklik.
 *
 * Sengaja TIDAK memakai $this->extend(): modal ini menyisipkan diri ke
 * halaman daftar yang sudah punya layout, bukan halaman tersendiri.
 *
 * Kolom "Catatan" yang dulu ada di halaman form dipindahkan ke sini
 * sebagai teks pendek di bawah field deskripsi supaya tidak melebar.
 */

/** Nama field CSRF, dipakai juga oleh JavaScript di halaman utama. */
$csrfName = service('security')->getTokenName();
?>
<div class="modal fade" id="modalApplication" tabindex="-1" aria-labelledby="modalApplicationLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formApplication" method="POST" action="">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title" id="modalApplicationLabel">Tambah Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="modalApplicationAlert" role="alert"></div>

                    <div class="mb-3">
                        <label for="modalName" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalName" name="name" required minlength="3"
                            maxlength="100">
                        <div class="form-text">3-100 karakter, mis. "Modul Registrasi BPM"</div>
                        <div class="invalid-feedback" data-feedback-for="name"></div>
                    </div>

                    <div class="mb-3">
                        <label for="modalCode" class="form-label">Kode</label>
                        <input type="text" class="form-control" id="modalCode" name="code"
                            pattern="[a-z0-9][a-z0-9-]{2,49}" maxlength="50"
                            placeholder="otomatis dari nama">
                        <div class="form-text">
                            Huruf kecil, angka, dan tanda hubung. Kalau dikosongkan, dibuat dari nama.
                            Dipakai untuk dokumentasi dan mengenali key di log.
                        </div>
                        <div class="invalid-feedback" data-feedback-for="code"></div>
                    </div>

                    <div class="mb-3">
                        <label for="modalDescription" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="modalDescription" name="description" rows="3"
                            maxlength="191"></textarea>
                        <div class="invalid-feedback" data-feedback-for="description"></div>
                    </div>

                    <p class="small text-muted mb-0">
                        <i class="fas fa-fw fa-circle-info me-1"></i>
                        Application adalah identitas pemanggil API. Setelah dibuat, buat API key di
                        dalamnya, lalu centang endpoint yang boleh diakses. Seluruh endpoint
                        <strong>hanya membaca</strong> data dari <code>sik_beta</code>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="modalApplicationSubmit">
                        <i class="fas fa-fw fa-save me-1"></i> Simpan
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php // Token CSRF dib-track juga di sini supaya JavaScript selalu punya
      // nilai terbaru. Config\Security::$regenerate = true membuat token
      // berubah setiap POST sukses, dan cookie-nya httpOnly sehingga tidak
      // bisa dibaca JavaScript. ?>
<script>
    window.__csrfName = <?= json_encode($csrfName) ?>;
    window.__csrfValue = <?= json_encode((string) service('security')->getHash()) ?>;
</script>