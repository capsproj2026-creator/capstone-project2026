<?php
// admin/settings_general.php
include '../config.php';

// --- START PROCESS LOGIC ---
if (isset($_POST['update_all'])) {
    // 1. Update General Informations Table
    if (isset($_POST['gen_info'])) {
        foreach ($_POST['gen_info'] as $id => $desc) {
            $stmt = $conn->prepare("UPDATE general_informations SET description = ? WHERE id = ?");
            $stmt->bind_param("si", $desc, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 2. Update Parking Rules Table
    if (isset($_POST['rules'])) {
        foreach ($_POST['rules'] as $id => $desc) {
            $stmt = $conn->prepare("UPDATE parking_rules SET description = ? WHERE id = ?");
            $stmt->bind_param("si", $desc, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect to show success
    echo "<script>window.location.href='settings.php?tab=general&status=success';</script>";
    exit();
}
// --- END PROCESS LOGIC ---

// Fetch dynamic data for the forms
$gen_info = $conn->query("SELECT * FROM general_informations");
$parking_rules = $conn->query("SELECT * FROM parking_rules");
?>

<style>
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }

    /* Textarea "View All" Styling */
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        font-family: inherit;
        font-size: 14px;
        color: #334155;
        resize: none; /* Disables manual drag resize */
        overflow: hidden; /* Hides internal scrollbar */
        margin-bottom: 12px;
    }

    .section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 5px; }
    .section-subtitle { color: #64748b; font-size: 14px; margin-bottom: 25px; }

    .btn-save {
        background: #0f172a;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        float: right;
    }
</style>

<form action="" method="POST">
    <div class="settings-card">
        <h3 class="section-title">System Information</h3>
        <p class="section-subtitle">Basic system configuration and information</p>
        
        <div class="form-row">
            <div class="form-group">
                <label>Campus Name</label>
                <input type="text" class="form-control" name="campus_name" value="Smart Campus University">
            </div>
            <div class="form-group">
                <label>Timezone</label>
                <select class="form-control" name="timezone">
                    <option>Pacific Standard Time</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Contact Email</label>
                <input type="email" class="form-control" name="contact_email" value="security@campus.edu">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="text" class="form-control" name="contact_phone" value="+1 (555) 123-4567">
            </div>
        </div>
    </div>

    <div class="settings-card">
        <h3 class="section-title">Parking Guidelines & Policies</h3>
        <p class="section-subtitle">Edit the descriptions stored in your database</p>

        <div class="form-group">
            <label>General Information List</label>
            <?php while($row = $gen_info->fetch_assoc()): ?>
                <textarea name="gen_info[<?= $row['id'] ?>]" class="form-control auto-expand"><?= htmlspecialchars($row['description']) ?></textarea>
            <?php endwhile; ?>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label>Parking Rules</label>
            <?php while($row = $parking_rules->fetch_assoc()): ?>
                <textarea name="rules[<?= $row['id'] ?>]" class="form-control auto-expand"><?= htmlspecialchars($row['description']) ?></textarea>
            <?php endwhile; ?>
        </div>

        <div style="margin-top: 30px; height: 50px;">
            <button type="submit" name="update_all" class="btn-save">
                <i class="fa-solid fa-floppy-disk"></i> Save All Changes
            </button>
        </div>
    </div>
</form>

<script>
    // Script to automatically expand textareas to "View All" content
    document.querySelectorAll('.auto-expand').forEach(textarea => {
        // Adjust height on load
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';

        // Adjust height on input
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
</script>