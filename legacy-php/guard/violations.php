<?php
session_start();
include '../config.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Guard') {
    header("Location: ../login.php");
    exit();
}

// 2. Handle Violation Submission
if (isset($_POST['submit_violation'])) {
    $plate_num = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $type = mysqli_real_escape_string($conn, $_POST['violation_type']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $g_id = $_SESSION['user_id'];

    // Find User by Plate Number
    $userQuery = $conn->query("SELECT id, fullname, strike_count FROM users WHERE plate_number = '$plate_num' LIMIT 1");
    
    if ($user = $userQuery->fetch_assoc()) {
        $u_id = $user['id'];
        $new_strikes = $user['strike_count'] + 1;
        $status = ($new_strikes >= 3) ? 'Suspended' : 'Active';
        
        $notif_title = "Violation Recorded: $type";
        $notif_msg = "Your vehicle ($plate_num) has been cited. Total strikes: $new_strikes/3.";

        mysqli_begin_transaction($conn);
        try {
            // Log violation
            $stmt1 = $conn->prepare("INSERT INTO violations_log (user_id, violator_name, plate_number, violation_type, description, guard_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("issssi", $u_id, $user['fullname'], $plate_num, $type, $desc, $g_id);
            $stmt1->execute();
            
            // Update User Strike/Status
            $conn->query("UPDATE users SET strike_count = $new_strikes, status = '$status' WHERE id = '$u_id'");

            // AUTOMATIC NOTIFY: Pushes to user dashboard
            // type: 'Violation', is_read: 0 (matches your SQL schema)
            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, sender_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 'Violation', 0)");
            $stmt2->bind_param("iiss", $u_id, $g_id, $notif_title, $notif_msg);
            $stmt2->execute();
            
            mysqli_commit($conn);
            header("Location: violations.php?success=1");
            exit();
        } catch (Exception $e) { 
            mysqli_rollback($conn); 
        }
    } else {
        // Optional: Error message if plate not found
        header("Location: violations.php?error=plate_not_found");
        exit();
    }
}

// 3. CRITICAL: Fetch logs OUTSIDE the submit block to avoid "Undefined variable $logs"
$logs = $conn->query("SELECT * FROM violations_log ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Violation Records | Guard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #3b82f6; --bg: #f8fafc; --text: #1e293b; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .card { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 16px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 16px; border-bottom: 1px solid var(--border); font-size: 14px; }
        code { background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 700; color: #334155; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 450px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include('../include/nav.php'); ?>
        <main class="content-body">
            <div class="header">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700;">Violation Records</h1>
                    <p style="color: #64748b;">Enter Plate Number for instant citation</p>
                </div>
                <button class="btn-add" onclick="toggleModal(true)"><i data-lucide="plus-circle"></i> Log Violation</button>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Plate No.</th>
                            <th>Violator Name</th>
                            <th>Violation Type</th>
                            <th>Date Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($logs && $logs->num_rows > 0): ?>
                            <?php while($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($row['plate_number']) ?></code></td>
                                <td><strong><?= htmlspecialchars($row['violator_name']) ?></strong></td>
                                <td style="color: #ef4444; font-weight: 600;"><?= $row['violation_type'] ?></td>
                                <td><?= date('M d, Y | h:i A', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="vModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Record Violation</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Vehicle Plate Number</label>
                    <input type="text" name="plate_number" placeholder="Enter Plate Number" required>
                </div>
                <div class="form-group">
                    <label>Violation Type</label>
                    <select name="violation_type" required>
                        <option value="">-- Select Category --</option>
                        <option value="Wrong Parking">Wrong Parking</option>
                        <option value="Over Speeding">Over Speeding</option>
                        <option value="Use of Motorcycle Mufflers">Use of Motorcycle Mufflers</option>
                        <option value="Explicit disrespect">Explicit disrespect</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Context of incident..." required></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="toggleModal(false)" style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">Cancel</button>
                    <button type="submit" name="submit_violation" style="flex: 2; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: bold;">Submit & Notify User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function toggleModal(show) {
            document.getElementById('vModal').style.display = show ? 'flex' : 'none';
        }
    </script>
</body>
</html>