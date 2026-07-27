<?php
session_start();
include '../config.php';

// 1. Security Check: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// 2. Active Tab Logic
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'Pending';

// 3. Handle Grant/Deny Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    
    if (isset($_POST['action_grant'])) {
        $sql = "UPDATE users SET gate_access = 'Access', status = 'Access' WHERE id = ?";
        $upd = $conn->prepare($sql);
        $upd->bind_param("i", $user_id);
        $upd->execute();
        $upd->close();
    } elseif (isset($_POST['action_deny'])) {
        $sql = "UPDATE users SET gate_access = 'Denied' WHERE id = ?";
        $upd = $conn->prepare($sql);
        $upd->bind_param("i", $user_id);
        $upd->execute();
        $upd->close();
    }
    echo "<script>window.location.href='rfid-assignment.php?tab=$current_tab';</script>";
    exit();
}

// 4. Fetch Users - Filtered for Students (1) and Staff (2) ONLY
if ($current_tab == 'Pending') {
    $sql = "SELECT u.*, d.departmentname 
            FROM users u 
            LEFT JOIN departments d ON u.department_code = d.departmentcode 
            WHERE (u.user_role_id = 1 OR u.user_role_id = 2) 
            AND (u.gate_access IS NULL OR u.gate_access = '' OR u.gate_access = 'Pending')
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT u.*, d.departmentname 
            FROM users u 
            LEFT JOIN departments d ON u.department_code = d.departmentcode 
            WHERE (u.user_role_id = 1 OR u.user_role_id = 2) 
            AND u.gate_access = ? 
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $current_tab);
}

$stmt->execute();
$users_list = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Access Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0f172a; --border: #e2e8f0; --bg: #f8fafc; --secondary: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; display: flex; width: 100vw; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 30px; max-width: 1100px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .tab-link { text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: var(--secondary); font-size: 14px; }
        .tab-link.active { background: var(--primary); color: #fff; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f1f5f9; color: var(--secondary); font-size: 11px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 14px; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-Pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-Access { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .badge-Denied { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .btn-view { border: 1px solid var(--border); background: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: #fff; width: 550px; border-radius: 16px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        
        .profile-section { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .profile-img-circle { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); }

        .img-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .img-box { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #f1f5f9; text-align: center; }
        .img-box img { width: 100%; height: 160px; object-fit: cover; cursor: pointer; }
        .img-box p { font-size: 11px; font-weight: 700; padding: 8px; margin: 0; color: var(--secondary); text-transform: uppercase; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>
        
        <main class="content-body">
            <div class="card">
                <h2 style="margin-bottom: 20px; font-weight: 800;">Gate Access Management</h2>

                <div class="tabs">
                    <a href="?tab=Pending" class="tab-link <?= $current_tab == 'Pending' ? 'active' : '' ?>">Pending Requests</a>
                    <a href="?tab=Access" class="tab-link <?= $current_tab == 'Access' ? 'active' : '' ?>">Access</a>
                    <a href="?tab=Denied" class="tab-link <?= $current_tab == 'Denied' ? 'active' : '' ?>">Denied</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>User & ID Number</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $users_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="../uploads/profile/<?= (!empty($row['photo'])) ? htmlspecialchars($row['photo']) : 'default.png' ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 700;"><?= htmlspecialchars($row['fullname']) ?></div>
                                        <div style="font-size: 12px; color: var(--secondary);">ID: <?= htmlspecialchars($row['id_number']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['departmentname'] ?? 'N/A') ?></td>
                            <td>
                                <?php $status_label = (!empty($row['gate_access'])) ? $row['gate_access'] : 'Pending'; ?>
                                <span class="badge badge-<?= $status_label ?>"><?= $status_label ?></span>
                            </td>
                            <td style="text-align:right;">
                                <button class="btn-view" onclick='openReview(<?= json_encode($row) ?>)'>View Details</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="profile-section">
                <img id="m_profile_img" src="" class="profile-img-circle" onerror="this.src='../uploads/profile/default.png'">
                <div>
                    <h3 id="m_name" style="margin:0;"></h3>
                    <p id="m_id" style="color:var(--secondary); font-size:14px; margin:2px 0 0 0;"></p>
                </div>
            </div>
            
            <div class="img-grid">
                <div class="img-box">
                    <img id="img_license" src="" onerror="this.src='../uploads/documents/default_id.png'">
                    <p>Driver's License</p>
                </div>
                <div class="img-box">
                    <img id="img_orcr" src="" onerror="this.src='../uploads/documents/default_car.png'">
                    <p>OR / CR Photo</p>
                </div>
            </div>

            <form method="POST" class="modal-footer">
                <input type="hidden" name="user_id" id="hidden_uid">
                <button type="button" class="btn-view" onclick="closeModal()">Close</button>
                
                <?php if($current_tab !== 'Denied'): ?>
                    <button type="submit" name="action_deny" style="background:#ef4444; color:#fff; border:none; padding:10px 18px; border-radius:6px; font-weight:600; cursor:pointer;">Deny</button>
                <?php endif; ?>
                
                <?php if($current_tab !== 'Access'): ?>
                    <button type="submit" name="action_grant" style="background:var(--primary); color:#fff; border:none; padding:10px 18px; border-radius:6px; font-weight:600; cursor:pointer;">Grant Access</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

<script>
    function openReview(data) {
        document.getElementById('m_name').innerText = data.fullname;
        document.getElementById('m_id').innerText = "ID Number: " + data.id_number;
        document.getElementById('hidden_uid').value = data.id;

        const profilePath = "../uploads/profile/";
        const licensePath = "../uploads/documents/license/";
        const orcrPath = "../uploads/documents/orcr/";
        
        // Show actual profile picture if available
        document.getElementById('m_profile_img').src = data.photo ? profilePath + data.photo : profilePath + "default.png";

        // Show documents
        document.getElementById('img_license').src = data.driver_license ? licensePath + data.driver_license : "../uploads/documents/default_id.png";
        document.getElementById('img_orcr').src = data.or_cr_photo ? orcrPath + data.or_cr_photo : "../uploads/documents/default_car.png";

        document.getElementById('reviewModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target == document.getElementById('reviewModal')) closeModal();
    }
</script>

</body>
</html>