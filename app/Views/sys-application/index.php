<?= $this->extend('layout/dashboard') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Manajemen Application</h1>
        <button type="button" class="btn btn-primary btn-sm" id="btnAddApplication"
            data-action="<?= base_url('application/create') ?>">
            <i class="fas fa-fw fa-plus me-1"></i> Tambah Application
        </button>
    </div>

    <div id="pageNotice" class="d-none" role="alert"></div>

    <!--
        Tabel disuapkan server-side. Barisnya dibuat JavaScript dari
        endpoint application/data, jadi <tbody> sengaja dibiarkan kosong:
        baris kosong PHP dengan colspan akan memicu peringatan DataTables
        ("Requested unknown parameter").
    -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Application</h6>
        </div>
        <div class="table-responsive">
            <table id="tbl-application" class="table table-striped table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Kode</th>
                        <th>API Key</th>
                        <th>Dibuat Oleh</th>
                        <th>Dibuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            <i class="fas fa-info-circle me-1"></i>
            API key hanya disimpan sebagai hash SHA-256. Key penuh ditampilkan sekali saat dibuat
            dan tidak bisa dibaca lagi dari sistem ini.
            <?php if (! is_super_admin()): ?>
                <br>Anda hanya melihat application yang Anda buat sendiri.
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->include('sys-application/_form-modal') ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('partial/datatables') ?>
<script>
    (() => {
        'use strict';

        const modalEl   = document.getElementById('modalApplication');
        const modal     = new bootstrap.Modal(modalEl);
        const form      = document.getElementById('formApplication');
        const label     = document.getElementById('modalApplicationLabel');
        const alertBox  = document.getElementById('modalApplicationAlert');
        const submitBtn = document.getElementById('modalApplicationSubmit');
        const notice    = document.getElementById('pageNotice');
        const addBtn    = document.getElementById('btnAddApplication');

        const FIELDS = ['name', 'code', 'description'];

        /*
         * Token CSRF harus selalu segar. Config\Security::$regenerate = true
         * membuat token berubah setiap POST sukses, sedangkan cookie-nya
         * httpOnly (Config\Cookie::$httponly) sehingga tidak bisa dibaca dari
         * sini. Jadi setiap respons JSON membawa token baru, dan nilai itu
         * yang dipakai untuk request berikutnya.
         */
        let csrf = { name: window.__csrfName, value: window.__csrfValue };

        const esc = (value) => {
            const holder = document.createElement('div');
            holder.textContent = (value === null || value === undefined) ? '' : String(value);
            return holder.innerHTML;
        };

        const hiddenCsrf = () =>
            `<input type="hidden" name="${esc(csrf.name)}" value="${esc(csrf.value)}">`;

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

        const clearErrors = () => {
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
            FIELDS.forEach((field) => {
                form.querySelector(`[name="${field}"]`).classList.remove('is-invalid');
                const hint = form.querySelector(`[data-feedback-for="${field}"]`);
                if (hint) {
                    hint.textContent = '';
                }
            });
        };

        const showErrors = (message, errors) => {
            const entries = Object.entries(errors || {});

            if (entries.length === 0) {
                alertBox.textContent = message || 'Gagal menyimpan.';
                alertBox.classList.remove('d-none');
                return;
            }

            alertBox.innerHTML = '<ul class="mb-0">'
                + entries.map(([, text]) => `<li>${esc(text)}</li>`).join('')
                + '</ul>';
            alertBox.classList.remove('d-none');

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

        const openModal = ({ title, action, values }) => {
            clearErrors();
            FIELDS.forEach((field) => {
                form.querySelector(`[name="${field}"]`).value = (values && values[field]) || '';
            });
            label.textContent = title;
            form.action = action;
            modal.show();
        };

        addBtn.addEventListener('click', () => openModal({
            title: 'Tambah Application',
            action: addBtn.dataset.action,
            values: { name: '', code: '', description: '' }
        }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            submitBtn.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf.value
                    },
                    body: new FormData(form)
                });

                const payload = await response.json();
                applyToken(payload);

                if (!payload.ok) {
                    showErrors(payload.message, payload.errors);
                    return;
                }

                modal.hide();
                flash(payload.message, 'success');
                table.ajax.reload(null, false);
            } catch (error) {
                alertBox.textContent = 'Tidak bisa menghubungi server. Coba lagi.';
                alertBox.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
            }
        });

        const table = new DataTable('#tbl-application', {
            serverSide: true,
            processing: true,
            ajax: '<?= base_url('application/data') ?>',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
            order: [[0, 'asc']],
            language: Object.assign({}, window.kanzaTableDefaults.language, {
                emptyTable: 'Belum ada application yang cocok.'
            }),
            columns: [
                {
                    // Kolom 0: Application (+ kode ownerless & deskripsi)
                    data: 'name',
                    render: (value, type, row) => {
                        if (type !== 'display') {
                            return value;
                        }
                        const ownerless = row.ownerless
                            ? '<span class="badge bg-secondary ms-1">Tanpa owner</span>'
                            : '';
                        const description = row.description
                            ? `<span class="d-block small text-muted">${esc(row.description)}</span>`
                            : '';
                        return `<i class="fas fa-cube me-1 text-muted"></i>${esc(value)}${ownerless}${description}`;
                    }
                },
                {
                    // Kolom 1: Kode
                    data: 'code',
                    render: (value, type) => (type === 'display' ? `<code>${esc(value)}</code>` : value)
                },
                {
                    // Kolom 2: jumlah API key — tidak bisa diurutkan di SQL
                    // karena dihitung dari tabel lain.
                    data: 'keyTotal',
                    orderable: false,
                    render: (value, type, row) => (type === 'display'
                        ? `<span class="badge bg-success">${row.keyActive} aktif</span> `
                          + `<span class="badge bg-secondary">${value} total</span>`
                        : value)
                },
                {
                    // Kolom 3: Dibuat Oleh
                    data: 'ownerName',
                    render: (value, type) => (type === 'display'
                        ? `<span class="small">${esc(value || '-')}</span>`
                        : value)
                },
                {
                    // Kolom 4: Dibuat
                    data: 'createdAt',
                    render: (value, type) => (type === 'display'
                        ? `<span class="small text-muted">${esc(value || '-')}</span>`
                        : value)
                },
                {
                    // Kolom 5: Aksi
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end text-nowrap',
                    render: (value, type, row) => {
                        if (type !== 'display') {
                            return '';
                        }
                        return '<a class="btn btn-sm btn-primary" href="' + esc(row.keysUrl) + '">'
                            + '<i class="fas fa-fw fa-key me-1"></i> Kelola Key</a> '
                            + '<button type="button" class="btn btn-sm btn-outline-primary js-edit"'
                            + ` data-action="${esc(row.editUrl)}"`
                            + ` data-name="${esc(row.name)}"`
                            + ` data-code="${esc(row.code)}"`
                            + ` data-description="${esc(row.description || '')}"`
                            + ' title="Ubah"><i class="fas fa-fw fa-pen"></i></button> '
                            + '<form class="d-inline js-delete" method="POST" action="' + esc(row.deleteUrl) + '">'
                            + hiddenCsrf()
                            + '<button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">'
                            + '<i class="fas fa-fw fa-trash"></i></button></form>';
                    }
                }
            ]
        });

        // Event delegation: baris bisa diganti sewaktu-waktu oleh paging,
        // sorting, atau reload, jadilistener tidak boleh dipasang per baris.
        document.getElementById('tbl-application').addEventListener('click', (event) => {
            const button = event.target.closest('.js-edit');

            if (button) {
                openModal({
                    title: 'Ubah Application',
                    action: button.dataset.action,
                    values: {
                        name: button.dataset.name || '',
                        code: button.dataset.code || '',
                        description: button.dataset.description || ''
                    }
                });
                return;
            }

            const del = event.target.closest('.js-delete button');

            if (del) {
                const name = del.closest('tr').querySelector('td').textContent.trim();
                del.closest('form').addEventListener('submit', (submitEvent) => {
                    submitEvent.preventDefault();
                    const form = submitEvent.currentTarget;
                    if (!window.confirm('Hapus application "' + name + '" beserta seluruh API key-nya?'
                        + ' Integrasi yang memakai key ini akan langsung gagal.')) {
                        return;
                    }
                    form.submit();
                }, { once: true });
            }
        });
    })();
</script>
<?= $this->endSection() ?>