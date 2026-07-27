<?php
session_start();
include 'config.php';
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT u.*, r.role_name FROM users u 
            JOIN user_roles r ON u.user_role_id = r.id 
            WHERE u.email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            
            if ($user['status'] !== 'Granted') {
                $error_msg = "Account is awaiting Admin approval.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['email'] = $user['email']; 
                $_SESSION['id_number'] = $user['id_number'];
                $_SESSION['phone'] = $user['phone_number'];
                $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'default_avatar.png';

                switch ($user['role_name']) {
                    case 'Admin': 
                        header("Location: admin/"); 
                        break;
                    case 'Guard': 
                        header("Location: guard/"); 
                        break;
                    case 'Student':
                    case 'Staff':
                        header("Location: users/"); 
                        break;
                    default: 
                        header("Location: login.php"); 
                        break;
                }
                exit();
            }
        } else { 
            $error_msg = "Incorrect password."; 
        }
    } else { 
        $error_msg = "No account found."; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
     <meta charset="UTF-8">
     <title>Login - Smart Campus VMS</title>     
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <style>
          @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
          body { height: 100vh; margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; }
          .login-card { background: #fff; width: 100%; max-width: 400px; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
          .form-group { margin-bottom: 20px; }
          .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 10px; }
          .input-container { position: relative; display: flex; align-items: center; }
          .input-container i.left-icon { position: absolute; left: 14px; color: #94a3b8; }
          .input-field { width: 100%; padding: 14px 45px 14px 44px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; outline: none; font-size: 14px; }
          .toggle-password { position: absolute; right: 14px; cursor: pointer; color: #94a3b8; }
          .btn-sign-in { width: 100%; background: #0f172a; color: white; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 10px; }
          .error-alert { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; text-align: center; border: 1px solid #fee2e2; }
          .footer-text { text-align: center; font-size: 14px; color: #64748b; margin-top: 25px; }
     </style>
</head>
<body>
     <div class="login-card">
          <h2 style="text-align:center; margin-bottom: 30px;">Sign In</h2>
          
          <?php if($error_msg) echo "<div class='error-alert'>$error_msg</div>"; ?>
          
          <form method="POST">
               <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-container">
                         <i class="fa-regular fa-envelope left-icon"></i>
                         <input type="email" name="email" class="input-field" placeholder="Enter your email" required>
                    </div>
               </div>
               <div class="form-group">
                    <label>Password</label>
                    <div class="input-container">
                         <i class="fa-solid fa-lock left-icon"></i>
                         <input type="password" id="password" name="password" class="input-field" placeholder="Enter your password" required>
                         <i class="fa-regular fa-eye toggle-password" onclick="toggleVisibility()"></i>
                    </div>
               </div>
               <button type="submit" class="btn-sign-in">Sign In</button>
          </form>
          <div class="footer-text">Don't have an account? <a href="register.php" style="color:#000; font-weight:700; text-decoration:none;">Register here</a></div>
     </div>
     <script>
          function toggleVisibility() {
               const p = document.getElementById('password');
               p.type = p.type === "password" ? "text" : "password";
          }
     </script>
</body>
</html>