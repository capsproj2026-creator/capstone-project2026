<?php
include '../config.php';

// 1. Handling Filter Logic
$zone_filter = isset($_GET['zone_id']) ? $_GET['zone_id'] : 'All';

// 2. Fetch Summary Stats for Top Cards
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as avail,
    SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occ,
    SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as res,
    SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maint
FROM parking_slots")->fetch_assoc();

$occupancy_rate = ($stats['total'] > 0) ? round(($stats['occ'] / $stats['total']) * 100) : 0;

// 3. Fetch All 18 Zones for the Dropdown
$zones_query = $conn->query("SELECT id, area_name FROM parking_areas ORDER BY id ASC");

// 4. Fetch Slots (Filtered or All)
$sql = "SELECT s.*, a.area_name 
        FROM parking_slots s 
        JOIN parking_areas a ON s.area_id = a.id";
if ($zone_filter !== 'All') {
    $sql .= " WHERE s.area_id = " . intval($zone_filter);
}
$sql .= " ORDER BY a.id ASC, s.slot_number ASC";
$slots = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Parking Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --avail: #22c55e; --occ: #ef4444; --res: #f59e0b; --maint: #64748b; --primary: #3182ce; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; display: flex; width: 100vw; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content-body { padding: 30px; overflow-y: auto; flex: 1; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 15px; border-radius: 12px; border: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .stat-card h3 { font-size: 1.3rem; font-weight: 700; margin: 2px 0; }
        .stat-card small { color: #718096; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }

        /* Form Group Style for Zone Navigation */
        .filter-section { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .form-group { flex: 1; max-width: 400px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #4a5568; margin-bottom: 8px; }
        .form-group select { 
            width: 100%; 
            padding: 10px 12px; 
            border-radius: 8px; 
            border: 1px solid #cbd5e1; 
            font-family: inherit; 
            font-size: 0.9rem;
            color: #2d3748;
            outline: none;
            cursor: pointer;
        }
        .form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }

        /* Slot Grid Display */
        .slots-panel { background: white; border-radius: 15px; padding: 25px; border: 1px solid #edf2f7; }
        .slots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; }
        
        .slot { border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px 5px; text-align: center; cursor: pointer; transition: 0.2s; position: relative; }
        .slot:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        
        .slot.available { background: #f0fff4; color: var(--avail); border-color: #c6f6d5; }
        .slot.occupied { background: #fff5f5; color: var(--occ); border-color: #fed7d7; }
        .slot.reserved { background: #fffaf0; color: var(--res); border-color: #fef3c7; }
        .slot.maintenance { background: #f7fafc; color: var(--maint); border-color: #e2e8f0; }
        
        .slot i { font-size: 1.1rem; margin-bottom: 5px; display: block; }
        .slot b { font-size: 0.85rem; display: block; }
        .area-label { font-size: 0.55rem; opacity: 0.7; display: block; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
     <h1 style="font-weight: 800; color: #1e293b; margin-bottom: 5px;">Parking Management</h1>
     <p style="color: #64748b; margin-bottom: 25px;">Select a zone below to filter real-time availability.</p>

     <div class="stats-grid">
          <div class="stat-card"><div><small>Total</small><h3><?= $stats['total'] ?></h3></div><i class="fa-solid fa-square-p" style="color: #3b82f6"></i></div>
          <div class="stat-card" style="color: var(--avail)"><div><small>Available</small><h3><?= $stats['avail'] ?></h3></div><i class="fa-solid fa-circle-check"></i></div>
          <div class="stat-card" style="color: var(--occ)"><div><small>Occupied</small><h3><?= $stats['occ'] ?></h3></div><i class="fa-solid fa-car-side"></i></div>
          <div class="stat-card" style="color: var(--res)"><div><small>Reserved</small><h3><?= $stats['res'] ?></h3></div><i class="fa-solid fa-lock"></i></div>
          <div class="stat-card" style="color: var(--maint)"><div><small>Maint.</small><h3><?= $stats['maint'] ?></h3></div><i class="fa-solid fa-wrench"></i></div>
          <div class="stat-card" style="color: #8b5cf6"><div><small>Usage</small><h3><?= $occupancy_rate ?>%</h3></div><i class="fa-solid fa-chart-line"></i></div>
     </div>

     <div class="filter-section">
          <div class="form-group">
               <label for="zone_id"><i class="fa-solid fa-map-location-dot"></i> Select Parking Zone</label>
               <select name="zone_id" id="zone_id" onchange="window.location.href='?zone_id=' + this.value">
               <option value="All" <?= $zone_filter == 'All' ? 'selected' : '' ?>>View All Campus (1,060 Slots)</option>
               <?php 
               $zones_query->data_seek(0);
               while($z = $zones_query->fetch_assoc()): 
               ?>
                    <option value="<?= $z['id'] ?>" <?= $zone_filter == $z['id'] ? 'selected' : '' ?>>
                         <?= $z['area_name'] ?>
                    </option>
               <?php endwhile; ?>
               </select>
          </div>
          <div style="flex: 1; text-align: right;">
               <span style="background: #e2e8f0; padding: 8px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: #475569;">
               Currently Viewing: <?= ($zone_filter == 'All') ? 'All Areas' : 'Single Zone' ?>
               </span>
          </div>
     </div>

     <div class="slots-panel">
          <h3 style="margin-bottom: 20px; color: #334155; font-size: 1.1rem; font-weight: 700;">
               <i class="fa-solid fa-grip"></i> 
               
               <?php 
               if ($zone_filter == 'All') {
               echo "All Campus Overview";
               } else {
               // This small query finds the specific name of the zone you picked
               $current_zone_name = $conn->query("SELECT area_name FROM parking_areas WHERE id = " . intval($zone_filter))->fetch_assoc();
               echo "Grid View: " . ($current_zone_name['area_name'] ?? 'Unknown Area');
               }
               ?>

               <span style="font-weight: 400; color: #94a3b8; font-size: 0.85rem; margin-left: 10px;">
               — Showing <?= $slots->num_rows ?> slots
               </span>
          </h3>
          
          <div class="slots-grid">
               <?php while($s = $slots->fetch_assoc()): 
               $status_class = strtolower($s['status']);
               $icon = ($status_class == 'occupied') ? 'fa-car-side' : (($status_class == 'maintenance') ? 'fa-wrench' : (($status_class == 'reserved') ? 'fa-lock' : 'fa-circle-check'));
               ?>
               <div class="slot <?= $status_class ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <b><?= $s['slot_number'] ?></b>
                    <?php if($zone_filter == 'All'): ?>
                         <span class="area-label" style="font-size: 0.55rem; color: #64748b;"><?= $s['area_name'] ?></span>
                    <?php endif; ?>
               </div>
               <?php endwhile; ?>
          </div>
     </div>

</body>
</html>