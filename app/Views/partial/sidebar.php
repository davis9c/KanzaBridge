<!-- Sidebar -->
<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark vh-100" style="width: 280px;">
    <!-- Brand -->
    <a href="<?= base_url('dashboard') ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-project-diagram fa-2x me-2"></i>
        <span class="fs-4">Kanza Bridge</span>
    </a>
    <hr>

    <!-- Navigation -->
    <ul class="nav nav-pills flex-column mb-auto">
        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link text-white <?= service('uri')->getSegment(1) === 'dashboard' || service('uri')->getSegment(1) === '' ? 'active' : '' ?>" href="<?= base_url('dashboard') ?>">
                <i class="fas fa-fw fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>

        <!-- Admin System -->
        <?php if (session()->get('kd_jabatan') == env('ROLE_ADMIN')): ?>
            <li class="nav-item">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#collapseMaster" role="button" aria-expanded="false" aria-controls="collapseMaster">
                    <span><i class="fas fa-fw fa-users-cog me-2"></i>Manajemen User</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="collapseMaster">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link text-white <?= service('uri')->getSegment(1) === 'pegawai' ? 'active' : '' ?>" href="<?= base_url('pegawai') ?>">
                                <i class="fas fa-fw fa-users me-2"></i>Pegawai
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        <?php endif; ?>

        <!-- Guide -->
        <li class="nav-item">
            <a class="nav-link text-white <?= service('uri')->getSegment(1) === 'guide' ? 'active' : '' ?>" href="<?= base_url('guide') ?>">
                <i class="fas fa-fw fa-book me-2"></i>
                Guide
            </a>
        </li>

        <!-- Logout -->
        <li class="nav-item">
            <button type="button" class="nav-link text-white btn btn-link text-start w-100" style="border: none; background: none;" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-fw fa-sign-out-alt me-2"></i>
                Logout
            </button>
        </li>
    </ul>

    <hr>

    <!-- Current User Info -->
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle fa-2x me-2"></i>
            <strong><?= session()->get('nama') ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="<?= base_url('profile') ?>">Profil</a></li>
            <li><a class="dropdown-item" href="<?= base_url('settings') ?>">Pengaturan</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
        </ul>
    </div>
</div>
<!-- End of Sidebar -->
