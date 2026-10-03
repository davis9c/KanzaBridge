<?php

/**
 * Modal Ubah User.
 *
 * POST lewat AJAX supaya tabel tidak perlu reload. Isian form dikirim dari
 * baris tabel yang diklik, jadi tidak ada endpoint terpisah untuk mengambil
 * data user.
 *
 * Bentuk field-nya sengaja sama dengan sys-user/form.php supaya halaman
 * /user/edit/{id} tetap berfungsi sebagai fallback kalau JavaScript mati.
 *
 * @var list<array<string,mixed>> $roles
 * @var int|null                 $userId  User yang sedang diedit, saat reopen.
 * @var bool                     $autoOpen Buka otomatis karena validasi gagal.
 */
$roles    = $roles ?? [];
$userId   = (int) ($userId ?? 0);
$autoOpen = (bool) ($autoOpen ?? false);
?>
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-labelledby="modalEditUserLabel"
    aria-hidden="true" data-auto-open="<?= $autoOpen ? '1' : '0' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="formEditUser" method="POST" action="" class="modal-content">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h5 class="modal-title" id="modalEditUserLabel">Ubah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger d-none" id="editUserAlert" role="alert"></div>

                <div class="mb-3">
                    <label for="editUsername" class="form-label">
                        Username <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="editUsername" name="username" required
                        minlength="3" maxlength="100" pattern="[A-Za-z0-9._-]+"
                        autocomplete="off">
                    <div class="form-text">3-100 karakter, alfanumerik saja.</div>
                    <div class="invalid-feedback" data-feedback-for="username"></div>
                </div>

                <div class="mb-3">
                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="editEmail" name="email" required>
                    <div class="invalid-feedback" data-feedback-for="email"></div>
                </div>

                <div class="mb-3">
                    <label for="editFullName" class="form-label">
                        Nama Lengkap <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="editFullName" name="full_name" required
                        minlength="3" maxlength="150">
                    <div class="invalid-feedback" data-feedback-for="full_name"></div>
                </div>

                <div class="alert alert-warning small">
                    <i class="fas fa-fw fa-exclamation-triangle me-1"></i>
                    UserGate tidak menyediakan endpoint ubah password. Password hanya bisa diubah
                    dengan menghapus user lalu membuatnya kembali.
                </div>

                <hr>

                <label class="form-label fw-bold">
                    Role <span id="editUserRoleLock" class="d-none">
                        <span class="badge bg-secondary">SuperAdmin tidak dapat diubah oleh Anda</span>
                    </span>
                </label>

                <?php if ($roles === []): ?>
                    <?php /*
                        Tabel `roles` kosong. Tanpa blok ini, satu checkbox
                        "Tanpa role" yang terkunci akan tampil sendirian dan
                        terbaca seperti pilihan yang bekerja — padahal
                        justru tidak ada role sama sekali yang bisa dipilih.
                    */ ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-fw fa-exclamation-triangle me-1"></i>
                        Belum ada role yang tersedia, jadi role tidak bisa
                        diberikan. Jalankan
                        <code>php spark db:seed RoleSeeder</code>, lalu muat
                        ulang halaman ini.
                    </div>
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
                        <?php $roleId = (int) $role['id']; ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="roles[]"
                                value="<?= $roleId ?>" id="edit-role-<?= $roleId ?>"
                                data-role-id="<?= $roleId ?>">
                            <label class="form-check-label" for="edit-role-<?= $roleId ?>">
                                <strong><?= esc(role_label($role['name'])) ?></strong>
                                <?php if ((int) $role['is_super'] === 1): ?>
                                    <span class="badge bg-warning text-dark ms-1">SuperAdmin</span>
                                <?php endif; ?>
                                <span class="d-block small text-muted"><?= esc($role['description'] ?? '') ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($roles !== [] && ! can('user.promote')): ?>
                    <div class="alert alert-secondary small mb-0">
                        <i class="fas fa-fw fa-info-circle me-1"></i>
                        Penetapan role SuperAdmin hanya dapat dilakukan oleh SuperAdmin.
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="editUserSubmit">
                    <i class="fas fa-fw fa-save me-1"></i> Simpan
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>
<?php // $userId dipakai oleh view utama untuk reopen setelah validasi gagal. ?>