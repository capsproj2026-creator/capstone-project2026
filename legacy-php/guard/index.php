<?php
session_start();
// Check if user is logged in and has the 'Guard' role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Guard') {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

// Fetch stats for the dashboard
$pending = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];
// Count all users except Guards for total management overview
$total_users = $conn->query("SELECT COUNT(u.id) as count FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE r.role_name != 'Guard'")->fetch_assoc()['count'];

// Fetch the 5 most recent user registrations
$recent_users = $conn->query("SELECT u.*, r.role_name 
                              FROM users u 
                              JOIN user_roles r ON u.user_role_id = r.id 
                              WHERE r.role_name != 'Guard' 
                              ORDER BY u.id DESC LIMIT 5");
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
        /* CSS KEPT EXACTLY THE SAME AS PROVIDED */
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
            padding: 30px;
            overflow-y: auto; 
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        /* Added small helper styles for the new content elements */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-box { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stat-box h3 { font-size: 13px; color: #64748b; text-transform: uppercase; margin-bottom: 10px; }
        .stat-box p { font-size: 24px; font-weight: 700; color: #0f172a; }
        
        .user-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .user-table th { text-align: left; padding: 12px; border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 13px; }
        .user-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-granted { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div style="margin-bottom: 25px;">
                <h1 style="font-size: 24px; color: #0f172a; font-weight: 800;">User Management Dashboard</h1>
                <p style="color: #64748b;">System overview and recent activity</p>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Pending Requests</h3>
                    <p><?php echo $pending; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Total Managed Users</h3>
                    <p><?php echo $total_users; ?></p>
                </div>
            </div>

            <div class="card">
                <h2 style="font-size: 18px; margin-bottom: 15px;">Recent Registrations</h2>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $recent_users->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <span class="status-badge <?php echo ($user['status'] == 'Pending') ? 'status-pending' : 'status-granted'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

</body>
</html>