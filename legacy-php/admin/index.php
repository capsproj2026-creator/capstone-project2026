<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

// Fetch stats
$pending = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];
$total = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role_id != 'Admin'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Campus VMS</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            display: flex;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .content-body {
            margin-right: 260px;
            padding: 30px;
            overflow-y: auto; /* Only the content scrolls, not the whole page */
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div class="card">
                <h1>User Management</h1>
                <p>If you can see the profile icon on the top right, the margin is fixed!</p>
            </div>
        </main>
    </div>

</body>
</html>