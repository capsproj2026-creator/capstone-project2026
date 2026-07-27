<?php
/**
 * CSPC-VMS Fully Integrated Role-Based Portal
 * Features: Dynamic Routing based on Admin, Guard, and User roles.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SESSION DATA & ROLE DEFINITION
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['user_name'] ?? 'Guest';
// Standardizing role to lowercase for reliable comparison
$user_role = strtolower($_SESSION['user_role'] ?? 'user'); 
$profile_pic = $_SESSION['profile_pic'] ?? 'default_avatar.png';
$email = $_SESSION['email'] ?? 'N/A';
$phone = $_SESSION['phone'] ?? 'Not Set';
$id_number = $_SESSION['id_number'] ?? 'N/A';

/**
 * FUNCTION: getAuthorizedRoutes
 * Refined routes to match login.php logic for Student and Staff roles.
 */
function getAuthorizedRoutes($role) {
    $routes = [
        // Public/Common access for all logged-in users
        ['label' => 'Dashboard', 'path' => 'index.php', 'icon' => 'fa-solid fa-house-chimney', 'access' => ['admin', 'guard', 'student', 'staff']],
        
        // Administrative & Security specific routes
        ['label' => 'Registrations', 'path' => 'registrations.php', 'icon' => 'fa-solid fa-user-plus', 'access' => ['admin']],
        ['label' => 'RFID Assignment', 'path' => 'rfid-assignment.php', 'icon' => 'fa-solid fa-id-card-clip', 'access' => ['admin']],
        ['label' => 'User Management', 'path' => 'user-management.php', 'icon' => 'fa-solid fa-users-gear', 'access' => ['admin']],
        ['label' => 'Violations', 'path' => 'violations.php', 'icon' => 'fa-solid fa-triangle-exclamation', 'access' => ['admin', 'guard']],
        ['label' => 'Access Logs', 'path' => 'access-logs.php', 'icon' => 'fa-solid fa-clipboard-list', 'access' => ['admin', 'guard']],
        ['label' => 'Parking', 'path' => 'parking.php', 'icon' => 'fa-solid fa-car-side', 'access' => ['admin', 'guard']],
        ['label' => 'Live Cameras', 'path' => 'live-cameras.php', 'icon' => 'fa-solid fa-video', 'access' => ['admin', 'guard']],
        ['label' => 'Reports', 'path' => 'reports.php', 'icon' => 'fa-solid fa-chart-line', 'access' => ['admin']],
        ['label' => 'Settings', 'path' => 'settings.php', 'icon' => 'fa-solid fa-sliders', 'access' => ['admin']],
        
        // Guard Specific (Replaced generic gears with relevant icons)
        ['label' => 'AI Parking', 'path' => 'ai_parking.php', 'icon' => 'fa-solid fa-microchip', 'access' => ['guard']],
        ['label' => 'User Monitor', 'path' => 'monitor.php', 'icon' => 'fa-solid fa-desktop', 'access' => ['guard']],
        ['label' => 'Live Gate Monitor', 'path' => 'gate.php', 'icon' => 'fa-solid fa-door-open', 'access' => ['guard']],
        
        // Staffs & Students
        ['label' => 'Notifications', 'path' => 'notifications.php', 'icon' => 'fa-solid fa-bell', 'access' => ['student', 'staff']],
        ['label' => 'Entry & Exit History', 'path' => 'entry_exit_history.php', 'icon' => 'fa-solid fa-clock-rotate-left', 'access' => ['student', 'staff']],
        ['label' => 'User Parking', 'path' => 'userparking.php', 'icon' => 'fa-solid fa-car-side', 'access' => ['student', 'staff']]
    ];

    return array_filter($routes, function($route) use ($role) {
        return in_array($role, $route['access']);
    });
}

