<?php
session_start();
// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

// 1. Logic for Stats and Filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';

// Fetch Dynamic Stats for Cards
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Granted'")->fetch_assoc()['count'];
$declined_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Denied'")->fetch_assoc()['count'];

// Fetch Users based on Status Filter
$sql = "SELECT * FROM users WHERE status = '$status_filter' ORDER BY id DESC";
$requests = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Management - Smart Campus VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="include/settings_nav.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div class="settings-header" style="margin-bottom: 20px;">
                <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 5px;">System Settings</h1>
                <p style="color: #64748b; font-size: 15px;">Configure system preferences and access rules</p>
            </div>

            <?php include('include/settings_nav.php'); ?>

            <div class="settings-content-card">
                <?php 
                    // Load content files based on ?tab= (e.g., settings_general.php)
                    $allowed = ['general', 'admins', 'notifications', 'violations', 'roles'];
                    $file = in_array($current_tab, $allowed) ? "include/settings_{$current_tab}.php" : "include/settings_general.php";
                    include($file);
                ?>
            </div>
        </main>
    </div>

</body>
</html>