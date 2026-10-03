<?php

/**
 * Modal Tambah User.
 *
 * POST lewat AJAX supaya tabel tidak perlu reload, sama seperti modal ubah.
 * Field-nya sengaja dibuat sama dengan sys-user/form.php supaya aturan
 * validasi di satu tempat (validateNewUser()) tetap berlaku untuk keduanya.
 *
 * Bentuknya harus mengikuti dua aturan yang sudah jadi di halaman ini:
 *
 *   1. <form> ini adalah .modal-content itu sendiri, bukan pembungkusnya.
 *      Kalau ada <form> di antara .modal-content dan .modal-body, rantai
 *      flex-nya terputus: .modal-body tidak pernah jadi scroll container dan
 *      .modal-content yang memotong bagian bawah termasuk tombol submit —
 *      gejalanya modal terpotong tanpa scrollbar dan tanpa error JS.
 *      Lihat Tests\Support\Views\ScrollableModalTrait.
 *
 *   2. Error dari server (tanpa JavaScript) tetap ditampilkan di dalam
 *      modal, dan penanda `errorForm` dari controller yang membuat modal ini
 *      terbuka lagi supaya pesan dan isian lama tidak hilang.
 *
 * @var list<array<string,mixed>> $roles
 * @var array<string,string>       $errors       Flashdata `errors` (kunci = nama field).
 * @var string                     $serverError  Flashdata `error`, pesan dari server/UserGate.
 * @var bool                       $autoOpen     Buka otomatis karena validasi gagal.
 */

$roles       = $roles ?? [];
$errors      = $errors ?? [];
$serverError = (string) ($serverError ?? '');
$autoOpen    = (bool) ($autoOpen ?? false);

$hasServerError = $serverError !== '';
$hasFieldError  = $errors !== [];

$oldUsername = (string) old('username');
$oldEmail    = (string) old('email');
$oldFullName = (string) old('full_name');
$oldRoles    = array_map('intval', (array) old('roles', []));
?>
<div class="modal fade" id="modalCreateUser" tabindex="-1" aria-labelledby="modalCreateUserLabel"
    aria-hidden="true" data-auto-open="<?= $autoOpen ? '1' : '0' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="formCreateUser" method="POST" action="<?= base_url('user/create') ?>" class="modal-content">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateUserLabel">Tambah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <?php if (! $hasServerError && ! $hasFieldError): ?>
                    <div class="alert alert-danger d-none" id="createUserAlert" role="alert"></div>
                <?php else: ?>
                    <div class="alert alert-danger small" id="createUserAlert" role="alert">
                        <?php if ($hasServerError): ?>
                            <?= esc($serverError) ?>
                        <?php else: ?>
                            <ul class="mb-0">
                                <?php foreach ($errors as $message): ?>
                                    <li><?= esc($message) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="createUsername" class="form-label">
                        Username <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                        class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                        id="createUsername" name="username" required
                        pattern="[A-Za-z0-9]{3,100}" maxlength="100" autocomplete="off"
                        value="<?= esc($oldUsername) ?>">
                    <div class="form-text">3-100 karakter, alfanumerik saja.</div>
                    <div class="invalid-feedback" data-feedback-for="username"></div>
                </div>

                <div class="mb-3">
                    <label for="createEmail" class="form-label">
                        Email <span class="text-danger">*</span>
                    </label>
                    <input type="email"
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        id="createEmail" name="email" required
                        value="<?= esc($oldEmail) ?>">
                    <div class="invalid-feedback" data-feedback-for="email"></div>
                </div>

                <div class="mb-3">
                    <label for="createFullName" class="form-label">
                        Nama Lengkap <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                        class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                        id="createFullName" name="full_name" required minlength="3" maxlength="150"
                        value="<?= esc($oldFullName) ?>">
                    <div class="invalid-feedback" data-feedback-for="full_name"></div>
                </div>

                <div class="mb-3">
                    <label for="createPassword" class="form-label">
                        Password <span class="text-danger">*</span>
                    </label>
                    <input type="password"
                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                        id="createPassword" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">
                        Minimal 8 karakter. Disimpan sebagai hash di UserGate dan tidak pernah
                        ditampilkan lagi.
                    </div>
                    <div class="invalid-feedback" data-feedback-for="password"></div>
                </div>

                <hr>

                <label class="form-label fw-bold">Role</label>

                <?php if ($roles === []): ?>
                    <?php /*
                        Sama seperti modal ubah: tabel `roles` kosong harus
                        dijelaskan. Tanpa blok ini, satu checkbox "Tanpa role"
                        yang terkunci akan tampil sendirian dan terbaca seperti
                        pilihan yang bekerja.
                    */ ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-fw fa-exclamation-triangle me-1"></i>
                        Belum ada role yang tersedia, jadi role tidak bisa diberikan. Jalankan
                        <code>php spark db:seed RoleSeeder</code>, lalu muat ulang halaman ini.
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
                                value="<?= $roleId ?>" id="create-role-<?= $roleId ?>"
                                data-role-id="<?= $roleId ?>"
                                <?= in_array($roleId, $oldRoles, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="create-role-<?= $roleId ?>">
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
                <button type="submit" class="btn btn-primary" id="createUserSubmit">
                    <i class="fas fa-fw fa-save me-1"></i> Simpan
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>