<!-- Topbar -->
<?php
$hasMenu = has_any_role();
$seg1     = service('uri')->getSegment(1);
$roles    = session()->get('access_roles') ?: [];

// "Penggunaan" sudah jadi dashboard, jadi yang aktif di dropdown
// Application hanya halaman daftarnya.
$isAppPage = $seg1 === 'application';

// Satu sumber kebenaran dengan App\Filters\AccessFilter: role yang boleh
// membuka "Manajemen User". Supervisor dan Petugas tetap punya menu, jadi
// $hasMenu tidak berubah — mereka hanya tidak boleh masuk ke bagian ini.
$userAdminRule = config('Access')->restrictedRoutes['user'] ?? null;
$canManageUser = $userAdminRule === null
    || has_any_of_roles((array) ($userAdminRule['roles'] ?? []));
?>
<!--
     Navbar memakai navbar-expand-lg + collapse, dengan navbar-toggler.

     Sebelumnya `navbar-expand` tanpa toggler maupun collapse: di layar sempit
     atau saat browser di-zoom, link menu meluber keluar layar DAN tidak ada
     tombol apa pun untuk memunculkannya — menu jadi hilang total. Sekarang
     di bawah 992px tombol hamburger men-collapse isi menu.
-->
<nav class="navbar navbar-expand-lg bg-body shadow-sm">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="<?= base_url('dashboard') ?>">
            <i class="fas fa-project-diagram"></i> Kanza Bridge
        </a>

        <!-- Hanya terlihat di layar kecil; menyembunyikan/munculkan isi menu. -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarKanza" aria-controls="navbarKanza"
            aria-expanded="false" aria-label="Buka menu navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarKanza">
            <!-- Navbar Nav -->
            <ul class="navbar-nav me-auto">
                <?php if ($hasMenu): ?>
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?= $seg1 === 'dashboard' || $seg1 === '' ? 'active fw-bold' : '' ?>" href="<?= base_url('dashboard') ?>">
                            <i class="fas fa-fw fa-gauge-high me-1"></i> Dashboard
                        </a>
                    </li>

                    <?php if ($canManageUser): ?>
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
                    <?php endif; ?>

                    <!-- Manajemen Application / API Key -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $isAppPage ? 'active fw-bold' : '' ?>"
                            href="#" id="applicationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-fw fa-key me-1"></i> Application
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="applicationDropdown">
                            <li>
                                <a class="dropdown-item <?= $isAppPage ? 'active' : '' ?>"
                                    href="<?= base_url('application') ?>">
                                    <i class="fas fa-fw fa-cubes me-2 text-muted"></i> Application
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= base_url('application') ?>">
                                    <i class="fas fa-fw fa-key me-2 text-muted"></i> API Key
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
                <?php else: ?>
                    <!-- User tanpa role tetap boleh login, tapi tidak punya menu. -->
                    <li class="nav-item">
                        <span class="navbar-text small text-muted">Tidak ada menu untuk akun Anda.</span>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Nav Item - User Information -->
            <ul class="navbar-nav ms-auto">
                <!-- Toggle tema terang/gelap -->
                <?= $this->include('partial/theme-toggle') ?>

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
    </div>
</nav>
<!-- End of Topbar -->
