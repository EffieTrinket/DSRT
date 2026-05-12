<?php
/**
 * Shared Sidebar Include
 *
 * Required variables (set before including this file):
 *   $activePage  - string: 'dashboard', 'disasters', 'residents', 'packages',
 *                          'distribution', 'reports', 'settings'
 *   $sidebarBase - string: '' for files in pages/, '../pages/' for files in actions/
 */
$sidebarBase = $sidebarBase ?? '';
$activePage  = $activePage  ?? '';
$isVolunteer = isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer';
$isAdmin     = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;

function sidebarActive(string $page, string $activePage): string {
    return $page === $activePage ? ' style="background-color: rgba(255, 255, 255, 0.2);"' : '';
}
?>
<div class="sidebar">
    <div>
        <h2><i class="fa-solid fa-house-flood-water"></i> DSRT</h2>

        <?php if (!$isVolunteer): ?>
        <a href="<?= $sidebarBase ?>dashboard.php"<?= sidebarActive('dashboard', $activePage) ?>>
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </a>
        <?php endif; ?>

        <a href="<?= $sidebarBase ?>disasters.php"<?= sidebarActive('disasters', $activePage) ?>>
            <i class="fa-solid fa-triangle-exclamation"></i> Disaster Records
        </a>

        <?php if (!$isVolunteer): ?>
        <a href="<?= $sidebarBase ?>residents.php"<?= sidebarActive('residents', $activePage) ?>>
            <i class="fa-solid fa-users"></i> Residents
        </a>
        <?php endif; ?>

        <a href="<?= $sidebarBase ?>packages.php"<?= sidebarActive('packages', $activePage) ?>>
            <i class="fa-solid fa-boxes-stacked"></i> Packages
        </a>

        <a href="<?= $sidebarBase ?>distribution.php"<?= sidebarActive('distribution', $activePage) ?>>
            <i class="fa-solid fa-truck-fast"></i> Distribution
        </a>

        <?php if (!$isVolunteer): ?>
        <a href="<?= $sidebarBase ?>reports.php"<?= sidebarActive('reports', $activePage) ?>>
            <i class="fa-solid fa-file-lines"></i> Reports
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <a href="<?= $sidebarBase ?>volunteers.php"<?= sidebarActive('volunteers', $activePage) ?>>
            <i class="fa-solid fa-user-plus"></i> Volunteers
        </a>
        <?php endif; ?>

        <a href="<?= $sidebarBase ?>settings.php"<?= sidebarActive('settings', $activePage) ?>>
            <i class="fa-solid fa-gear"></i> Settings
        </a>

        <a href="<?= $sidebarBase ?>logout.php" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

    <div class="sidebar-footer">
        Disaster Relief Tracker<br>
        Emergency Response System
    </div>
</div>