$sidebar_items = getAuthorizedRoutes($user_role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --nav-h: 70px;
            --side-w: 260px;
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg-body: #f8fafc;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); overflow-x: hidden; }

        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-h);
            background: #fff; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; z-index: 1001;
        }

        .nav-left { display: flex; align-items: center; gap: 15px; }
        .menu-toggle {
            background: #2563eb; border: none; width: 44px; height: 44px; 
            border-radius: 8px; cursor: pointer; color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
            transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .menu-toggle:hover {
            background: #1d4ed8; box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .brand h2 { font-size: 18px; font-weight: 800; letter-spacing: -0.5px; }
        .brand span { color: var(--primary); }

        /* User Profile Controls */
        .user-pill {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 6px 12px; border-radius: 12px; transition: var(--transition);
        }
        .user-pill:hover { background: #f1f5f9; }
        .user-info { text-align: right; line-height: 1.2; display: none; }
        @media (min-width: 640px) { .user-info { display: block; } }
        .u-name { display: block; font-size: 14px; font-weight: 600; }
        .u-role { display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background: #eee; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--border); }

        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.3);
            backdrop-filter: blur(4px); opacity: 0; pointer-events: none;
            transition: var(--transition); z-index: 999;
        }
        body.mobile-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        /* Sidebar */
        .sidebar {
            position: fixed; top: var(--nav-h); left: 0;
            width: var(--side-w); height: calc(100vh - var(--nav-h));
            background: #fff; border-right: 1px solid var(--border);
            z-index: 1000; transition: var(--transition);
            display: flex; flex-direction: column;
        }
        
        /* Desktop Hide Logic */
        body.sidebar-hidden .sidebar { 
            transform: translateX(-100%); 
        }
        
        /* Mobile Logic */
        @media (max-width: 992px) {
            .sidebar { 
                transform: translateX(-100%); 
            }
            body.mobile-open .sidebar { 
                transform: translateX(0); 
            }
        }

        .nav-menu { padding: 20px 12px; flex-grow: 1; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            text-decoration: none; color: var(--text-muted); font-size: 14px;
            font-weight: 500; border-radius: 10px; transition: 0.2s; margin-bottom: 4px;
        }
        .nav-link i { width: 20px; font-size: 18px; text-align: center; }
        .nav-link:hover { background: #f1f5f9; color: var(--text-main); }
        .nav-link.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }

        /* Dropdown Menu */
        .profile-dropdown {
            position: absolute; top: 75px; right: 24px; width: 280px;
            background: #fff; border: 1px solid var(--border); border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); 
            display: none; z-index: 1002; overflow: hidden;
        }
        .profile-dropdown.show { display: block; animation: slideDown 0.2s ease-out; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

        .dd-header { padding: 20px; background: #fcfcfd; border-bottom: 1px solid var(--border); }
        .dd-body { padding: 15px 20px; }
        .dd-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 13px; color: var(--text-muted); }
        .dd-item i { color: #94a3b8; width: 16px; }
        .dd-footer { padding: 8px; border-top: 1px solid var(--border); background: #f8fafc; }
        .dd-link {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            text-decoration: none; color: var(--text-main); font-size: 14px; border-radius: 8px;
        }
        .dd-link:hover { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .dd-link.logout { color: #ef4444; font-weight: 600; }

        /* Main Layout Adjustment */
        .main-content { 
            margin-top: var(--nav-h); 
            margin-left: var(--side-w); 
            padding: 20px; 
            transition: var(--transition); 
        }
        body.sidebar-hidden .main-content { 
            margin-left: 0; 
        }
        
        @media (max-width: 992px) {
            .main-content { 
                margin-left: 0; 
                padding: 20px; 
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>

<!-- Top Navigation -->
<nav class="top-navbar">
    <div class="nav-left">
        <button class="menu-toggle" id="toggleBtn"><i class="fa-solid fa-bars-staggered"></i></button>
        <h2><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'User'); ?> Portal</h2>
    </div>

    <div class="nav-right">
        <div class="user-pill" id="userPill">
            <div class="user-info">
                <span class="u-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="u-role"><?= htmlspecialchars($user_role) ?></span>
            </div>
            <img src="../uploads/profile/<?= $profile_pic ?>" class="avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=2563eb&color=fff'">
        </div>

        <!-- Profile Dropdown -->
        <div class="profile-dropdown" id="dropdown">
            <div class="dd-header">
                <div style="font-weight: 700; font-size: 15px;"><?= htmlspecialchars($user_name) ?></div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;"><?= $user_role ?> Account</div>
            </div>
            <div class="dd-body">
                <div class="dd-item"><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($email) ?></div>
                <div class="dd-item"><i class="fa-solid fa-hashtag"></i> ID: <?= htmlspecialchars($id_number) ?></div>
            </div>
            <div class="dd-footer">
                <a href="../include/profile.php" class="dd-link"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
                <a href="../logout.php" class="dd-link logout"><i class="fa-solid fa-power-off"></i> Sign Out</a>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar with Role-Based Links -->
<aside class="sidebar" id="sidebar">
    <nav class="nav-menu">
        <?php foreach ($sidebar_items as $item): ?>
            <a href="<?= $item['path'] ?>" class="nav-link <?= ($current_page == $item['path']) ? 'active' : '' ?>">
                <i class="fa-solid <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<main class="main-content">
</main>

<script>
    const toggleBtn = document.getElementById('toggleBtn');
    const userPill = document.getElementById('userPill');
    const dropdown = document.getElementById('dropdown');
    const overlay = document.getElementById('overlay');
    const body = document.body;

    // Sidebar Toggling - Toggle sidebar visibility on any screen size
    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (window.innerWidth > 992) {
            body.classList.toggle('sidebar-hidden');
        } else {
            body.classList.toggle('mobile-open');
        }
    });
    
    // Close sidebar on navigation link click (mobile)
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                body.classList.remove('mobile-open');
            }
        });
    });

    // Profile Dropdown
    userPill.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    // Interaction Cleanup
    window.addEventListener('click', (e) => {
        // Close dropdown when clicking outside
        if (!dropdown.contains(e.target) && !userPill.contains(e.target)) {
            dropdown.classList.remove('show');
        }
        // Mobile: Close sidebar when clicking overlay
        if (e.target === overlay) {
            body.classList.remove('mobile-open');
        }
    });

    // Handle Resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            body.classList.remove('mobile-open');
        }
    });
</script>

</body>
</html>