<?php
require "auth.php";
require "db.php";

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

$date = $_GET["date"] ?? date("Y-m-d");

/* MEMBERS */
$members = $conn->query("
    SELECT id, full_name
    FROM members
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* EVENTS ON SELECTED DATE */
$events = $conn->prepare("
    SELECT id, event_name
    FROM events
    WHERE event_date = ?
    ORDER BY event_name
");
$events->execute([$date]);
$events = $events->fetchAll(PDO::FETCH_ASSOC);

/* EXISTING ATTENDANCE */
$attendance = [];
$stmt = $conn->prepare("
    SELECT member_id, event_id, present
    FROM attendance a
    JOIN events e ON e.id = a.event_id
    WHERE e.event_date = ?
");
$stmt->execute([$date]);
foreach ($stmt as $row) {
    $attendance[$row["event_id"]][$row["member_id"]] = $row["present"];
}

/* SAVE CHANGES */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($_POST["attendance"] ?? [] as $event_id => $members_data) {

        foreach ($members_data as $member_id => $value) {

            $present = $value == "1" ? 1 : 0;

            $stmt = $conn->prepare("
                UPDATE attendance
                SET present = ?
                WHERE event_id = ? AND member_id = ?
            ");
            $stmt->execute([$present, $event_id, $member_id]);
        }
    }

    $success = "Attendance updated successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Attendance Records</title>

<style>
body {
    font-family: Arial;
    background:#eef7ff;
    margin:0;
}
.header {
    background:#4db8ff;
    padding:15px 20px;
    color:white;
    display:flex;
    justify-content:space-between;
}
.container {
    padding:25px;
}
table {
    width:100%;
    border-collapse:collapse;
    background:white;
}
th, td {
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}
th {
    background:#f0f8ff;
}
button {
    padding:10px 18px;
    border:none;
    background:#4db8ff;
    color:white;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
}
.success {
    color:green;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="header">
    <div>📊 Edit Attendance Records</div>
    <a href="attendance.php" style="color:white;text-decoration:none;">⬅ Back</a>
</div>

<div class="container">

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="get">
    <label><strong>Select Date:</strong></label>
    <input type="date" name="date" value="<?= $date ?>">
    <button type="submit">Load</button>
</form>

<br>

<?php if ($events): ?>
<form method="post">

<table>
<tr>
    <th>Name</th>
    <?php foreach ($events as $e): ?>
        <th><?= htmlspecialchars($e["event_name"]) ?></th>
    <?php endforeach; ?>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>

    <?php foreach ($events as $e): ?>
        <td>
            <input type="checkbox"
                   name="attendance[<?= $e['id'] ?>][<?= $m['id'] ?>]"
                   value="1"
                   <?= !empty($attendance[$e["id"]][$m["id"]]) ? "checked" : "" ?>>
        </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>

</table>

<br>
<button type="submit">Save Changes</button>

</form>
<?php else: ?>
<p>No attendance records found for this date.</p>
<?php endif; ?>

</div>
</body>
</html>
