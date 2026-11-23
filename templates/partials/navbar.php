<?php
$currentRoute = trim((string) ($_GET['r'] ?? 'home'), '/');
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('home')) ?>" aria-label="DonorNexa home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </span>
            <span class="brand-text">Donor<strong>Nexa</strong></span>
            <?php if ($authUser): ?>
                <span class="role-pill role-<?= e($authUser->role) ?>"><?= e(ucfirst($authUser->role)) ?></span>
            <?php endif; ?>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Toggle navigation">
            <span class="hamburger-box"><span class="hamburger-inner"></span></span>
        </button>
        <nav id="site-nav" class="site-nav" aria-label="Main navigation">
            <ul>
                <li><a href="<?= e(url('emergencies')) ?>" class="<?= $currentRoute === 'emergencies' ? 'active' : '' ?>">Emergencies</a></li>
                <?php if (!$authUser): ?>
                    <li><a href="<?= e(url('register/donor')) ?>" class="<?= $currentRoute === 'register/donor' ? 'active' : '' ?>">Become a donor</a></li>
                    <li><a href="<?= e(url('register/hospital')) ?>" class="<?= $currentRoute === 'register/hospital' ? 'active' : '' ?>">For hospitals</a></li>
                    <li><a class="btn btn-small" href="<?= e(url('login')) ?>">Log in</a></li>
                <?php else: ?>
                    <?php if ($authUser->isDonor()): ?>
                        <li><a href="<?= e(url('donor/dashboard')) ?>" class="<?= $currentRoute === 'donor/dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                        <li><a href="<?= e(url('donor/history')) ?>" class="<?= $currentRoute === 'donor/history' ? 'active' : '' ?>">History</a></li>
                        <li><a href="<?= e(url('donor/profile')) ?>" class="<?= $currentRoute === 'donor/profile' ? 'active' : '' ?>">Profile</a></li>
                    <?php elseif ($authUser->isHospital()): ?>
                        <li><a href="<?= e(url('hospital/dashboard')) ?>" class="<?= $currentRoute === 'hospital/dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                        <li><a href="<?= e(url('hospital/request/new')) ?>" class="<?= $currentRoute === 'hospital/request/new' ? 'active' : '' ?>">New request</a></li>
                        <li><a href="<?= e(url('hospital/inventory')) ?>" class="<?= $currentRoute === 'hospital/inventory' ? 'active' : '' ?>">Inventory</a></li>
                    <?php else: ?>
                        <li><a href="<?= e(url('admin/dashboard')) ?>" class="<?= $currentRoute === 'admin/dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                        <li><a href="<?= e(url('admin/hospitals')) ?>" class="<?= strpos($currentRoute, 'admin/hospital') === 0 ? 'active' : '' ?>">Hospitals</a></li>
                        <li><a href="<?= e(url('admin/users')) ?>" class="<?= strpos($currentRoute, 'admin/user') === 0 ? 'active' : '' ?>">Users</a></li>
                        <li><a href="<?= e(url('admin/requests')) ?>" class="<?= strpos($currentRoute, 'admin/request') === 0 ? 'active' : '' ?>">Requests</a></li>
                        <li><a href="<?= e(url('admin/audit')) ?>" class="<?= strpos($currentRoute, 'admin/audit') === 0 ? 'active' : '' ?>">Audit</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= e(url('notifications')) ?>" class="bell <?= $currentRoute === 'notifications' ? 'active' : '' ?>" aria-label="Notifications<?= $unread ? ", $unread unread" : '' ?>">
                            <svg class="nav-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <span>Alerts</span>
                            <?php if ($unread): ?> <span class="count-pill"><?= (int) $unread ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-user-item">
                        <form method="post" action="<?= e(url('logout')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <button class="btn btn-small btn-outline btn-logout" type="submit" title="Sign out of <?= e($authUser->name) ?>">
                                <svg class="logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                <span>Log out (<?= e(strtok($authUser->name, ' ')) ?>)</span>
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
                <li class="nav-theme-item">
                    <button class="btn-theme-toggle" id="theme-toggle" type="button" aria-label="Toggle dark/light theme" title="Toggle dark/light theme">
                        <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="4"></circle>
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
                        </svg>
                        <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</header>
