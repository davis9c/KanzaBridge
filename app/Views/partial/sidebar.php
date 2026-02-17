<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center"
        href="<?= base_url('dashboard') ?>">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-project-diagram"></i>
        </div>
        <div class="sidebar-brand-text mx-3">
            XProject <sup>1.0</sup>
        </div>
    </a>

    <hr class="sidebar-divider my-0">

    <!-- Dashboard -->
    <li class="nav-item <?= service('uri')->getSegment(1) === 'dashboard' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= base_url('dashboard') ?>">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>
    <!-- User & Role - Only for Admin -->
    <?php if (session()->get('kd_jabatan') == env('ROLE_ADMIN')): ?>
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Admin System
        </div>
        <li class="nav-item">
            <a class="nav-link collapsed" href="#"
                data-toggle="collapse"
                data-target="#collapseMaster"
                aria-expanded="false"
                aria-controls="collapseMaster">

                <i class="fas fa-fw fa-users-cog"></i>
                <span>Manajemen User</span>
            </a>

            <div id="collapseMaster"
                class="collapse <?= in_array(service('uri')->getSegment(1), ['user', 'role']) ? 'show' : '' ?>"
                data-parent="#accordionSidebar">

                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Pengaturan:</h6>
                    <a class="collapse-item <?= service('uri')->getSegment(1) === 'pegawai' ? 'active' : '' ?>"
                        href="<?= base_url('pegawai') ?>">
                        Pegawai
                    </a>
                </div>
            </div>
        </li>
    <?php endif; ?>

    <!-- Guide -->
    <li class="nav-item <?= service('uri')->getSegment(1) === 'guide' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= base_url('guide') ?>">
            <i class="fas fa-fw fa-book"></i>
            <span>Guide</span>
        </a>
    </li>

    <!-- Diagnostics - Only for Admin -->
    <?php if (session()->get('kd_jabatan') === env('ROLE_ADMIN')): ?>
        <li class="nav-item <?= service('uri')->getSegment(1) === 'diagnose' ? 'active' : '' ?>">
            <a class="nav-link" href="<?= base_url('diagnose') ?>">
                <i class="fas fa-fw fa-tools"></i>
                <span>Diagnostics</span>
            </a>
        </li>
    <?php endif; ?>

    <hr class="sidebar-divider d-none d-md-block">

    <!-- Current User Info -->
    <div class="text-center d-md-block my-3">
        <small class="text-white">
            <strong>Logged as:</strong><br>
            <?= session()->get('nama') ?><br>
            <span class="badge badge-light"><?= session()->get('nm_jabatan') ?></span>
        </small>
    </div>

    <!-- Sidebar Toggler -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->