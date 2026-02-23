<?php
require "auth.php";
require "db.php";

/* ===============================
   PREVENT CACHING
================================ */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

clearstatcache();

/* ===============================
   PROTECT ADMIN PAGE
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
   DATA QUERIES
================================ */

// 1. Attendance, Points, and Member Summary (FIXED SORTING)
$members = $conn->query("
    SELECT
        m.id AS member_id,
        m.full_name,
        SUM(CASE WHEN e.event_name='SPM' AND a.present=1 THEN 1 ELSE 0 END) AS spm,
        SUM(CASE WHEN e.event_name='SS' AND a.present=1 THEN 1 ELSE 0 END) AS ss,
        SUM(CASE WHEN e.event_name='AM' AND a.present=1 THEN 1 ELSE 0 END) AS am,
        SUM(CASE WHEN e.event_name='YP' AND a.present=1 THEN 1 ELSE 0 END) AS yp,
        SUM(CASE WHEN e.event_name='PM' AND a.present=1 THEN 1 ELSE 0 END) AS pm,
        COUNT(CASE WHEN a.present=1 THEN 1 END) AS total_present,
        (
            COUNT(CASE WHEN a.present=1 THEN 1 END) * 10
            + IFNULL((SELECT SUM(points) FROM points WHERE member_id = m.id),0)
        ) AS attendance_points
    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id
    LEFT JOIN events e ON a.event_id = e.id
    GROUP BY m.id, m.full_name
    ORDER BY m.full_name COLLATE NOCASE ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Individual Offering Summary
$offerings = $conn->query("
    SELECT m.id, IFNULL(SUM(o.amount),0)
    FROM members m
    LEFT JOIN offerings o ON m.id = o.member_id
    GROUP BY m.id
")->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Grand Total Offering
$total_offering_all = $conn->query("SELECT IFNULL(SUM(amount),0) FROM offerings")->fetchColumn();

// 4. Financial Audit Totals
$audit = $conn->query("
    SELECT
        SUM(CASE WHEN type='IN' THEN amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN type='OUT' THEN amount ELSE 0 END) AS total_out
    FROM audit_logs
")->fetch(PDO::FETCH_ASSOC);

$audit_in  = $audit["total_in"] ?? 0;
$audit_out = $audit["total_out"] ?? 0;
$audit_balance = $audit_in - $audit_out;

// 5. Special Events Logic (FIXED SORTING)
$events_list = $conn->query("
    SELECT MIN(id) AS event_id, event_name, event_date
    FROM events
    WHERE event_name NOT IN ('SPM','SS','AM','YP','PM')
    GROUP BY event_name, event_date
    ORDER BY event_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$special_events = [];
foreach ($events_list as $ev) {
    $stmt = $conn->prepare("
        SELECT m.full_name, a.present
        FROM attendance a
        JOIN members m ON m.id = a.member_id
        WHERE a.event_id = ?
        ORDER BY m.full_name COLLATE NOCASE ASC
    ");
    $stmt->execute([$ev["event_id"]]);
    $special_events[$ev["event_name"] . " (" . $ev["event_date"] . ")"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GLACIERS Admin Dashboard</title>

<style>
/* CSS Variables for easy color management */
:root {
    --primary: #4db8ff;
    --primary-dark: #0088cc;
    --highlight: #d1ecff; 
    --bg: #eef7ff;
    --text: #333;
    --success: #2e7d32;
    --danger: #c62828;
}

* { box-sizing: border-box; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
body { margin: 0; background: var(--bg); color: var(--text); }

/* Header and Navigation */
.header {
    background: linear-gradient(135deg, var(--primary), #6fd3ff);
    padding: 16px 20px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header-links a {
    color: white;
    text-decoration: none;
    font-weight: bold;
    margin-left: 15px;
    padding: 8px 14px;
    border-radius: 8px;
    transition: 0.3s ease;
}

.header-links a:hover { background: rgba(255, 255, 255, 0.3); }

.container { padding: 20px; max-width: 1200px; margin: auto; }

/* Dashboard Cards */
.card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    transition: 0.3s ease;
}

h2 { color: var(--primary-dark); margin-top: 0; font-size: 1.3rem; border-left: 5px solid var(--primary); padding-left: 10px; }

/* Main Table with High-Contrast Highlight */
.table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #ddd;
    border-radius: 10px;
}

table { width: 100%; border-collapse: collapse; min-width: 800px; background: white; }
th, td { padding: 14px 12px; border-bottom: 1px solid #eee; text-align: center; transition: all 0.2s; }
th { background: #e1f2ff; font-weight: bold; color: #333; border-bottom: 2px solid var(--primary); }

/* Stronger Hover Logic */
tr:hover td {
    background-color: var(--highlight) !important;
    color: #000;
    font-weight: 600;
}

/* Sticky Column handling hover */
.sticky-name {
    position: sticky;
    left: 0;
    background: white;
    z-index: 5;
    text-align: left !important;
    font-weight: bold;
    border-right: 2px solid var(--bg);
}

th.sticky-name { background: #e1f2ff; z-index: 6; }
tr:hover .sticky-name { background: var(--highlight) !important; }

/* Navigation Buttons */
.button-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.btn-nav {
    display: block;
    padding: 20px;
    border-radius: 12px;
    color: white;
    text-decoration: none;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-nav:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }
.btn-nav:active { transform: scale(0.95); }

.attendance { background: #2e7d32; }
.offering { background: #ef6c00; }
.points { background: #7b1fa2; }
.export { background: #006064; }

/* Financial Text */
.in { color: var(--success); font-weight: bold; }
.out { color: var(--danger); font-weight: bold; }

/* Special Events Section */
details { border: 1px solid #eee; border-radius: 8px; margin-bottom: 10px; }
summary { padding: 15px; cursor: pointer; font-weight: bold; outline: none; transition: 0.2s; }
summary:hover { background: var(--highlight); }

/* Responsive Adjustments */
@media (max-width: 768px) {
    .header { flex-direction: column; text-align: center; }
    .header-links { margin-top: 10px; }
    .button-grid { grid-template-columns: 1fr; }
    .card { padding: 15px; }
}
</style>
</head>
<body>

<div class="header">
    <div style="font-size: 1.4rem; font-weight: bold;">❄ GLACIERS Dashboard</div>
    <div class="header-links">
        <a href="masterlist.php">Masterlist</a>
        <a href="logout.php" style="background: var(--danger); border-radius: 6px;">Logout</a>
    </div>
</div>

<div class="container">

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px;">
        <div class="card">
            <h2>Total Offering (All)</h2>
            <h3 style="font-size: 2.2rem; color: var(--success); margin: 10px 0;">₱<?= number_format($total_offering_all, 2) ?></h3>
        </div>

        <div class="card">
            <h2>Financial Audit Status</h2>
            <div class="table-wrap">
                <table style="min-width: 100%;">
                    <tr><th>Total In</th><th>Total Out</th><th>Balance</th></tr>
                    <tr>
                        <td class="in">₱<?= number_format($audit_in, 2) ?></td>
                        <td class="out">₱<?= number_format($audit_out, 2) ?></td>
                        <td style="font-size: 1.2rem; font-weight: 900;">₱<?= number_format($audit_balance, 2) ?></td>
                    </tr>
                </table>
            </div>
            <div style="margin-top: 12px; text-align: right;"><a href="audit.php" style="color: var(--primary-dark); font-weight: bold; text-decoration: none;">View Detailed Audit →</a></div>
        </div>
    </div>

    <div class="button-grid">
        <a class="btn-nav attendance" href="attendance.php">📅 Take Attendance</a>
        <a class="btn-nav offering" href="offering.php">💰 Encode Offering</a>
        <a class="btn-nav points" href="points.php">⭐ Manage Points</a>
        <a class="btn-nav export" href="export_yp.php">⬇ Export Report</a>
    </div>

    <div class="card">
        <h2>Member Performance Summary</h2>
        <p style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">*Hover or tap a row to highlight data.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-name">Full Name</th>
                        <th>SPM</th><th>SS</th><th>AM</th><th>YP</th><th>PM</th>
                        <th>Total</th><th>Offering</th><th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="sticky-name"><?= htmlspecialchars($m["full_name"] ?? '') ?></td>
                        <td><?= $m["spm"] ?></td>
                        <td><?= $m["ss"] ?></td>
                        <td><?= $m["am"] ?></td>
                        <td><?= $m["yp"] ?></td>
                        <td><?= $m["pm"] ?></td>
                        <td><strong><?= $m["total_present"] ?></strong></td>
                        <td style="color: var(--success);">₱<?= number_format($offerings[$m["member_id"]] ?? 0, 2) ?></td>
                        <td style="color: #7b1fa2; font-weight: bold;"><?= $m["attendance_points"] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Special Events History</h2>
        <?php foreach ($special_events as $event => $rows): ?>
        <details>
            <summary><?= htmlspecialchars($event) ?> <span style="font-weight: normal; color: #888; margin-left: 10px;">(Click to view)</span></summary>
            <div class="table-wrap">
                <table style="min-width: 100%;">
                    <tr><th style="text-align: left;">Name</th><th>Status</th></tr>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="text-align: left;"><?= htmlspecialchars($r["full_name"] ?? '') ?></td>
                        <td style="font-weight: bold; color: <?= $r["present"] ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= $r["present"] ? "✔ Present" : "✖ Absent" ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </details>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>