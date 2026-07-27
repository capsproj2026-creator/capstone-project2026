<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violation Dashboard - VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Stats Cards */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .stat-card { 
            background: white; 
            padding: 24px; 
            border-radius: 16px; 
            border: 1px solid var(--border); 
            display: flex; 
            align-items: center; 
            gap: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-icon { 
            width: 50px; height: 50px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
        }
        .stat-data p { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .stat-data h3 { font-size: 1.6rem; font-weight: 700; margin-top: 2px; }

        /* Table Section */
        .panel { 
            background: white; 
            border-radius: 16px; 
            border: 1px solid var(--border); 
            padding: 20px; 
        }
        .panel-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding-bottom: 20px; 
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        .search-box { 
            padding: 10px 15px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            width: 300px; 
            outline: none; 
            transition: 0.2s;
        }
        .search-box:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1); }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 15px; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        
        .status-badge { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-size: 0.75rem; 
            font-weight: 700; 
        }
        .pending { background: #fff5f5; color: #c53030; }
        .resolved { background: #f0fff4; color: #2f855a; }

        .btn-view { 
            color: var(--primary); 
            background: transparent; 
            border: none; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 0.85rem;
        }
        .btn-view:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <h1>Violation Dashboard</h1>
    <p>Real-time overview of campus traffic and safety compliance.</p>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-data"><p>Pending Cases</p><h3 id="count-pending">14</h3></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #22c55e;"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-data"><p>Resolved Cases</p><h3>89</h3></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0ea5e9;"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="stat-data"><p>Total Records</p><h3>103</h3></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Recent Violations</h3>
            <input type="text" class="search-box" id="searchInput" placeholder="Search plate or driver..." onkeyup="filterLog()">
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Driver / Plate</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="violationLog">
                </tbody>
        </table>
    </div>

    <script>
        const data = [
            { time: 'May 18, 02:30 PM', driver: 'Juan Dela Cruz', plate: 'NXX-902', type: 'No Helmet', area: 'Main Gate', status: 'Pending' },
            { time: 'May 18, 11:15 AM', driver: 'Alice Guo', plate: 'PNP-001', type: 'Illegal Parking', area: 'Admin Circle', status: 'Resolved' },
            { time: 'May 17, 04:45 PM', driver: 'Bong Go', plate: 'ABC-123', type: 'Speeding', area: 'Acad 5 Rd', status: 'Pending' },
            { time: 'May 17, 09:20 AM', driver: 'Robin Padilla', plate: 'ACT-007', type: 'No ID Entry', area: 'Side Gate', status: 'Resolved' }
        ];

        function renderDashboard() {
            const log = document.getElementById('violationLog');
            log.innerHTML = data.map(v => `
                <tr>
                    <td>${v.time}</td>
                    <td>
                        <div style="font-weight: 700;">${v.driver}</div>
                        <div style="font-size: 0.75rem; color: #64748b;">${v.plate}</div>
                    </td>
                    <td>${v.type}</td>
                    <td>${v.area}</td>
                    <td><span class="status-badge ${v.status.toLowerCase()}">${v.status}</span></td>
                    <td><button class="btn-view">View Details</button></td>
                </tr>
            `).join('');
        }

        function filterLog() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#violationLog tr');
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // Initialize
        renderDashboard();
    </script>
</body>
</html>