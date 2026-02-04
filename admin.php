<?php
require "auth.php";
require "db.php";


/* ===============================
   PROTECT ADMIN PAGE
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
   ATTENDANCE SUMMARY
================================ */
$members = $conn->query("
    SELECT
        m.id AS member_id,
        m.full_name,

        SUM(CASE WHEN e.event_name='SPM' AND a.present=1 THEN 1 ELSE 0 END) AS spm,
        SUM(CASE WHEN e.event_name='SS'  AND a.present=1 THEN 1 ELSE 0 END) AS ss,
        SUM(CASE WHEN e.event_name='AM'  AND a.present=1 THEN 1 ELSE 0 END) AS am,
        SUM(CASE WHEN e.event_name='YP'  AND a.present=1 THEN 1 ELSE 0 END) AS yp,
        SUM(CASE WHEN e.event_name='PM'  AND a.present=1 THEN 1 ELSE 0 END) AS pm,

        COUNT(CASE WHEN a.present=1 THEN 1 END) AS total_present,
        COUNT(CASE WHEN a.present=1 THEN 1 END) * 10 AS attendance_points

    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id
    LEFT JOIN events e ON a.event_id = e.id
    GROUP BY m.id
    ORDER BY m.full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   OFFERING TOTALS
================================ */
$offerings = $conn->query("
    SELECT m.id, IFNULL(SUM(o.amount),0)
    FROM members m
    LEFT JOIN offerings o ON m.id = o.member_id
    GROUP BY m.id
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* GLOBAL OFFERING TOTAL */
$total_offering_all = $conn->query("
    SELECT IFNULL(SUM(amount),0) FROM offerings
")->fetchColumn();

/* ===============================
   AUDIT SUMMARY
================================ */
$audit = $conn->query("
    SELECT
        SUM(CASE WHEN type='IN' THEN amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN type='OUT' THEN amount ELSE 0 END) AS total_out
    FROM audit_logs
")->fetch(PDO::FETCH_ASSOC);

$audit_in  = $audit["total_in"]  ?? 0;
$audit_out = $audit["total_out"] ?? 0;
$audit_balance = $audit_in - $audit_out;

/* ===============================
   SPECIAL EVENTS
================================ */
$special = $conn->query("
    SELECT
        e.event_name,
        e.event_date,
        m.full_name,
        a.present
    FROM events e
    JOIN attendance a ON e.id = a.event_id
    JOIN members m ON a.member_id = m.id
    WHERE e.event_name NOT IN ('SPM','SS','AM','YP','PM')
    ORDER BY e.event_date DESC, e.event_name, m.full_name
")->fetchAll(PDO::FETCH_ASSOC);

$special_events = [];
foreach ($special as $row) {
    $key = $row["event_name"] . " (" . $row["event_date"] . ")";
    $special_events[$key][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>GLACIERS Admin Dashboard</title>

<style>
* { box-sizing:border-box; font-family:"Segoe UI",Arial }
body { margin:0; background:#eef7ff }

.header {
    background:linear-gradient(135deg,#4db8ff,#6fd3ff);
    padding:18px 25px;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:22px;
}

.header a {
    color:white;
    text-decoration:none;
    font-weight:bold;
    margin-left:12px;
}

.container { padding:25px }

.card {
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    margin-bottom:25px;
}

h2,h3 { color:#4db8ff; margin-top:0 }

table {
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}
th,td {
    padding:10px;
    border-bottom:1px solid #eee;
    text-align:center;
}
th { background:#f0f8ff }

.attendance { background:#4caf50; color:white; padding:10px 16px; border-radius:10px; text-decoration:none; font-weight:bold }
.offering   { background:#ff9800; color:white; padding:10px 16px; border-radius:10px; text-decoration:none; font-weight:bold }
.points     { background:#9c27b0; color:white; padding:10px 16px; border-radius:10px; text-decoration:none; font-weight:bold }
.auditbtn   { background:#0284c7; color:white; padding:10px 16px; border-radius:10px; text-decoration:none; font-weight:bold }
.export     { background:#16a34a; color:white; padding:10px 16px; border-radius:10px; text-decoration:none; font-weight:bold }

.in { color:green; font-weight:bold }
.out { color:red; font-weight:bold }
</style>
</head>

<body>

<div class="header">
    <div>❄ GLACIERS Admin Dashboard</div>
    <div>
        <a href="masterlist.php">Masterlist</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

<!-- ================= GLOBAL OFFERING ================= -->
<div class="card">
<h2>Total Offering (All Members)</h2>
<h3>₱<?= number_format($total_offering_all,2) ?></h3>
</div>

<!-- ================= AUDIT SUMMARY ================= -->
<div class="card">
<h2>Financial Audit Summary</h2>

<table>
<tr>
    <th>Total Money In</th>
    <th>Total Money Out</th>
    <th>Balance</th>
    <th>Action</th>
</tr>
<tr>
    <td class="in">₱<?= number_format($audit_in,2) ?></td>
    <td class="out">₱<?= number_format($audit_out,2) ?></td>
    <td><strong>₱<?= number_format($audit_balance,2) ?></strong></td>
    <td><a class="auditbtn" href="audit.php">Open Audit</a></td>
</tr>
</table>
</div>

<!-- ================= GLOBAL BUTTONS ================= -->
<div class="card">
<a class="attendance" href="attendance.php">📅 Attendance</a>
<a class="offering" href="offering.php">💰 Offering</a>
<a class="points" href="points.php">⭐ Points</a>
<a class="export" href="export_yp.php">⬇ Export</a>
</div>

<!-- ================= MEMBER SUMMARY ================= -->
<div class="card">
<h2>Attendance, Offering & Points Summary</h2>

<table>
<tr>
    <th>Name</th>
    <th>SPM</th>
    <th>SS</th>
    <th>AM</th>
    <th>YP</th>
    <th>PM</th>
    <th>Total Present</th>
    <th>Offering</th>
    <th>Points</th>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>
    <td><?= $m["spm"] ?></td>
    <td><?= $m["ss"] ?></td>
    <td><?= $m["am"] ?></td>
    <td><?= $m["yp"] ?></td>
    <td><?= $m["pm"] ?></td>
    <td><strong><?= $m["total_present"] ?></strong></td>
    <td>₱<?= number_format($offerings[$m["member_id"]] ?? 0,2) ?></td>
    <td><?= $m["attendance_points"] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<!-- ================= SPECIAL EVENTS ================= -->
<div class="card">
<h2>Special Events Attendance</h2>

<?php if (empty($special_events)): ?>
<p>No special events recorded.</p>
<?php endif; ?>

<?php foreach ($special_events as $event => $rows): ?>
<h3><?= htmlspecialchars($event) ?></h3>

<table>
<tr>
    <th>Name</th>
    <th>Date</th>
    <th>Attendance</th>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r["full_name"]) ?></td>
    <td><?= $r["event_date"] ?></td>
    <td><?= $r["present"] ? "Present" : "Absent" ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>
</div>

</div>
</body>
</html>
