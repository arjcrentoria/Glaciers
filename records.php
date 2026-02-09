<?php
require "auth.php";
require "db.php";

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

$records = $conn->query("
    SELECT m.full_name, e.event_name, e.event_date, a.present
    FROM attendance a
    JOIN members m ON m.id = a.member_id
    JOIN events e ON e.id = a.event_id
    ORDER BY e.event_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Attendance Records</title>
<style>
body { font-family: Arial; background:#eef7ff; }
table { width:100%; border-collapse:collapse; background:white; }
th, td { padding:10px; border-bottom:1px solid #ddd; text-align:center; }
th { background:#f0f8ff; }
.present { color:green; font-weight:bold; }
.absent { color:red; }
</style>
</head>

<body>

<h2>📊 Attendance Records</h2>
<a href="attendance.php">⬅ Back</a>

<table>
<tr>
    <th>Name</th>
    <th>Event</th>
    <th>Date</th>
    <th>Status</th>
</tr>

<?php foreach ($records as $r): ?>
<tr>
    <td><?= htmlspecialchars($r["full_name"]) ?></td>
    <td><?= htmlspecialchars($r["event_name"]) ?></td>
    <td><?= htmlspecialchars($r["event_date"]) ?></td>
    <td class="<?= $r["present"] ? 'present' : 'absent' ?>">
        <?= $r["present"] ? "Present" : "Absent" ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
