<?php
session_start();
// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$allowed_statuses = ['Pending', 'Granted', 'Denied'];
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses, true) ? $_GET['status'] : 'Pending';

// Fetch Dynamic Stats
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Granted'")->fetch_assoc()['count'];
$declined_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Denied'")->fetch_assoc()['count'];

// SQL Joined with user_roles
$sql = "SELECT u.*, r.role_name 
        FROM users u 
        JOIN user_roles r ON u.user_role_id = r.id 
        WHERE u.status = '{$status_filter}' 
        ORDER BY u.id DESC";
$requests = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS KEPT EXACTLY AS PER YOUR FILE */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: #2563eb; }
        .stat-card.active { border-color: #2563eb; background: #eff6ff; }
        .stat-card p { color: #64748b; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .stat-card h2 { font-size: 28px; color: #0f172a; margin-top: 10px; font-weight: 800; }
        .requests-list { display: grid; gap: 15px; }
        .request-item { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .btn-approve { background: #10b981; color: #fff; }
        .btn-decline { background: #ef4444; color: #fff; }
        .btn-view { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>
        <main class="content-body">
            <h1 style="margin-bottom: 25px;">Registration Management</h1>
            <div class="stats-row">
                <a href="?status=Pending" class="stat-card <?php echo $status_filter == 'Pending' ? 'active' : ''; ?>">
                    <p>Pending</p><h2><?php echo $pending_count; ?></h2>
                </a>
                <a href="?status=Granted" class="stat-card <?php echo $status_filter == 'Granted' ? 'active' : ''; ?>">
                    <p>Approved</p><h2><?php echo $approved_count; ?></h2>
                </a>
                <a href="?status=Denied" class="stat-card <?php echo $status_filter == 'Denied' ? 'active' : ''; ?>">
                    <p>Declined</p><h2><?php echo $declined_count; ?></h2>
                </a>
            </div>

            <div class="requests-list">
                <?php if ($requests && $requests->num_rows > 0): ?>
                    <?php while($row = $requests->fetch_assoc()): ?>
                    <div class="request-item">
                        <div>
                            <h4 style="font-size: 16px; font-weight: 700;"><?php echo htmlspecialchars($row['fullname']); ?></h4>
                            <p style="font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($row['role_name']); ?> • ID: <?php echo htmlspecialchars($row['id_number']); ?></p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <?php if($row['status'] == 'Pending'): ?>
                                <a href="approve.php?id=<?php echo $row['id']; ?>" class="btn-action btn-approve" onclick="return confirm('Approve user?')">Approve</a>
                                <a href="decline.php?id=<?php echo $row['id']; ?>" class="btn-action btn-decline" onclick="return confirm('Decline user?')">Decline</a>
                            <?php endif; ?>
                            <a href="view_user.php?id=<?php echo $row['id']; ?>" class="btn-action btn-view">View Profile</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; padding: 40px;">No records found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>