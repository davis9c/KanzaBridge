<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<?php
$appId   = (int) $application['id'];
$newKey  = session()->getFlashdata('new_api_key');
$newKeyLabel = session()->getFlashdata('new_api_key_label');

// Penanda dari controller: modal mana yang harus dibuka ulang setelah
// validasi gagal. Tanpa ini user akan melihat halaman yang terlihat utuh
// tapi form-nya tidak pernah muncul.
$errorForm   = session()->getFlashdata('errorForm');
$errorKeyId  = (int) (session()->getFlashdata('errorKeyId') ?: 0);
$formErrors  = session()->getFlashdata('errors') ?: [];
$baseUrl     = rtrim(base_url(), '/');

$autoOpenCreate = $errorForm === 'create';
$autoOpenScopes = $errorForm === 'scopes' && $errorKeyId > 0;

// Key yang modal hak aksesnya harus dibuka ulang, together dengan scope
// yang tadi dicoba disimpan (bisa saja kosong).
$reopenKey = null;
$reopenScopes = (array) old('scopes', []);

if ($autoOpenScopes) {
    foreach ($keys as $candidate) {
        if ((int) $candidate['id'] === $errorKeyId) {
            $reopenKey = $candidate;
            break;
        }
    }
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">
            <a href="<?= base_url('application') ?>">Manajemen Application</a> /
            <?= esc($application['name']) ?> / API Key
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="btnCreateKey">
                <i class="fas fa-fw fa-plus me-1"></i> Buat Key
            </button>
            <a href="<?= base_url('application/edit/' . $appId) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-fw fa-pen me-1"></i> Ubah Application
            </a>
            <a href="<?= base_url('application') ?>" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?php if ($newKey): ?>
        <div class="alert alert-success" id="new-key-alert">
            <h6 class="alert-heading">
                <i class="fas fa-fw fa-key me-1"></i>
                API key untuk "<?= esc($newKeyLabel) ?>" sudah dibuat
            </h6>
            <p class="mb-2">
                <strong>Simpan sekarang.</strong> Key ini ditampilkan sekali saja — yang tersimpan di
                server hanya hash-nya, jadi halaman ini tidak akan pernah menampilkannya lagi.
            </p>
            <div class="input-group mb-2">
                <input type="text" id="new-key-value" class="form-control font-monospace" readonly
                    value="<?= esc($newKey) ?>">
                <button class="btn btn-outline-success" type="button" id="copy-new-key">
                    <i class="fas fa-fw fa-copy me-1"></i> Salin
                </button>
            </div>
            <pre class="bg-body-tertiary border rounded p-2 mb-0 small">curl -X GET "<?= esc($baseUrl) ?>/api/v2/me" \
  -H "X-API-Key: <?= esc($newKey) ?>"</pre>
        </div>
    <?php endif; ?>

    <!-- ============================ DAFTAR KEY ============================ -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">API Key milik Application ini</h6>
        </div>
        <div class="table-responsive">
            <table id="tbl-api-key" class="table table-striped table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Nama Key</th>
                        <th>Key</th>
                        <th>Endpoint Diizinkan</th>
                        <th>Status</th>
                        <th>Masa Berlaku</th>
                        <th>Terakhir Dipakai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $key): ?>
                        <?php
                        $keyId   = (int) $key['id'];
                        $active  = (string) $key['status'] === 'ACTIVE';
                        $expired = ! empty($key['expires_at']) && strtotime((string) $key['expires_at']) < time();
                        ?>
                        <tr>
                            <td>
                                <i class="fas fa-key me-1 text-muted"></i><?= esc($key['label']) ?>
                            </td>
                            <td>
                                <code><?= esc(api_key_mask((string) $key['key_prefix'])) ?></code>
                            </td>
                            <td class="small">
                                <?php if ($key['scopes'] === []): ?>
                                    <span class="badge bg-warning text-dark">Tidak ada</span>
                                <?php else: ?>
                                    <?php foreach ($key['scopes'] as $scope): ?>
                                        <span class="badge bg-primary me-1" title="<?= esc($scope) ?>">
                                            <?= esc(api_scope_label((string) $scope)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="badge bg-success">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">REVOKED</span>
                                <?php endif; ?>
                                <?php if ($expired): ?>
                                    <span class="badge bg-warning text-dark">Kedaluwarsa</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= esc($key['expires_at'] ?: 'Selamanya') ?>
                            </td>
                            <td class="small text-muted"><?= esc($key['last_used_at'] ?? '-') ?></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary js-scopes"
                                    data-action="<?= base_url('application/' . $appId . '/keys/' . $keyId . '/scopes') ?>"
                                    data-key-id="<?= $keyId ?>"
                                    data-key-label="<?= esc($key['label']) ?>"
                                    data-key-mask="<?= esc(api_key_mask((string) $key['key_prefix'])) ?>"
                                    data-key-active="<?= $active ? '1' : '0' ?>"
                                    data-scopes="<?= esc(json_encode(array_values($key['scopes']))) ?>"
                                    title="Atur endpoint yang boleh diakses">
                                    <i class="fas fa-fw fa-list-check me-1"></i> Hak Akses
                                </button>

                                <form action="<?= base_url('application/' . $appId . '/keys/' . $keyId . '/toggle-status') ?>"
                                    method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        onclick="return confirm('<?= $active ? 'Cabut API key ini?' : 'Aktifkan kembali API key ini?' ?>')"
                                        title="<?= $active ? 'Cabut' : 'Aktifkan' ?>">
                                        <i class="fas fa-fw <?= $active ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                    </button>
                                </form>

                                <form action="<?= base_url('application/' . $appId . '/keys/' . $keyId . '/rotate') ?>"
                                    method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                        onclick="return confirm('Ganti API key ini? Key lama langsung tidak berlaku dan key baru hanya ditampilkan sekali.')"
                                        title="Ganti key (rotasi)">
                                        <i class="fas fa-fw fa-rotate"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- PENTING: partial di bawah memakai helper view(), BUKAN $this->include().

     View::include($view, $options) tidak meneruskan $options ke dalam partial
     pada jalur ini. Partial dipanggil dari dalam view yang memakai
     extend() + section(), dan yang terbaca di partial hanyalah data view
     induk (title, keys, catalog, ...) — argumen kedua diabaikan. Gejalanya
     "Undefined variable" padahal variabelnya dikirim.

     Helper view() membuat renderer terpisah, jadi datanya benar-benar
     sampai. Karena itu partial partial diambil lewat view(), bukan include.
-->
<?= view('sys-application/_key-create-modal', [
    'appId'    => $appId,
    'catalog'  => $catalog,
    'errors'   => $formErrors,
    'baseUrl'  => $baseUrl,
    'autoOpen' => $autoOpenCreate,
]) ?>

<?= view('sys-application/_key-scopes-modal', [
    'appId'         => $appId,
    'catalog'       => $catalog,
    'autoOpen'      => $autoOpenScopes,
    'autoOpenKeyId' => $reopenKey === null ? 0 : (int) $reopenKey['id'],
]) ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('partial/datatables') ?>
<script>
    // Halaman ini tidak punya filter server, jadi search bawaan DataTables
    // tetap dipakai.
    kanzaTable('#tbl-api-key', {
        order: [[5, 'desc']]
    });
</script>

<!--
     Script modal WAJIB ada di section 'scripts', bukan di content section.
     Layout memuat bootstrap.bundle.min.js di dekat akhir <body>, yaitu
     SESUDAH renderSection('content'). Inline <script> dieksekusi sinkron
     saat parsing, jadi kalau script ini ditaruh di content section, global
     `bootstrap` belum ada dan seluruh IIFE ini berhenti dengan
     ReferenceError — tombol "Buat Key" dan "Hak Akses" diam saja tanpa error
     yang terlihat.
-->
<script>
    (() => {
        'use strict';

        const scopesModalEl = document.getElementById('modalKeyScopes');
        const scopesForm = document.getElementById('formKeyScopes');
        const scopesBoxes = scopesForm.querySelectorAll('[data-scope-checkbox]');
        const scopesCount = scopesForm.querySelector('[data-scope-count]');
        const scopesError = document.getElementById('scopeModalError');
        const scopesLabel = document.getElementById('scopeModalKeyLabel');
        const scopesMask = document.getElementById('scopeModalKeyMask');
        const scopesRevoked = document.getElementById('scopeModalRevoked');

        // Satu modal untuk semua baris. Instansinya dibuat sekali, bukan
        // per tombol, karena tabelnya bisa diganti paging/filter.
        const scopesModal = new bootstrap.Modal(scopesModalEl);

        const refreshCount = () => {
            scopesCount.textContent = String(
                scopesForm.querySelectorAll('[data-scope-checkbox]:checked').length
            );
        };

        /**
         * Isi modal hak akses dari tombol "Hak Akses" yang diklik.
         *
         * Scope dibaca dari data-scopes, jadi tidak perlu request ke server
         * hanya untuk mengisi checkbox.
         */
        const openScopes = (button) => {
            const scopes = JSON.parse(button.dataset.scopes || '[]');

            scopesForm.action = button.dataset.action;
            scopesLabel.textContent = button.dataset.keyLabel || '';
            scopesMask.textContent = button.dataset.keyMask || '';
            scopesRevoked.classList.toggle('d-none', button.dataset.keyActive === '1');

            scopesBoxes.forEach((box) => {
                box.checked = scopes.includes(box.value);
            });

            scopesError.classList.add('d-none');
            scopesError.textContent = '';

            refreshCount();
            scopesModal.show();
        };

        document.getElementById('tbl-api-key').addEventListener('click', (event) => {
            const button = event.target.closest('.js-scopes');

            if (button) {
                openScopes(button);
            }
        });

        scopesForm.querySelectorAll('[data-scope-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const checked = button.dataset.scopeToggle === 'all';

                scopesBoxes.forEach((box) => {
                    box.checked = checked;
                });

                refreshCount();
            });
        });

        scopesBoxes.forEach((box) => {
            box.addEventListener('change', refreshCount);
        });

        const createModalEl = document.getElementById('modalCreateKey');
        const createModal = new bootstrap.Modal(createModalEl);

        document.getElementById('btnCreateKey').addEventListener('click', () => {
            createModal.show();
        });

        document.getElementById('copy-new-key')?.addEventListener('click', function () {
            const field = document.getElementById('new-key-value');

            navigator.clipboard.writeText(field.value).then(() => {
                this.innerHTML = '<i class="fas fa-fw fa-check me-1"></i> Tersalin';
            });
        });

        // Reopen otomatis setelah validasi gagal. Penandanya datang dari
        // controller sebagai flashdata, bukan dari URL, supaya tidak bisa
        // dipicu dengan crafted link.
        <?php if ($autoOpenCreate): ?>
        createModal.show();
        <?php endif; ?>

        <?php if ($autoOpenScopes && $reopenKey !== null): ?>
        (() => {
            const button = document.querySelector(
                '.js-scopes[data-key-id="<?= (int) $reopenKey['id'] ?>"]'
            );

            if (button) {
                openScopes(button);
            }
            <?php if (! empty(session()->getFlashdata('error'))): ?>
            scopesError.textContent = <?= json_encode((string) session()->getFlashdata('error')) ?>;
            scopesError.classList.remove('d-none');
            <?php endif; ?>
        })();
        <?php endif; ?>
    })();
</script>
<?= $this->endSection() ?>