<div class="col-lg-8">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-info">
                Informasi Sistem
            </h6>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <tr>
                    <th>Operating System</th>
                    <td><?= esc($systemInfo['os']) ?></td>
                </tr>
                <tr>
                    <th>PHP Version</th>
                    <td><?= esc($systemInfo['php']) ?></td>
                </tr>
                <tr>
                    <th>Web Server</th>
                    <td><?= esc($systemInfo['server']) ?></td>
                </tr>
                <tr>
                    <th>Domain</th>
                    <td><?= esc($systemInfo['domain']) ?></td>
                </tr>
                <tr>
                    <th>Base URL</th>
                    <td><?= esc($systemInfo['base_url']) ?></td>
                </tr>
                <tr>
                    <th>Memory Limit</th>
                    <td><?= esc($systemInfo['memory']) ?></td>
                </tr>
                <tr>
                    <th>Timezone</th>
                    <td><?= esc($systemInfo['timezone']) ?></td>
                </tr>
                <tr>
                    <th>Server Time</th>
                    <td><?= esc($systemInfo['date']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>