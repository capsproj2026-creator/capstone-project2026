<?php
// Get the current tab from the URL, default to 'general'
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
?>

<div class="settings-nav-wrapper">
    <a href="?tab=general" class="settings-nav-item <?= ($current_tab == 'general') ? 'active' : '' ?>">
        <i class="fa-solid fa-gear"></i> General
    </a>
    
    <a href="?tab=admins" class="settings-nav-item <?= ($current_tab == 'admins') ? 'active' : '' ?>">
        <i class="fa-solid fa-user-shield"></i> Admin Users
    </a>
    
    <a href="?tab=notifications" class="settings-nav-item <?= ($current_tab == 'notifications') ? 'active' : '' ?>">
        <i class="fa-solid fa-bell"></i> Notifications
    </a>
    
    <a href="?tab=violations" class="settings-nav-item <?= ($current_tab == 'violations') ? 'active' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Violations
    </a>
</div>