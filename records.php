<?php
require "auth.php";
require "db.php";

/* ===============================
   PROTECT ADMIN
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
   SELECT DATE
================================ */
$selected_date = $_GET["date"] ?? date("Y-m-d");

/* ===============================
   FETCH MEMBERS
================================ */
$members = $conn->query("
    SELECT id, full_name
    FROM members
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   EVENTS
================================ */
$fixed_events = ["SPM", "SS", "AM", "YP", "PM"];

/* ===============================
   FETCH EXISTING ATTENDANCE
================================ */
$attendance_data = [];

$stmt = $conn->prepare("
    SELECT a.member_id, e.event_name, a.present
    FROM attendance a
    JOIN events e ON e.id = a.event_id
    WHERE e.event_date = ?
");
$stmt->execute([$selected_date]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendance_data[$row["event_name"]][$row["member_id"]] = $row["present"];
}

/* ===============================
   SAVE EDITED RECORDS
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($fixed_events as $event_name) {

        /* GET OR CREATE EVENT */
        $stmt = $conn->prepare("
            SELECT id FROM events
            WHERE event_name = ? AND event_date = ?
        ");
        $stmt->execute([$event_name, $selected_date]);
        $event_id = $stmt->fetchColumn();

        if (!$event_id) {
            $stmt = $conn->prepare("
                INSERT INTO events (event_name, event_date)
                VALUES (?, ?)
            ");
            $stmt->execute([$event_name, $selected_date]);
            $event_id = $conn->lastInsertId();
        }

        /* CLEAR OLD DATA */
        $stmt = $conn->prepare("DELETE FROM attendance WHERE event_id = ?");
        $stmt->execute([$event_id]);

        /* INSERT UPDATED DATA */
        foreach ($members as $m) {
            $present = isset($_POST["attendance"][$event_name][$m["id"]]) ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO attendance (member_id, event_id, present)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$m["id"], $event_id, $present]);
        }
    }

    header("Location: record.php?date=$selected_date&saved=1");
    exit;
}
?>

<!DOCTYPE html>
<html>
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
}
.container { padding: 25px; }
.card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
table {
    width: 100%;
    border-collapse: collapse;
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
</style>
</head>

<body>

<div class="header">
    <div>📊 Attendance Records</div>
    <a href="admin.php" style="color:white;text-decoration:none;">⬅ Back</a>
</div>

<div class="container">
<div class="card">

<h2>Edit Attendance</h2>

<?php if (isset($_GET["saved"])): ?>
<div class="success">Records updated successfully!</div>
<?php endif; ?>

<form method="get">
<label><strong>Select Date:</strong></label><br>
<input type="date" name="date" value="<?= $selected_date ?>" required>
<button type="submit">Load</button>
</form>

<hr>

<form method="post">

<table>
<tr>
    <th>Name</th>
    <?php foreach ($fixed_events as $e): ?>
        <th><?= $e ?></th>
    <?php endforeach; ?>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>

    <?php foreach ($fixed_events as $e): ?>
        <td>
            <input type="checkbox"
                   name="attendance[<?= $e ?>][<?= $m['id'] ?>]"
                   <?= !empty($attendance_data[$e][$m["id"]]) ? "checked" : "" ?>>
        </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>

<button type="submit">Save Changes</button>

</form>

</div>
</div>

</body>
</html>
