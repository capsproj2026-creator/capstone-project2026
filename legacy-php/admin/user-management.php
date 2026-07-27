<?php
session_start();
include '../config.php';

// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// 1. FETCH DASHBOARD STATS (JOINed to use role_name for easier counting)
// We use the names from user_roles to ensure accuracy
$total_users = $conn->query("SELECT COUNT(*) as c FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE r.role_name != 'Admin'")->fetch_assoc()['c'];
$student_count = $conn->query("SELECT COUNT(*) as c FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE r.role_name = 'Student'")->fetch_assoc()['c'];
$staff_count = $conn->query("SELECT COUNT(*) as c FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE r.role_name = 'Staff'")->fetch_assoc()['c'];
$guard_count = $conn->query("SELECT COUNT(*) as c FROM users u JOIN user_roles r ON u.user_role_id = r.id WHERE r.role_name = 'Guard'")->fetch_assoc()['c'];

// 2. SEARCH & FILTER LOGIC
$allowed_types = ['All', 'Student', 'Staff', 'Guard'];
$type_filter = isset($_GET['type']) && in_array($_GET['type'], $allowed_types, true) ? $_GET['type'] : 'All';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

/**
 * UPDATED SQL:
 * We JOIN users (u) with user_roles (r) to get the 'role_name'
 */
$sql = "SELECT u.*, r.role_name 
        FROM users u 
        JOIN user_roles r ON u.user_role_id = r.id 
        WHERE r.role_name != 'Admin'";

if ($type_filter !== 'All') {
    $sql .= " AND r.role_name = '{$type_filter}'";
}

if (!empty($search)) {
    $sql .= " AND (u.fullname LIKE '%$search%' OR u.id_number LIKE '%$search%' OR u.plate_number LIKE '%$search%')";
}

$sql .= " ORDER BY u.fullname ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }

        /* Sidebar Nav Placeholder */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stat-card p { color: #64748b; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        .stat-card h3 { font-size: 24px; font-weight: 700; color: #0f172a; }

        /* Filters */
        .filter-bar { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .search-box { position: relative; flex: 1; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-input { width: 100%; padding: 10px 10px 10px 35px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
        .filter-select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 14px; min-width: 150px; }

        /* Table */
        .table-container { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 15px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; background: #e2e8f0; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        /* Using role_name strings for CSS classes */
        .badge-student { background: #eff6ff; color: #2563eb; }
        .badge-staff { background: #fdf2f7; color: #db2777; }
        .badge-guard { background: #f0fdf4; color: #16a34a; }
        
        .status-dot { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #16a34a; }
        .status-dot::before { content: ""; width: 8px; height: 8px; background: #16a34a; border-radius: 50%; }

        .btn-view { padding: 6px 12px; border-radius: 6px; background: #f1f5f9; color: #0f172a; text-decoration: none; font-weight: 600; font-size: 12px; transition: 0.2s; }
        .btn-view:hover { background: #e2e8f0; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>

        <main class="content-body">
            <div style="margin-bottom: 25px;">
                <h2>User Management</h2>
                <p style="color: #64748b; font-size: 14px;">View and manage all registered campus users</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><p>Total Users</p><h3><?php echo $total_users; ?></h3></div>
                <div class="stat-card"><p>Students</p><h3><?php echo $student_count; ?></h3></div>
                <div class="stat-card"><p>Staff</p><h3><?php echo $staff_count; ?></h3></div>
                <div class="stat-card"><p>Security Guards</p><h3><?php echo $guard_count; ?></h3></div>
            </div>

            <div class="filter-bar">
                <form action="" method="GET" style="display: flex; flex: 1; gap: 15px;">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search name, ID or plate..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <select name="type" class="filter-select" onchange="this.form.submit()">
                        <option value="All">All Roles</option>
                        <option value="Student" <?php if($type_filter == 'Student') echo 'selected'; ?>>Student</option>
                        <option value="Staff" <?php if($type_filter == 'Staff') echo 'selected'; ?>>Staff</option>
                        <option value="Guard" <?php if($type_filter == 'Guard') echo 'selected'; ?>>Guard</option>
                    </select>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>ID Number</th>
                            <th>Role</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="../uploads/profile/<?php echo htmlspecialchars($user['profile_pic'], ENT_QUOTES); ?>" class="user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>'">
                                        <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['id_number']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($user['role_name']); ?>">
                                        <?php echo htmlspecialchars($user['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo ($user['plate_number'] !== 'N/A') ? htmlspecialchars($user['plate_number']) : '<span style="color:#cbd5e1">—</span>'; ?></td>
                                <td><span class="status-dot"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                <td>
                                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-view">View Details</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">
                                    <i class="fa-solid fa-user-slash" style="font-size: 32px; margin-bottom: 15px; display: block;"></i>
                                    No users found matching your search criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>