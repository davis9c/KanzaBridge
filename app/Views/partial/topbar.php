<!-- Topbar -->
<?php
$hasMenu = has_any_role();
$seg1     = service('uri')->getSegment(1);
$roles    = session()->get('access_roles') ?: [];
?>
<nav class="navbar navbar-expand navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="<?= base_url('dashboard') ?>">
            <i class="fas fa-project-diagram"></i> Kanza Bridge
        </a>

        <!-- Navbar Nav -->
        <ul class="navbar-nav me-auto">
            <?php if ($hasMenu): ?>
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= $seg1 === 'dashboard' || $seg1 === '' ? 'active fw-bold' : '' ?>" href="<?= base_url('dashboard') ?>">
                        <i class="fas fa-fw fa-tachometer-alt me-1"></i> Dashboard
                    </a>
                </li>

                <!-- Manajemen User (SuperAdmin & Admin) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $seg1 === 'user' ? 'active fw-bold' : '' ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-fw fa-users-cog me-1"></i> Manajemen User
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li>
                            <a class="dropdown-item <?= $seg1 === 'user' ? 'active' : '' ?>" href="<?= base_url('user') ?>">
                                <i class="fas fa-fw fa-user-gear me-2"></i> User
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Guide -->
                <li class="nav-item">
                    <a class="nav-link <?= $seg1 === 'guide' ? 'active fw-bold' : '' ?>" href="<?= base_url('guide') ?>">
                        <i class="fas fa-fw fa-book me-1"></i> Guide
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Nav Item - User Information -->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2 d-none d-lg-inline text-secondary small">
                        <?= esc(session()->get('access_full_name') ?: session()->get('nama')) ?>
                        <?php if ($roles !== []): ?>
                            <small class="text-muted">(<?= esc(implode(', ', array_map('role_label', $roles))) ?>)</small>
                        <?php endif; ?>
                    </span>
                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <?php if ($hasMenu): ?>
                        <a class="dropdown-item" href="<?= base_url('profile') ?>">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-muted"></i> Profil
                        </a>
                        <a class="dropdown-item" href="<?= base_url('settings') ?>">
                            <i class="fas fa-cogs fa-sm fa-fw me-2 text-muted"></i> Pengaturan
                        </a>
                        <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-muted"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>
<!-- End of Topbar -->
