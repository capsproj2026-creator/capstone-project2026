<?php
session_start();
$allowed_roles = ['Student', 'Staff'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

$user_id = $_SESSION['user_id'];

// --- ACTIONS: MARK ALL READ / CLEAR ALL ---
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    } elseif ($_GET['action'] === 'clear_all') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    header("Location: notifications.php");
    exit();
}

// 1. Fetch User Data
$stmt_user = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE u.id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();

// 2. Fetch Notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | Smart Campus VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }

        /* Header Section matching screenshot exactly */
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .welcome-box h1 { font-size: 24px; color: #0f172a; font-weight: 800; }
        .welcome-box p { color: #64748b; font-size: 14px; margin-top: 2px; }
        
        .header-actions { display: flex; gap: 15px; }
        .action-link { font-size: 13px; font-weight: 600; text-decoration: none; color: #2563eb; display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .action-link:hover { text-decoration: underline; }
        .action-link.clear { color: #ef4444; }

        /* Notification Container */
        .notification-container { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .container-header { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #0f172a; font-size: 16px; }

        .notif-item { 
            padding: 20px; 
            border-bottom: 1px solid #f1f5f9; 
            display: flex; 
            gap: 15px; 
            position: relative;
            transition: background 0.2s;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f8fafc; }

        /* Exact Unread Formatting from Screenshot */
        .notif-item.unread { 
            background-color: #f0f7ff; 
            border-left: 4px solid #2563eb; 
        }
        .unread-dot { 
            width: 8px; height: 8px; background: #2563eb; 
            border-radius: 50%; position: absolute; right: 25px; top: 50%; transform: translateY(-50%); 
        }

        .notif-icon { 
            width: 40px; height: 40px; border-radius: 8px; background: #eff6ff; color: #2563eb; 
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;
        }
        .notif-content { flex: 1; padding-right: 30px; }
        .notif-content h4 { font-size: 15px; color: #0f172a; font-weight: 700; margin-bottom: 4px; }
        .notif-content p { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 6px; }
        .notif-time { font-size: 12px; color: #94a3b8; font-weight: 500; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div class="top-header">
                <div class="welcome-box">
                    <h1>Notifications</h1>
                    <p>Logged in as <b><?php echo htmlspecialchars($user_data['fullname']); ?></b> • <?php echo htmlspecialchars($user_data['role_name']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="?action=mark_all_read" class="action-link">
                        <i class="fa-solid fa-check-double"></i> Mark all as read
                    </a>
                    <a href="?action=clear_all" class="action-link clear" onclick="return confirm('Delete all notifications?')">
                        <i class="fa-solid fa-trash-can"></i> Clear All
                    </a>
                </div>
            </div>

            <div class="notification-container">
                <div class="container-header">Recent Activity</div>
                
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while($row = $notifications->fetch_assoc()): ?>
                    <div class="notif-item <?php echo $row['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
                        <div class="notif-content">
                            <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                            <p><?php echo htmlspecialchars($row['message']); ?></p>
                            <div class="notif-time">
                                <i class="fa-regular fa-clock"></i> 
                                <?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?>
                            </div>
                        </div>
                        <?php if($row['is_read'] == 0): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px; color: #94a3b8;">
                        <i class="fa-solid fa-envelope-open" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                        No notifications to show.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

</body>
</html>