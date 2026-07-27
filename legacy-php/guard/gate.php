<?php
session_start();
// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Guard') {
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
        </main>
    </div>
</body>
</html>