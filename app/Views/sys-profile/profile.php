<div class="col-lg-6">
    <div class="card shadow mb-4">

        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?= $title ?>
            </h6>
        </div>

        <div class="card-body">

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success">
                    <?= session()->getFlashdata('success') ?>
                </div>
            <?php endif; ?>

            <h3><?= esc($title); ?></h3>
            <?php
            //dd($user) ;
            ?>
            <table class="table table-bordered table-striped">
                <tr>
                    <th width="30%">NIK</th>
                    <td><?= esc($user['nik']); ?></td>
                </tr>
                <tr>
                    <th>Nama</th>
                    <td><?= esc($user['nama']); ?></td>
                </tr>
                <tr>
                    <th>Jenis Kelamin</th>
                    <td><?= esc($user['jk']); ?></td>
                </tr>
                <tr>
                    <th>Tempat, Tanggal Lahir</th>
                    <td><?= esc($user['tmp_lahir']); ?>, <?= esc($user['tgl_lahir']); ?></td>
                </tr>
                <tr>
                    <th>Alamat</th>
                    <td><?= esc($user['alamat']); ?></td>
                </tr>
                <tr>
                    <th>Kota</th>
                    <td><?= esc($user['kota']); ?></td>
                </tr>
                <tr>
                    <th>Jabatan</th>
                    <td><?= esc($user['nm_jbtn']); ?>(<?= esc($user['kd_jbtn']); ?>)</td>
                </tr>
                <tr>
                    <th>Status Aktif</th>
                    <td>
                        <span class="badge bg-success">
                            <?= esc($user['stts_aktif']); ?>
                        </span>
                    </td>
                </tr>
            </table>

        </div>

        <div class="card-footer text-muted">
            Terakhir diperbarui:
        </div>
    </div>
</div>