<?php
session_start();
include '../config.php';

// Security: Only Admin can perform declines
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $admin_id = intval($_SESSION['user_id']);

    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT user_role_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && ($row['user_role_id'] == 3 || $row['user_role_id'] == 4)) {
            $role_name = ($row['user_role_id'] == 3 ? 'Student' : 'Staff');

            $update_stmt = $conn->prepare("UPDATE users SET status = 'Denied' WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);

            if ($update_stmt->execute()) {
                $update_stmt->close();

                $notif_msg = "Your registration as $role_name has been declined. Please check your details and try again.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, title, message, is_read, created_at) VALUES (?, ?, 'Account Declined', ?, 0, NOW())");
                $notif_stmt->bind_param("iis", $user_id, $admin_id, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();

                header("Location: registrations.php?msg=Declined");
                exit();
            }
        } else {
            header("Location: registrations.php?error=InvalidRole");
            exit();
        }
    }
}
exit();
?>