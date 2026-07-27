<?php
// admin/include/settings_violations.php
include '../config.php';

/**
 * 1. DATABASE LOGIC (Top of file)
 */

// HANDLE STATUS TOGGLE (Active/Inactive)
if (isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    $id = intval($_GET['toggle_id']);
    $current = strtolower($_GET['current_status']); // Ensure comparison is case-insensitive
    
    // Toggle logic: exactly 'active' or 'inactive' to match the CSS check
    $new_status = ($current == 'active') ? 'inactive' : 'active';

    $stmt = $conn->prepare("UPDATE violation_types SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if($stmt->execute()){
        // Redirect back to clean URL
        echo "<script>window.location.href='settings.php?tab=violations';</script>";
        exit();
    }
    $stmt->close();
}

// HANDLE ADD OR UPDATE
if (isset($_POST['save_violation'])) {
    $name = trim(strip_tags($_POST['violation_name']));
    $desc = trim(strip_tags($_POST['description']));
    $id = intval($_POST['violation_id']); 

    if (!empty($id)) {
        // UPDATE EXISTING
        $stmt = $conn->prepare("UPDATE violation_types SET violation_name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $desc, $id);
    } else {
        // ADD NEW (Default status is lowercase 'active')
        $stmt = $conn->prepare("INSERT INTO violation_types (violation_name, description, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $name, $desc);
    }
    
    $stmt->execute();
    $stmt->close();
    echo "<script>window.location.href='settings.php?tab=violations';</script>";
    exit();
}

// Fetch violations
$violation_types = $conn->query("SELECT * FROM violation_types ORDER BY id ASC");
?>

<style>
    /* Layout */
    .settings-card { background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .btn-add { background: #0f172a; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; }
    .violation-item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .violation-info h4 { margin: 0 0 5px 0; font-size: 16px; color: #0f172a; font-weight: 700; }
    .violation-info p { margin: 0; font-size: 14px; color: #64748b; }
    .violation-actions { display: flex; align-items: center; gap: 12px; }

    /* TOGGLE SLIDER - FUNCTIONAL COLORS */
    .switch { position: relative; width: 44px; height: 24px; display: inline-block; }
    .switch input { opacity: 0; width: 0; height: 0; }
    
    /* Default: Gray (Inactive / Unchecked) */
    .slider { 
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; 
        background-color: #cbd5e1; /* Gray */
        transition: .4s; border-radius: 24px; 
    }
    .slider:before { 
        position: absolute; content: ""; height: 18px; width: 18px; 
        left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; 
    }
    
    /* Green: (Active / Checked) */
    .switch input:checked + .slider { 
        background-color: #22c55e !important; /* Green */
    }
    .switch input:checked + .slider:before { 
        transform: translateX(20px); 
    }

    .btn-action { padding: 8px 16px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; text-decoration: none; }
    
    /* Modal */
    .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .modal-content { background: #fff; margin: 10% auto; padding: 30px; border-radius: 12px; width: 450px; }
    .form-control { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
</style>

<div class="settings-card">
    <div class="section-header">
        <div>
            <h3 style="margin:0;">Violation Types & Penalties</h3>
            <p style="color:#64748b; font-size:14px; margin-top:5px;">Manage rules and set active status</p>
        </div>
        <button class="btn-add" onclick="openViolationModal('', '', '')">+ Add Type</button>
    </div>

    <div class="violation-list">
        <?php while($row = $violation_types->fetch_assoc()): ?>
            <div class="violation-item">
                <div class="violation-info">
                    <h4><?= htmlspecialchars($row['violation_name']) ?></h4>
                    <p><?= htmlspecialchars($row['description']) ?></p>
                </div>
                <div class="violation-actions">
                    <label class="switch">
                        <input type="checkbox" <?= (strtolower($row['status']) == 'active') ? 'checked' : '' ?> 
                               onchange="window.location.href='settings.php?tab=violations&toggle_id=<?= $row['id'] ?>&current_status=<?= $row['status'] ?>'">
                        <span class="slider"></span>
                    </label>
                    
                    <button class="btn-action" onclick="openViolationModal(
                        '<?= $row['id'] ?>', 
                        '<?= addslashes($row['violation_name']) ?>', 
                        '<?= addslashes($row['description']) ?>'
                    )">Edit</button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<div id="violationModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="margin-top:0;">Add Violation</h3>
        <form action="" method="POST">
            <input type="hidden" name="violation_id" id="form_violation_id">
            
            <label style="font-size:12px; font-weight:700;">Violation Name</label>
            <input type="text" name="violation_name" id="form_violation_name" class="form-control" required>
            
            <label style="font-size:12px; font-weight:700;">Description</label>
            <textarea name="description" id="form_description" class="form-control" rows="4" required></textarea>
            
            <div style="display:flex; gap:10px; margin-top:15px;">
                <button type="submit" name="save_violation" class="btn-add" style="flex:1;">Save Violation</button>
                <button type="button" class="btn-action" style="flex:1;" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openViolationModal(id, name, desc) {
        document.getElementById('form_violation_id').value = id;
        document.getElementById('form_violation_name').value = name;
        document.getElementById('form_description').value = desc;
        document.getElementById('modalTitle').innerText = id === '' ? 'Add Violation Type' : 'Edit Violation Type';
        document.getElementById('violationModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('violationModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('violationModal')) {
            closeModal();
        }
    }
</script>