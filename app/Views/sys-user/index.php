<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<?php
$title = $title;

/*
 * Flashdata hanya bisa dibaca SEKALI, jadi semuanya diambil di awal.
 *
 * `errorForm` berasal dari SysUser::store() sebagai penanda modal tambah
 * yang harus dibuka lagi. Kalau penandanya ada, pesan dan error field-nya
 * ditampilkan DI DALAM modal itu — bukan sebagai alert halaman — supaya
 * user tidak kehilangan isian form yang tadi sudah diisi panjang. Tanpa
 * penanda ini, form yang gagal divalidasi akan hilang begitu halaman
 * dimuat ulang.
 *
 * Tanpa penanda, `errors` justru berasal dari halaman ubah (fallback tanpa
 * JavaScript), jadi error-nya ditampilkan sebagai alert halaman — bukan
 * ikut disuapkan ke modal tambah, karena itu akan mengarah ke form yang salah.
 */
$flashSuccess   = (string) session()->getFlashdata('success');
$flashError     = (string) session()->getFlashdata('error');
$formErrors     = (array) (session()->getFlashdata('errors') ?: []);
$autoOpenCreate = session()->getFlashdata('errorForm') === 'create';
?>
<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0"><?= esc($title) ?></h1>
        <button type="button" class="btn btn-primary btn-sm" id="btnCreateUser">
            <i class="fas fa-fw fa-plus me-1"></i> Tambah User
        </button>
    </div>

    <?php if (! $autoOpenCreate && $flashSuccess !== ''): ?>
        <div class="alert alert-success" role="alert"><?= esc($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (! $autoOpenCreate && $flashError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= esc($flashError) ?></div>
    <?php endif; ?>

    <?php if (! $autoOpenCreate && $formErrors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($formErrors as $message): ?>
                    <li><?= esc($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php /*
        Peringatan tabel `roles` kosong diletakkan di luar modal, bukan hanya
        di dalam modal ubah. Admin harus melihatnya SEBELUM menekan tombol
        Ubah — kalau hanya di dalam modal, gejalanya baru terasa setelah
        modal dibuka.
    */ ?>
    <?php if ($roles === []): ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-fw fa-exclamation-triangle me-1"></i>
            Belum ada role yang tersedia. Role tidak bisa diberikan ke user
            sampai tabel <code>roles</code> diisi — jalankan
            <code>php spark db:seed RoleSeeder</code>, lalu muat ulang halaman ini.
        </div>
    <?php endif; ?>

    <div id="pageNotice" class="d-none" role="alert"></div>

    <?php // Token awal untuk POST Ajax pertama. Setelah itu selalu diambil
          // dari setiap respons JSON (Config\Security::$regenerate = true). ?>
    <script>
        window.ThemeCsrf = {
            name: <?= json_encode(service('security')->getTokenName()) ?>,
            value: <?= json_encode((string) service('security')->getHash()) ?>
        };

        // Label role dikirim dari server supaya kolom Role tidak perlu
        // menebak-nebak nama role di JavaScript. Kalau tidak, setiap role
        // baru akan tertampil memakai label role yang salah.
        window.roleLabels = <?= json_encode(access_service()->roleLabels) ?>;
    </script>

    <!--
        Tabel disuapkan server-side. Barisnya dibuat JavaScript dari endpoint
        user/data, jadi <tbody> sengaja dibiarkan kosong: baris kosong PHP
        dengan colspan akan memicu peringatan DataTables
        ("Requested unknown parameter").
    -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar User</h6>
        </div>
        <div class="table-responsive">
            <table id="tbl-user" class="table table-striped table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle me-1"></i>
            Akun dibuat di UserGate. Role di bawah disimpan di database lokal
            KanzaBridge dan tidak berasal dari UserGate.
            <?php if (! can('user.delete')): ?>
                <br>Penghapusan user dan penetapan role SuperAdmin hanya dapat dilakukan oleh SuperAdmin.
            <?php endif; ?>
        </div>
    </div>
</div>

<?php /*
    Partial di bawah diambil dengan helper view(), BUKAN $this->include().

    View::include($view, $options) tidak meneruskan $options ke dalam partial
    pada jalur ini, jadi partial hanya melihat data view induk — gejalanya
    "Undefined variable" padahal variabelnya sudah dikirim. Helper view()
    membuat renderer terpisah, jadi datanya benar-benar sampai.
*/ ?>
<?= view('sys-user/_create-modal', [
    'roles'       => $roles,
    'errors'      => $autoOpenCreate ? $formErrors : [],
    'serverError' => $autoOpenCreate ? $flashError : '',
    'autoOpen'    => $autoOpenCreate,
]) ?>

<?= view('sys-user/_edit-modal', ['roles' => $roles]) ?>

<?= view('sys-user/_confirm-modal') ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('partial/datatables') ?>
<script>
    (() => {
        'use strict';

        const notice = document.getElementById('pageNotice');

        const editModalEl = document.getElementById('modalEditUser');
        const editModal = new bootstrap.Modal(editModalEl);
        const editForm = document.getElementById('formEditUser');
        const editAlert = document.getElementById('editUserAlert');
        const editSubmit = document.getElementById('editUserSubmit');
        const editRoleLock = document.getElementById('editUserRoleLock');
        const roleBoxes = editForm.querySelectorAll('[data-role-id]');

        const confirmModalEl = document.getElementById('modalConfirmAction');
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const confirmMessage = document.getElementById('confirmActionMessage');
        const confirmSubmit = document.getElementById('confirmActionSubmit');

        const createModalEl = document.getElementById('modalCreateUser');
        const createModal = new bootstrap.Modal(createModalEl);
        const createForm = document.getElementById('formCreateUser');
        const createAlert = document.getElementById('createUserAlert');
        const createSubmit = document.getElementById('createUserSubmit');
        const createRoleBoxes = createForm.querySelectorAll('[data-role-id]');

        // Field yang diperiksa untuk error, per modal. Password hanya ada di
        // modal tambah — user tidak bisa mengganti password lewat form ubah.
        const EDIT_FIELDS = ['username', 'email', 'full_name'];
        const CREATE_FIELDS = ['username', 'email', 'full_name', 'password'];

        /*
         * Token CSRF harus selalu segar. Config\Security::$regenerate = true
         * membuat token berubah setiap POST sukses, sedangkan cookie-nya
         * httpOnly (Config\Cookie::$httponly) sehingga tidak bisa dibaca dari
         * sini. Jadi setiap respons JSON membawa token baru, dan nilai itu
         * yang dipakai untuk request berikutnya — termasuk request kedua
         * dari modal yang sama.
         */
        let csrf = window.ThemeCsrf || { name: '', value: '' };

        const esc = (value) => {
            const holder = document.createElement('div');
            holder.textContent = (value === null || value === undefined) ? '' : String(value);
            return holder.innerHTML;
        };

        const applyToken = (payload) => {
            if (payload && payload.csrf) {
                csrf = { name: payload.csrf.name, value: payload.csrf.value };
            }
        };

        const flash = (message, variant) => {
            notice.className = `alert alert-${variant} mb-4`;
            notice.innerHTML = `<i class="fas fa-fw fa-circle-info me-1"></i>${esc(message)}`;
            notice.classList.remove('d-none');
        };

        /*
         * Dua modal ini punya bentuk field yang sama, jadi pembersihan dan
         * tampilnya error cukup satu pasang fungsi. Parameter form-nya
         * karena nama field di keduanya tidak boleh sama — menanyakan
         * `[name="username"]` tanpa tahu form mana akan selalu menemukan
         * field modal ubah lebih dulu di DOM.
         */
        const clearErrors = (form, alert, fields) => {
            alert.classList.add('d-none');
            alert.textContent = '';
            fields.forEach((field) => {
                form.querySelector(`[name="${field}"]`)?.classList.remove('is-invalid');
                const hint = form.querySelector(`[data-feedback-for="${field}"]`);
                if (hint) {
                    hint.textContent = '';
                }
            });
        };

        const showErrors = (form, alert, fields, message, errors) => {
            const entries = Object.entries(errors || {});

            if (entries.length === 0) {
                alert.textContent = message || 'Gagal menyimpan.';
                alert.classList.remove('d-none');
                return;
            }

            alert.innerHTML = '<ul class="mb-0">'
                + entries.map(([, text]) => `<li>${esc(text)}</li>`).join('')
                + '</ul>';
            alert.classList.remove('d-none');

            entries.forEach(([field, text]) => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                }
                const hint = form.querySelector(`[data-feedback-for="${field}"]`);
                if (hint) {
                    hint.textContent = text;
                }
            });
        };

        /**
         * Kirim POST Ajax dan kembalikan payload-nya.
         *
         * Sengaja tidak melempar error supaya satu tempat yang memutuskan
         * cara menampilkan gagalnya.
         */
        const post = async (url, body) => {
            // Token di body ditulis ulang dari `csrf`, bukan mempercayai
            // hidden field milik form. Field itu rendered saat halaman
            // dimuat dan sudah basi begitu satu POST sukses, karena token
            // di-regenerate setiap submission (Config\Security::$regenerate).
            // CI4 membaca token dari POST DULUAN, sebelum header
            // X-CSRF-TOKEN — jadi tanpa baris ini, POST kedua dari modal
            // yang sama (misal ubah user A lalu user B tanpa reload) selalu
            // ditolak dengan 403.
            body.set(csrf.name, csrf.value);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf.value
                },
                body
            });

            const payload = await response.json();
            applyToken(payload);

            return payload;
        };

        /* ---------------------------------------------------------------- *
         *  Modal Ubah
         * ---------------------------------------------------------------- */

        /**
         * Buka modal ubah untuk satu baris.
         *
         * Role dicocokkan lewat `roleIds` dari server, bukan lewat teks label.
         * Nama role di tabel (`SUPER_ADMIN`) berbeda dari label tampilan
         * (`SuperAdmin`) yang dikeluarkan Config\Access::$roleLabels, jadi
         * mencocokkan label selalu gagal — dan akibatnya tidak ada satu pun
         * checkbox yang pernah tercentang.
         */
        const openEdit = (row) => {
            clearErrors(editForm, editAlert, EDIT_FIELDS);

            editForm.action = row.editUrl;
            editForm.querySelector('[name="username"]').value = row.username;
            editForm.querySelector('[name="email"]').value = row.email;
            editForm.querySelector('[name="full_name"]').value = row.fullName;

            const wanted = new Set((row.roleIds || []).map(String));

            roleBoxes.forEach((box) => {
                box.checked = wanted.has(String(box.dataset.roleId));
            });

            // Role milik SuperAdmin tidak boleh disentuh oleh Actor biasa.
            // Server tetap memeriksanya; di sini supaya checkboxnya terlihat
            // terkunci, bukan sekadar ditolak diam-diam.
            editRoleLock.classList.toggle('d-none', row.canEditRoles);

            editModal.show();
        };

        const table = new DataTable('#tbl-user', {
            serverSide: true,
            processing: true,
            ajax: '<?= base_url('user/data') ?>',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
            order: [[1, 'asc']],
            language: Object.assign({}, window.kanzaTableDefaults.language, {
                emptyTable: 'Belum ada user yang cocok.'
            }),
            columns: [
                {
                    data: 'username',
                    render: (value, type, row) => {
                        if (type !== 'display') {
                            return value;
                        }
                        const self = row.isSelf
                            ? '<span class="badge bg-info ms-1">Anda</span>'
                            : '';
                        return `<i class="fas fa-user me-1 text-muted"></i>${esc(value)}${self}`;
                    }
                },
                { data: 'fullName' },
                { data: 'email' },
                {
                    // Kolom Role diambil dari tabel terpisah, jadi tidak
                    // bisa diurutkan di SQL.
                    data: 'roleNames',
                    orderable: false,
                    render: (roles, type) => {
                        if (type !== 'display') {
                            return Array.isArray(roles) ? roles.join(',') : '';
                        }
                        if (!roles || roles.length === 0) {
                            return '<span class="badge bg-secondary">Tanpa role</span>';
                        }
                        return roles.map((role) => {
                            // Label diambil dari Config\Access::$roleLabels via
                            // window.roleLabels. Versi lama menebak
                            // `role === 'SUPER_ADMIN' ? ... : 'Admin'`, yang
                            // membuat semua role baru tertampil sebagai "Admin".
                            const labels = window.roleLabels || {};
                            const label = labels[role] ?? role;
                            const variant = role === 'SUPER_ADMIN' ? 'warning text-dark' : 'primary';
                            return `<span class="badge bg-${variant} me-1">${esc(label)}</span>`;
                        }).join('');
                    }
                },
                {
                    data: 'status',
                    render: (value, type) => (type === 'display'
                        ? `<span class="badge bg-${value === 'ACTIVE' ? 'success' : 'danger'}">${esc(value)}</span>`
                        : value)
                },
                {
                    data: 'lastLoginAt',
                    render: (value, type) => (type === 'display'
                        ? `<span class="small text-muted">${esc(value || '-')}</span>`
                        : value)
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end text-nowrap',
                    render: (id, type, row) => {
                        if (type !== 'display') {
                            return id;
                        }

                        const edit = '<button type="button" class="btn btn-sm btn-outline-primary js-edit"'
                            + ` data-id="${id}"><i class="fas fa-fw fa-pen"></i></button> `;

                        // Flag hak akses berasal dari server, jadi tombol yang
                        // tampil sama persis dengan yang diizinkan.
                        const toggleDisabled = row.canToggle ? '' : ' disabled';
                        const toggle = `<button type="button" class="btn btn-sm btn-outline-secondary js-toggle"`
                            + ` data-id="${id}"${toggleDisabled}`
                            + ` title="${row.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan'}">`
                            + `<i class="fas fa-fw ${row.status === 'ACTIVE' ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>`
                            + '</button> ';

                        const deleteBtn = row.canDelete
                            ? '<button type="button" class="btn btn-sm btn-outline-danger js-delete"'
                              + ` data-id="${id}"><i class="fas fa-fw fa-trash"></i></button>`
                            : '';

                        return edit + toggle + deleteBtn;
                    }
                }
            ]
        });

        // Event delegation: baris bisa berganti karena paging/sorting/reload,
        // jadi listener tidak boleh dipasang per elemen.
        document.getElementById('tbl-user').addEventListener('click', (event) => {
            const id = event.target.closest('button[data-id]')?.dataset.id;

            if (!id) {
                return;
            }

            const row = table.rows({ search: 'applied' }).data().toArray()
                .find((item) => String(item.id) === String(id));

            if (!row) {
                return;
            }

            if (event.target.closest('.js-edit')) {
                openEdit(row);
                return;
            }

            if (event.target.closest('.js-toggle')) {
                askConfirm(row, 'toggle');
                return;
            }

            if (event.target.closest('.js-delete')) {
                askConfirm(row, 'delete');
            }
        });

        /* ---------------------------------------------------------------- *
         *  Modal konfirmasi
         * ---------------------------------------------------------------- */

        let pending = null;

        const askConfirm = (row, action) => {
            const isDelete = action === 'delete';
            const label = row.username;

            confirmMessage.textContent = isDelete
                ? `Hapus user "${label}" dari UserGate? Tindakan ini tidak dapat dibatalkan.`
                : (row.status === 'ACTIVE'
                    ? `Nonaktifkan user "${label}"? User tidak akan bisa masuk lagi.`
                    : `Aktifkan kembali user "${label}"?`);

            confirmSubmit.classList.toggle('btn-danger', isDelete);
            confirmSubmit.classList.toggle('btn-warning', !isDelete);

            pending = {
                action,
                url: isDelete ? row.deleteUrl : row.toggleUrl,
                label
            };

            confirmModal.show();
        };

        confirmSubmit.addEventListener('click', async () => {
            if (!pending) {
                return;
            }

            confirmSubmit.disabled = true;

            try {
                const body = new FormData();
                body.append(csrf.name, csrf.value);

                const payload = await post(pending.url, body);

                if (!payload.ok) {
                    flash(payload.message || 'Aksi gagal.', 'danger');
                    return;
                }

                confirmModal.hide();
                flash(payload.message, 'success');
                table.ajax.reload(null, false);
            } catch (error) {
                flash('Tidak bisa menghubungi server. Coba lagi.', 'danger');
            } finally {
                confirmSubmit.disabled = false;
                pending = null;
            }
        });

        /* ---------------------------------------------------------------- *
         *  Submit modal ubah
         * ---------------------------------------------------------------- */

        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(editForm, editAlert, EDIT_FIELDS);
            editSubmit.disabled = true;

            try {
                const payload = await post(editForm.action, new FormData(editForm));

                if (!payload.ok) {
                    showErrors(editForm, editAlert, EDIT_FIELDS, payload.message, payload.errors);
                    return;
                }

                editModal.hide();
                flash(payload.message, 'success');
                table.ajax.reload(null, false);
            } catch (error) {
                editAlert.textContent = 'Tidak bisa menghubungi server. Coba lagi.';
                editAlert.classList.remove('d-none');
            } finally {
                editSubmit.disabled = false;
            }
        });

        /* ---------------------------------------------------------------- *
         *  Modal tambah
         * ---------------------------------------------------------------- */

        /**
         * Kosongkan modal tambah.
         *
         * Dipanggil setelah simpan berhasil dan setiap kali modal ditutup,
         * sehingga membuka ulang tidak pernah menampilkan akun yang sudah
         * tersimpan. Checkbox role harus di-uncheck manual — `reset()` tidak
         * mengembalikan state kotak centang ke keadaan awal.
         */
        const resetCreate = () => {
            createForm.reset();
            createRoleBoxes.forEach((box) => {
                box.checked = false;
            });
            clearErrors(createForm, createAlert, CREATE_FIELDS);
        };

        document.getElementById('btnCreateUser').addEventListener('click', () => {
            clearErrors(createForm, createAlert, CREATE_FIELDS);
            createModal.show();
        });

        createModalEl.addEventListener('hidden.bs.modal', resetCreate);

        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(createForm, createAlert, CREATE_FIELDS);
            createSubmit.disabled = true;

            try {
                const payload = await post(createForm.action, new FormData(createForm));

                if (!payload.ok) {
                    showErrors(createForm, createAlert, CREATE_FIELDS, payload.message, payload.errors);
                    return;
                }

                createModal.hide();
                flash(payload.message, 'success');
                table.ajax.reload(null, false);
            } catch (error) {
                createAlert.textContent = 'Tidak bisa menghubungi server. Coba lagi.';
                createAlert.classList.remove('d-none');
            } finally {
                createSubmit.disabled = false;
            }
        });

        <?php if ($autoOpenCreate): ?>
        // Server memverifikasi ulang dan menolak isiannya, jadi modal dibuka
        // lagi apa adanya. Penandanya datang dari flashdata controller,
        // bukan dari URL, jadi tidak bisa dipicu crafted link.
        createModal.show();
        <?php endif; ?>
    })();
</script>
<?= $this->endSection() ?>