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
   SAVE ATTENDANCE
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_date = $_POST["attendance_date"] ?? date("Y-m-d");

    foreach ($fixed_events as $event_name) {

        $stmt = $conn->prepare("
            INSERT INTO events (event_name, event_date)
            VALUES (?, ?)
        ");
        $stmt->execute([$event_name, $event_date]);

        $event_id = $conn->lastInsertId();

        foreach ($_POST["attendance"][$event_name] ?? [] as $member_id => $value) {
            $present = ($value == "1") ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO attendance (member_id, event_id, present)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$member_id, $event_id, $present]);
        }
    }

    if (!empty($_POST["special_event"])) {

        $stmt = $conn->prepare("
            INSERT INTO events (event_name, event_date)
            VALUES (?, ?)
        ");
        $stmt->execute([$_POST["special_event"], $event_date]);

        $event_id = $conn->lastInsertId();

        foreach ($_POST["special"] ?? [] as $member_id => $value) {
            $present = ($value == "1") ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO attendance (member_id, event_id, present)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$member_id, $event_id, $present]);
        }
    }

    $success = "Attendance saved successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Attendance</title>

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
.back-btn {
    color: white;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
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
th { background: #f0f8ff; }
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
.success { color: green; }
</style>
</head>

<body>

<div class="header">
    <div>❄ Attendance Management</div>
    <div>
        <a class="back-btn" href="admin.php">⬅ Back</a>
        &nbsp; | &nbsp;
        <a class="back-btn" href="records.php">📊 Records</a>
    </div>
</div>

<div class="container">
<div class="card">

<h2>Youth Attendance</h2>
<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="post">

<!-- 🔼 SAVE BUTTON (TOP) -->
<button type="submit">Save Attendance</button><br><br>

<label><strong>Attendance Date:</strong></label><br>
<input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required><br><br>

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
                   value="1">
        </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>

<h3 style="margin-top:25px;">Special Event</h3>
<input type="text" name="special_event" placeholder="Event name">

<table>
<tr><th>Name</th><th>Present</th></tr>
<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>
    <td>
        <input type="checkbox"
               name="special[<?= $m['id'] ?>]"
               value="1">
    </td>
</tr>
<?php endforeach; ?>
</table>

<!-- 🔽 SAVE BUTTON (BOTTOM) -->
<button type="submit">Save Attendance</button>

</form>
</div>
</div>

</body>
</html>
