<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="<?= base_url('dashboard') ?>">
            <i class="fas fa-project-diagram"></i> Kanza Bridge
        </a>

        <!-- Navbar Nav -->
        <ul class="navbar-nav me-auto">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= service('uri')->getSegment(1) === 'dashboard' || service('uri')->getSegment(1) === '' ? 'active fw-bold' : '' ?>" href="<?= base_url('dashboard') ?>">
                    <i class="fas fa-fw fa-tachometer-alt me-1"></i> Dashboard
                </a>
            </li>

            <!-- Admin System -->
            <?php if (session()->get('kd_jabatan') == env('ROLE_ADMIN')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= service('uri')->getSegment(1) === 'pegawai' ? 'active fw-bold' : '' ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-fw fa-users-cog me-1"></i> Manajemen User
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li>
                            <a class="dropdown-item <?= service('uri')->getSegment(1) === 'pegawai' ? 'active' : '' ?>" href="<?= base_url('pegawai') ?>">
                                <i class="fas fa-fw fa-users me-2"></i> Pegawai
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Guide -->
            <li class="nav-item">
                <a class="nav-link <?= service('uri')->getSegment(1) === 'guide' ? 'active fw-bold' : '' ?>" href="<?= base_url('guide') ?>">
                    <i class="fas fa-fw fa-book me-1"></i> Guide
                </a>
            </li>

        </ul>

        <!-- Nav Item - User Information -->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2 d-none d-lg-inline text-secondary small">
                        <?= session()->get('nama') ?>
                        <small class="text-muted">(<?= session()->get('jabatan') ?>)</small>
                    </span>
                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="<?= base_url('profile') ?>">
                        <i class="fas fa-user fa-sm fa-fw me-2 text-muted"></i> Profil
                    </a>
                    <a class="dropdown-item" href="<?= base_url('settings') ?>">
                        <i class="fas fa-cogs fa-sm fa-fw me-2 text-muted"></i> Pengaturan
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-muted"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>
<!-- End of Topbar -->
