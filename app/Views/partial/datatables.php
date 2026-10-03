<?php

/**
 * Aset DataTables (core + integrasi Bootstrap 5).
 *
 * Sengaja tidak dimuat di layout: hanya 3 halaman yang punya tabel, jadi
 * halaman lain tidak perlu menanggung ~40 KB ekstra.
 *
 * Dipakai dari section 'scripts' layout/dashboard.php (setelah
 * bootstrap.bundle.min.js) lalu diinisialisasi per tabel, contoh:
 *
 *   <?= $this->section('scripts') ?>
 *       <?= $this->include('partial/datatables') ?>
 *       <script>
 *           new DataTable('#tbl-user', { order: [[5, 'desc']] });
 *       </script>
 *   <?= $this->endSection() ?>
 *
 * DataTables 3.x tidak memakai jQuery.
 */
?>
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@3.1.3/css/dataTables.bootstrap5.min.css"
    integrity="sha384-OZKa6QSlaaq/LGR1sBFkYhC0c/nacIFh1chsblhDUxggC9Zb0XLEEMs95i1Kydnt" crossorigin="anonymous">

<script src="https://cdn.jsdelivr.net/npm/datatables.net@3.1.3/js/dataTables.min.js"
    integrity="sha384-2VkhZZqhleNsGIa6GcWWRJn09k3lpejTs0B2LzDbeU/YSNfr6nKAnRTgasXvxWc3" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@3.1.3/js/dataTables.bootstrap5.min.js"
    integrity="sha384-4d8X9sr6Gnv9AgIQn6bv3lmQxj5fD+9bVAun0/XMmdy7oPRvT0adfiUUiiYpi4Ck" crossorigin="anonymous"></script>

<script>
    (() => {
        'use strict';

        // Konfigurasi dasar yang dipakai semua tabel.
        window.kanzaTableDefaults = {
            language: {
                emptyTable: 'Tidak ada data.',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(disaring dari _MAX_ total data)',
                lengthMenu: 'Tampilkan _MENU_ data',
                loadingRecords: 'Memuat...',
                processing: 'Memproses...',
                search: 'Cari:',
                searchPlaceholder: 'Ketik untuk mencari...',
                zeroRecords: 'Tidak ada data yang cocok.',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: '›',
                    previous: '‹'
                },
                aria: {
                    sortAscending: ': urutkan naik',
                    sortDescending: ': urutkan turun'
                }
            },
            pagingType: 'bootstrap'
        };

        // Helper supaya tiap view cukup menulis merge opsi singkat.
        //
        // `language` digabung sendiri (merge dalam) karena Object.assign
        // biasa akan mengganti SELURUH objek language — memetik satu
        // `emptyTable` di view lalu kehilangan paginate/aria/info.
        //
        // Default juga tidak dimutasi: kalau tidak, opsi halaman pertama
        // yang menimpa `emptyTable` akan bocor ke halaman berikutnya.
        window.kanzaTable = (selector, options) => {
            const opts = Object.assign({}, window.kanzaTableDefaults, options ?? {});

            opts.language = Object.assign({}, window.kanzaTableDefaults.language, opts.language ?? {});

            return new DataTable(selector, opts);
        };
    })();
</script>