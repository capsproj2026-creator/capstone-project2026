<?php
session_start();
$allowed_roles = ['Student', 'Staff'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

// Fetch stats
$pending = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];
$total = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role_id != 'Guard'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking</title>
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