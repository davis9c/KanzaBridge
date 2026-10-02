<!-- Sidebar -->
<?php
// User tanpa role tetap boleh login, tetapi tidak melihat menu apa pun.
$hasMenu = has_any_role();
$seg1     = service('uri')->getSegment(1);
?>
<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark vh-100" style="width: 280px;">
    <!-- Brand -->
    <a href="<?= base_url('dashboard') ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-project-diagram fa-2x me-2"></i>
        <span class="fs-4">Kanza Bridge</span>
    </a>
    <hr>

    <!-- Navigation -->
    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($hasMenu): ?>
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link text-white <?= $seg1 === 'dashboard' || $seg1 === '' ? 'active' : '' ?>" href="<?= base_url('dashboard') ?>">
                    <i class="fas fa-fw fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- Manajemen User (SuperAdmin & Admin) -->
            <li class="nav-item">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#collapseMaster" role="button" aria-expanded="false" aria-controls="collapseMaster">
                    <span><i class="fas fa-fw fa-users-cog me-2"></i>Manajemen User</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="collapseMaster">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link text-white <?= $seg1 === 'user' ? 'active' : '' ?>" href="<?= base_url('user') ?>">
                                <i class="fas fa-fw fa-user-gear me-2"></i>User
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Guide -->
            <li class="nav-item">
                <a class="nav-link text-white <?= $seg1 === 'guide' ? 'active' : '' ?>" href="<?= base_url('guide') ?>">
                    <i class="fas fa-fw fa-book me-2"></i>
                    Guide
                </a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <span class="nav-link text-white-50 small">
                    <i class="fas fa-fw fa-lock me-2"></i>
                    Tidak ada menu untuk akun Anda.
                </span>
            </li>
        <?php endif; ?>

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
            <strong><?= esc(session()->get('access_full_name') ?: session()->get('nama')) ?></strong>
            <?php if (is_super_admin()): ?>
                <span class="badge bg-warning text-dark ms-2">SuperAdmin</span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <?php if ($hasMenu): ?>
                <li><a class="dropdown-item" href="<?= base_url('profile') ?>">Profil</a></li>
                <li><a class="dropdown-item" href="<?= base_url('settings') ?>">Pengaturan</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
        </ul>
    </div>
</div>
<!-- End of Sidebar -->
