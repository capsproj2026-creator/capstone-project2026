<?php
include 'config.php';
$message = "";

// 1. Fetch departments dynamically from the SQL table
$departments = [];
$dept_result = mysqli_query($conn, "SELECT departmentcode, departmentname FROM departments ORDER BY departmentname ASC");
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
    }
}

// 2. Fetch vehicle types dynamically from the SQL table
$vehicle_types = [];
$vh_result = mysqli_query($conn, "SELECT id, vehicle_name FROM vehicles ORDER BY id ASC");
if ($vh_result) {
    while ($row = mysqli_fetch_assoc($vh_result)) {
        $vehicle_types[] = $row;
    }
}

// --- PHP BACKEND LOGIC ---
function sanitize_filename(string $filename): string {
    $base = basename($filename);
    return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $base);
}

function upload_image_file(string $field, string $destination_dir, string $prefix): string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return 'N/A';
    }

    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif'];
    $sanitized_name = sanitize_filename($_FILES[$field]['name']);
    $extension = strtolower(pathinfo($sanitized_name, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions, true)) {
        return 'N/A';
    }

    if (!file_exists($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }

    $target_name = time() . '_' . $prefix . '_' . $sanitized_name;
    $target_path = $destination_dir . $target_name;

    return move_uploaded_file($_FILES[$field]['tmp_name'], $target_path) ? $target_name : 'N/A';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_category = isset($_POST['reg_category']) && in_array($_POST['reg_category'], ['vehicle', 'personnel'], true) ? $_POST['reg_category'] : 'vehicle';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $id_number = trim($_POST['id_number'] ?? '');

    if ($fullname === '' || $email === '' || $phone_number === '' || $password === '' || $id_number === '') {
        $message = "<div class='alert error'>Please fill in all required fields.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert error'>Please enter a valid email address.</div>";
    } elseif (strlen($password) < 8) {
        $message = "<div class='alert error'>Password must be at least 8 characters long.</div>";
    } else {
        $fullname = mysqli_real_escape_string($conn, $fullname);
        $email = mysqli_real_escape_string($conn, $email);
        $phone_number = mysqli_real_escape_string($conn, $phone_number);
        $id_number = mysqli_real_escape_string($conn, $id_number);
        $password = password_hash($password, PASSWORD_DEFAULT);

        $department_code = null;
        $vehicle_id = null;
        $plate_number = 'N/A';
        $user_role_id = 3;

        if ($reg_category === 'personnel') {
            $system_role = isset($_POST['system_role']) && in_array($_POST['system_role'], ['Guard', 'Admin'], true) ? $_POST['system_role'] : 'Guard';
            $user_role_id = ($system_role === 'Admin') ? 1 : 2;
        } else {
            $user_type = isset($_POST['user_type']) && in_array($_POST['user_type'], ['Student', 'Staff'], true) ? $_POST['user_type'] : 'Student';
            $user_role_id = ($user_type === 'Student') ? 3 : 4;
            $department_code = !empty($_POST['department_code']) ? mysqli_real_escape_string($conn, $_POST['department_code']) : null;
            $vehicle_id = !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
            $plate_number = mysqli_real_escape_string($conn, $_POST['plate_number'] ?? 'N/A');
        }

        $base_upload = "uploads/";
        $profile_dir = $base_upload . "profile/";
        $license_dir = $base_upload . "documents/license/";
        $orcr_dir    = $base_upload . "documents/orcr/";

        $profile_filename = upload_image_file('profile_pic', $profile_dir, 'PROF');
        if ($profile_filename === 'N/A') {
            $profile_filename = 'default_avatar.png';
        }

        $license_filename = upload_image_file('driver_license', $license_dir, 'DL');
        $orcr_filename = upload_image_file('or_cr_photo', $orcr_dir, 'ORCR');

        $department_sql = $department_code !== null ? "'{$department_code}'" : 'NULL';
        $vehicle_sql = ($vehicle_id !== null && $vehicle_id > 0) ? intval($vehicle_id) : 'NULL';

        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR id_number = ?");
        $check_stmt->bind_param("ss", $email, $id_number);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $message = "<div class='alert error'>Email or ID Number already registered!</div>";
        } else {
            $insert_sql = "INSERT INTO users (fullname, email, phone_number, password, user_role_id, department_code, vehicle_id, id_number, plate_number, profile_pic, driver_license, or_cr_photo, status, Gate_access) 
                           VALUES ('{$fullname}', '{$email}', '{$phone_number}', '{$password}', {$user_role_id}, {$department_sql}, {$vehicle_sql}, '{$id_number}', '{$plate_number}', '{$profile_filename}', '{$license_filename}', '{$orcr_filename}', 'Pending', 'Pending')";

            if ($conn->query($insert_sql)) {
                $new_user_id = mysqli_insert_id($conn);
                $welcome_title = 'Registration Received';
                $welcome_msg = "Hello {$fullname}, your account is now pending review. You will be notified once access is granted.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, title, message, type) VALUES (?, ?, ?, ?, 'System')");
                $notif_stmt->bind_param("iiss", $new_user_id, $new_user_id, $welcome_title, $welcome_msg);
                $notif_stmt->execute();
                $notif_stmt->close();

                $message = "<div class='alert success'>Registration Successful! Please wait for Admin approval. <a href='login.php' style='font-weight:bold; color:inherit;'>Login here</a></div>";
            } else {
                $message = "<div class='alert error'>Database Error: " . htmlspecialchars($conn->error, ENT_QUOTES) . "</div>";
            }
        }

        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CSPC-VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; --border: #e2e8f0; --text: #0f172a; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 40px 20px; }
        .reg-card { background: #fff; width: 100%; max-width: 600px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        h2 { font-size: 24px; font-weight: 800; margin-bottom: 8px; text-align: center; }
        p.subtitle { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #475569; }
        input, select { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); font-family: inherit; font-size: 14px; }
        .role-selector { display: flex; gap: 10px; margin-bottom: 25px; background: #f1f5f9; padding: 5px; border-radius: 12px; }
        .role-opt { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .role-opt.active { background: #fff; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .btn-reg { width: 100%; background: var(--primary); color: #fff; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .file-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .upload-hint { font-size: 11px; color: #64748b; margin-top: 4px; display: block; }
    </style>
</head>
<body>

    <div class="reg-card">
        <h2>Create Account</h2>
        <p class="subtitle">Join the CSPC Vehicle Management System</p>

        <?php echo $message; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="reg_category" id="reg_category" value="vehicle">

            <label>I am a...</label>
            <div class="role-selector">
                <div class="role-opt active" onclick="switchMode('vehicle', this)">Vehicle Owner</div>
                <div class="role-opt" onclick="switchMode('personnel', this)">System Personnel</div>
            </div>

            <div class="form-group" style="text-align: center;">
                <div onclick="document.getElementById('profile_input').click()" style="cursor:pointer; width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid var(--border); position: relative;">
                    <i id="profile-icon" class="fa-solid fa-camera" style="color: #94a3b8; font-size: 24px;"></i>
                    <img id="profile-preview" style="width: 100%; height: 100%; object-fit: cover; display: none; position: absolute; top:0; left:0;">
                </div>
                <input type="file" name="profile_pic" id="profile_input" accept="image/*" onchange="previewImg(this, 'profile-preview')" style="display:none">
                <label style="margin-top: 8px;">Profile Picture</label>
            </div>

            <div class="file-grid">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" placeholder="Juan Dela Cruz" required>
                </div>
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" name="id_number" placeholder="2021-XXXX" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="file-grid">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" placeholder="09123456789" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <div id="vehicle-fields">
                <div class="file-grid">
                    <div class="form-group">
                        <label>User Type</label>
                        <select name="user_type" id="user_type" onchange="toggleDeptVisibility()">
                            <option value="Student">Student</option>
                            <option value="Staff">Faculty / Staff</option> </select>
                    </div>
                    <div class="form-group" id="dept-box">
                        <label>Department</label>
                        <select name="department_code" id="department_code">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['departmentcode']); ?>">
                                    <?php echo htmlspecialchars($dept['departmentname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Vehicle Classification</label>
                    <select name="vehicle_id" id="vehicle_id">
                        <option value="">-- Select Type --</option>
                        <?php foreach ($vehicle_types as $vh): ?>
                            <option value="<?php echo htmlspecialchars($vh['id']); ?>">
                                <?php echo htmlspecialchars($vh['vehicle_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" name="plate_number" id="plate_number" placeholder="ABC 1234">
                </div>

                <div class="file-grid">
                    <div class="form-group">
                        <label>Driver's License Photo</label>
                        <input type="file" name="driver_license" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>Vehicle OR/CR Photo</label>
                        <input type="file" name="or_cr_photo" accept="image/*">
                    </div>
                </div>
            </div>

            <div id="personnel-fields" style="display: none;">
                <div class="form-group">
                    <label>Access Level</label>
                    <select name="system_role">
                        <option value="Guard">Security Guard</option>
                        <option value="Admin">System Administrator</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-reg">Register Account</button>
        </form>

        <p style="text-align: center; margin-top: 25px; font-size: 14px; color: #64748b;">
            Already registered? <a href="login.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Login here</a>
        </p>
    </div>

    <script>
        function switchMode(mode, el) {
            document.querySelectorAll('.role-opt').forEach(opt => opt.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('reg_category').value = mode;
            document.getElementById('vehicle-fields').style.display = (mode === 'vehicle') ? 'block' : 'none';
            document.getElementById('personnel-fields').style.display = (mode === 'personnel') ? 'block' : 'none';
        }

        function toggleDeptVisibility() {
            const userType = document.getElementById('user_type').value;
            document.getElementById('dept-box').style.display = (userType === 'Student' || userType === 'Staff') ? 'block' : 'none';
        }

        function previewImg(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.getElementById(previewId);
                    img.src = e.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>