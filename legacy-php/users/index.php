<?php
session_start();
include '../config.php';

/**
 * 1. Security Logic: 
 * We check if the session 'user_role' (which should be a string like 'Student') 
 * is allowed to access this user dashboard.
 */
$allowed_roles = ['Student', 'Staff'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/**
 * 2. Fetch User Data with JOIN:
 * We join the 'user_roles' table to get the readable 'role_name'.
 */
$stmt = $conn->prepare("SELECT u.*, r.role_name 
                        FROM users u 
                        JOIN user_roles r ON u.user_role_id = r.id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 3. Fetch System Stats (Updated queries to use JOIN for cleaner filtering)
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'")->fetch_assoc()['count'];

// Total campus users excluding Guards (using role_name from joined logic)
$total_campus_users = $conn->query("SELECT COUNT(u.id) as count 
                                   FROM users u 
                                   JOIN user_roles r ON u.user_role_id = r.id 
                                   WHERE r.role_name != 'Guard'")->fetch_assoc()['count'];

// 4. Fetch Parking Rules
$rules_result = $conn->query("SELECT * FROM parking_rules ORDER BY id ASC");

// 5. Fetch General Information
$gen_info_result = $conn->query("SELECT * FROM general_informations ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard | Smart Campus VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ALL CSS CODE KEPT EXACTLY AS PROVIDED */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .welcome-box h1 { font-size: 24px; color: #0f172a; font-weight: 800; }
        .welcome-box p { color: #64748b; font-size: 14px; margin-top: 4px; }
        .grid-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 25px; }
        .info-list { display: flex; flex-direction: column; gap: 15px; }
        .info-item { display: flex; gap: 12px; padding: 15px; border-radius: 12px; font-size: 14px; line-height: 1.5; color: #334155; }
        .info-item.green { background: #f0fdf4; border: 1px solid #dcfce7; }
        .info-item.blue { background: #eff6ff; border: 1px solid #dbeafe; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div class="container">
                
                <div class="top-header">
                    <div class="welcome-box">
                        <h1>Welcome back, <?php echo htmlspecialchars($user['fullname']); ?>!</h1>
                        <p>Currently logged in as <b><?php echo htmlspecialchars($user['role_name']); ?></b> • <?php echo date('F d, Y'); ?></p>
                    </div>
                </div>

                <div class="grid-layout">
                    <div class="card">
                        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;"><i class="fa-solid fa-circle-info" style="margin-right: 10px;"></i> General Information</h2>
                        <div class="info-list">
                            <?php if($gen_info_result->num_rows > 0): ?>
                                <?php while($info = $gen_info_result->fetch_assoc()): ?>
                                <div class="info-item green">
                                    <i class="fa-solid fa-check-double" style="color: #10b981; margin-top: 3px;"></i>
                                    <span><?php echo htmlspecialchars($info['description']); ?></span>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color:#64748b; font-style:italic;">No general information available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;"><i class="fa-solid fa-square-parking" style="margin-right: 10px;"></i> Official Parking Rules</h2>
                        <div class="info-list">
                            <?php if($rules_result->num_rows > 0): ?>
                                <?php while($rule = $rules_result->fetch_assoc()): ?>
                                <div class="info-item blue">
                                    <i class="fa-solid fa-circle-info" style="color: #2563eb; margin-top: 3px;"></i>
                                    <span><?php echo htmlspecialchars($rule['description']); ?></span>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color:#64748b; font-style:italic;">No rules posted yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>