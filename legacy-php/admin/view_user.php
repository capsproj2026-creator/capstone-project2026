<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
include '../config.php';

if (!isset($_GET['id'])) {
    header("Location: registrations.php");
    exit();
}

$target_id = intval($_GET['id']);

$sql = "SELECT u.*, r.role_name 
        FROM users u 
        JOIN user_roles r ON u.user_role_id = r.id 
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $target_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$status_class = in_array($user['status'], ['Pending', 'Granted', 'Denied'], true) ? $user['status'] : 'Pending';
$vehicle_roles = ['Student', 'Staff'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details | <?php echo htmlspecialchars($user['fullname']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; }
        .profile-section { display: grid; grid-template-columns: 150px 1fr; gap: 30px; margin-bottom: 40px; }
        .profile-img { width: 150px; height: 150px; border-radius: 12px; object-fit: cover; border: 4px solid #f1f5f9; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item label { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .info-item span { font-size: 16px; color: #0f172a; font-weight: 600; }
        .doc-section { margin-top: 30px; }
        .doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .doc-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; text-align: center; }
        .doc-card img { width: 100%; height: 200px; object-fit: contain; background: #f8fafc; border-radius: 8px; cursor: pointer; }
        .doc-label { font-size: 13px; font-weight: 700; margin-top: 10px; color: #475569; }
        .btn-back { text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; }
        .status-pill { padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .Pending { background: #fff7ed; color: #ea580c; }
        .Granted { background: #f0fdf4; color: #16a34a; }
        .Denied { background: #fef2f2; color: #dc2626; }
        .role-notice { padding: 15px; background: #f1f5f9; border-radius: 8px; color: #64748b; font-size: 13px; text-align: center; font-style: italic; border: 1px dashed #cbd5e1; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <a href="registrations.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
            <h1 style="margin-top:10px;"><?php echo htmlspecialchars($user['fullname']); ?></h1>
        </div>
        <span class="status-pill <?php echo htmlspecialchars($status_class, ENT_QUOTES); ?>"><?php echo htmlspecialchars($user['status']); ?></span>
    </div>

    <div class="profile-section">
        <img src="../uploads/profile/<?php echo htmlspecialchars($user['profile_pic'], ENT_QUOTES); ?>" class="profile-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&size=150'">
        
        <div class="info-grid">
            <div class="info-item"><label>User Role/Type</label><span><?php echo htmlspecialchars($user['role_name']); ?></span></div>
            
            <div class="info-item"><label>ID Number</label><span><?php echo htmlspecialchars($user['id_number']); ?></span></div>
            <div class="info-item"><label>Email</label><span><?php echo htmlspecialchars($user['email']); ?></span></div>
            <div class="info-item"><label>Phone</label><span><?php echo htmlspecialchars($user['phone_number']); ?></span></div>
            
            <?php if(in_array($user['role_name'], $vehicle_roles)): ?>
                <div class="info-item"><label>Plate Number</label><span><?php echo htmlspecialchars($user['plate_number']); ?></span></div>
            <?php endif; ?>
            
            <div class="info-item"><label>Account Status</label><span><?php echo htmlspecialchars($user['status']); ?></span></div>
        </div>
    </div>

    <div class="doc-section">
        <h3 style="border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Submitted Documents</h3>
        
        <?php if(in_array($user['role_name'], $vehicle_roles)): ?>
            <div class="doc-grid">
                <div class="doc-card">
                    <img src="../uploads/documents/license/<?php echo htmlspecialchars($user['driver_license'], ENT_QUOTES); ?>" onclick="window.open(this.src)" onerror="this.src='https://placehold.co/400x250?text=No+License+Image'">
                    <div class="doc-label">Driver's License Photo</div>
                </div>
                <div class="doc-card">
                    <img src="../uploads/documents/orcr/<?php echo htmlspecialchars($user['or_cr_photo'], ENT_QUOTES); ?>" onclick="window.open(this.src)" onerror="this.src='https://placehold.co/400x250?text=No+ORCR+Image'">
                    <div class="doc-label">Vehicle OR/CR Photo</div>
                </div>
            </div>
        <?php else: ?>
            <div class="role-notice">
                <i class="fa-solid fa-info-circle"></i> This user is registered as <b><?php echo htmlspecialchars($user['role_name']); ?></b>. No vehicle documents are required for this role.
            </div>
        <?php endif; ?>
    </div>

    <?php if($user['status'] == 'Pending'): ?>
    <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: flex-end;">
        <a href="decline.php?id=<?php echo $user['id']; ?>" style="background: #dc2626; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;">Decline Request</a>
        <a href="approve.php?id=<?php echo $user['id']; ?>" style="background: #16a34a; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;">Approve Request</a>
    </div>
    <?php endif; ?>
</div>

</body>
</html>