<?php
require "auth.php";
require "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
   SELECT DATE
================================ */
$selected_date = $_GET["date"] ?? date("Y-m-d");

/* ===============================
   FETCH MEMBERS (AUTO-UPDATED)
================================ */
$members = $conn->query("
    SELECT id, full_name
    FROM members
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FETCH EVENTS FOR DATE
================================ */
$events = $conn->prepare("
    SELECT id, event_name
    FROM events
    WHERE event_date = ?
    ORDER BY event_name
");
$events->execute([$selected_date]);
$events = $events->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FETCH EXISTING ATTENDANCE
================================ */
$attendance = [];
$stmt = $conn->prepare("
    SELECT event_id, member_id, present
    FROM attendance
    WHERE event_id IN (
        SELECT id FROM events WHERE event_date = ?
    )
");
$stmt->execute([$selected_date]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendance[$row["event_id"]][$row["member_id"]] = $row["present"];
}

/* ===============================
   SAVE EDITED RECORDS
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($events as $e) {
        foreach ($members as $m) {

            $present = isset($_POST["attendance"][$e["id"]][$m["id"]]) ? 1 : 0;

            $stmt = $conn->prepare("
                UPDATE attendance
                SET present = ?
                WHERE event_id = ? AND member_id = ?
            ");
            $stmt->execute([$present, $e["id"], $m["id"]]);
        }
    }

    $success = "Attendance updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Attendance Records</title>

<style>
body {
    font-family: "Segoe UI", Arial;
    background: #eef7ff;
    margin: 0;
}

.header {
    background: linear-gradient(135deg, #4db8ff, #6fd3ff);
    padding: 18px 25px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.container {
    padding: 25px;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

th {
    background: #f0f8ff;
}

button {
    margin-top: 15px;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    background: #4db8ff;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.success {
    color: green;
    margin-bottom: 10px;
}

input[type="date"] {
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
</style>
</head>

<body>

<div class="header">
    <div>📊 Edit Attendance Records</div>
    <a style="color:white;text-decoration:none;font-weight:bold;" href="attendance.php">⬅ Back</a>
</div>

<div class="container">
<div class="card">

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="get">
    <label><strong>Select Date:</strong></label><br>
    <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>">
    <button type="submit">Load</button>
</form>

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

<button type="submit">Save Changes</button>

</form>

<?php else: ?>
<p>No attendance records found for this date.</p>
<?php endif; ?>

</div>
</div>

</body>
</html>
