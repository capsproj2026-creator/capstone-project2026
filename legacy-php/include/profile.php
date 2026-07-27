<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Standardize role to lowercase for easy checking
$user_role = strtolower($_SESSION['user_role'] ?? 'student');
$message = "";

switch ($user_role) {
    case 'admin':
        $dashboard_url = "../admin/"; 
        break;
    case 'guard':
        $dashboard_url = "../guard/";
        break;
    default:
        $dashboard_url = "../users/";
        break;
}

// Fetch the latest user data from DB
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// --- BACKEND LOGIC ---
function sanitize_filename(string $filename): string {
    $base = basename($filename);
    return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $base);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_SESSION['user_id']);

    // 1. Update Profile Info & Photo
    if (isset($_POST['update_profile'])) {
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        $update_fields = "fullname = '$fullname', phone_number = '$phone_number', email = '$email'";

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $safe_name = sanitize_filename($_FILES['profile_pic']['name']);
            $new_name = time() . "_PROF_" . $safe_name;
            $upload_dir = "../uploads/profiles/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $new_name)) {
                $new_name = mysqli_real_escape_string($conn, $new_name);
                $update_fields .= ", profile_pic = '$new_name'";
            }
        }

        $stmt = $conn->prepare("UPDATE users SET $update_fields WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['fullname'] = $fullname;
            $message = "Profile updated successfully!";
            header("Refresh:1");
            exit();
        }
        $stmt->close();
    }

    // 2. Change Password Logic
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (password_verify($current, $user_data['password'])) {
            if ($new === $confirm) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $user_id);
                if ($stmt->execute()) {
                    $message = "Password updated successfully!";
                }
                $stmt->close();
            } else {
                $message = "New passwords do not match.";
            }
        } else {
            $message = "Incorrect current password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Campus VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #3182ce; --bg: #f8fafc; --text: #1e293b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); padding-bottom: 50px; }
        
        .header { background: white; border-bottom: 1px solid var(--border); padding: 20px 5%; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .back-btn { text-decoration: none; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        
        .profile-container { max-width: 900px; margin: 30px auto; padding: 0 20px; display: grid; gap: 25px; }
        
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-title { font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 20px; display: block; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        .profile-header-grid { display: flex; align-items: center; gap: 30px; margin-bottom: 30px; }
        .avatar-upload { position: relative; width: 120px; height: 120px; }
        .avatar-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .file-input-label { position: absolute; bottom: 0; right: 0; background: var(--primary); color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 0.85rem; font-weight: 600; color: #64748b; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .field { width: 100%; padding: 12px 12px 12px 40px; border-radius: 10px; border: 1px solid var(--border); font-size: 0.95rem; outline: none; transition: 0.2s; }
        .field:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }
        
        .btn-save { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; cursor: pointer; margin-top: 15px; width: fit-content; transition: 0.2s; }
        .btn-save:hover { background: #2b6cb0; transform: translateY(-1px); }

        .doc-viewer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .doc-card { background: #f8fafc; border-radius: 12px; padding: 15px; border: 1px solid var(--border); }
        .doc-type { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; }
        .doc-frame { width: 100%; height: 180px; background: #e2e8f0; border-radius: 8px; overflow: hidden; }
        .doc-frame img { width: 100%; height: 100%; object-fit: cover; }

        @media (max-width: 768px) { .form-grid, .doc-viewer-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <header class="header">
        <a href="<?php echo $dashboard_url; ?>" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">MY ACCOUNT</h2>
    </header>

    <form class="profile-container" method="POST" enctype="multipart/form-data">
        
        <?php if($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <span class="card-title">Profile Information</span>
            <div class="profile-header-grid">
                <div class="avatar-upload">
                    <img src="../uploads/profile/<?php echo htmlspecialchars($user_data['profile_pic'], ENT_QUOTES); ?>" class="avatar-preview" id="preview">
                    <label for="profile_pic" class="file-input-label">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" id="profile_pic" name="profile_pic" hidden onchange="previewImage(this)">
                </div>
                <div>
                    <h2 style="font-size: 1.4rem;"><?php echo htmlspecialchars($user_data['fullname']); ?></h2>
                    <p style="color: #64748b; font-size: 0.9rem; margin-top: 5px;">
                        <span style="background: #e2e8f0; padding: 3px 10px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;">
                            <?php echo htmlspecialchars($user_role); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <div class="input-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="fullname" class="field" value="<?php echo htmlspecialchars($user_data['fullname'], ENT_QUOTES); ?>" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" class="field" value="<?php echo htmlspecialchars($user_data['email'], ENT_QUOTES); ?>" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-phone"></i>
                        <input type="text" name="phone_number" class="field" value="<?php echo htmlspecialchars($user_data['phone_number'], ENT_QUOTES); ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label>Identification (ID) Number</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" class="field" value="<?php echo htmlspecialchars($user_data['id_number'], ENT_QUOTES); ?>" disabled style="background: #f1f5f9;">
                    </div>
                </div>
            </div>
            <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
        </div>

        <div class="card">
            <span class="card-title">Security & Password</span>
            <div class="form-grid">
                <div class="input-group">
                    <label>Current Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-key"></i>
                        <input type="password" name="current_password" class="field" placeholder="Enter current password">
                    </div>
                </div>
                <div class="input-group"></div> <div class="input-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="new_password" class="field" placeholder="Minimum 8 characters">
                    </div>
                </div>
                <div class="input-group">
                    <label>Confirm New Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="confirm_password" class="field" placeholder="Repeat new password">
                    </div>
                </div>
            </div>
            <button type="submit" name="change_password" class="btn-save">Update Password</button>
        </div>

        <?php if ($user_role !== 'admin' && $user_role !== 'guard'): ?>
        <div class="card">
            <span class="card-title">Verified Documents</span>
            <div class="doc-viewer-grid">
                <div class="doc-card">
                    <p class="doc-type">Driver's License Photo</p>
                    <div class="doc-frame">
                        <img src="../uploads/documents/license/<?php echo htmlspecialchars($user_data['driver_license'], ENT_QUOTES); ?>" onerror="this.src='https://placehold.co/400x250?text=No+License+Found'">
                    </div>
                </div>
                <div class="doc-card">
                    <p class="doc-type">Vehicle OR/CR Photo</p>
                    <div class="doc-frame">
                        <img src="../uploads/documents/orcr/<?php echo htmlspecialchars($user_data['or_cr_photo'], ENT_QUOTES); ?>" onerror="this.src='https://placehold.co/400x250?text=No+ORCR+Found'">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </form>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>