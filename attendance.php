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
   FETCH MEMBERS (AUTO-UPDATES)
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

        // 🔹 Check if event already exists for this date
        $stmt = $conn->prepare("
            SELECT id FROM events
            WHERE event_name = ? AND event_date = ?
        ");
        $stmt->execute([$event_name, $event_date]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            $event_id = $event["id"];

            // Clear old attendance for editing
            $conn->prepare("
                DELETE FROM attendance WHERE event_id = ?
            ")->execute([$event_id]);

        } else {
            // Create new event
            $stmt = $conn->prepare("
                INSERT INTO events (event_name, event_date)
                VALUES (?, ?)
            ");
            $stmt->execute([$event_name, $event_date]);
            $event_id = $conn->lastInsertId();
        }

        // Save attendance (checked = present, unchecked = absent)
        foreach ($members as $m) {
            $present = isset($_POST["attendance"][$event_name][$m["id"]]) ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO attendance (member_id, event_id, present)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$m["id"], $event_id, $present]);
        }
    }

    /* ===============================
       SPECIAL EVENT (OPTIONAL)
    ================================ */
    if (!empty($_POST["special_event"])) {

        $special_name = trim($_POST["special_event"]);

        $stmt = $conn->prepare("
            INSERT INTO events (event_name, event_date)
            VALUES (?, ?)
        ");
        $stmt->execute([$special_name, $event_date]);
        $event_id = $conn->lastInsertId();

        foreach ($members as $m) {
            $present = isset($_POST["special"][$m["id"]]) ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO attendance (member_id, event_id, present)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$m["id"], $event_id, $present]);
        }
    }

    $success = "✅ Attendance saved successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
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
    margin-left: 10px;
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

input[type="text"], input[type="date"] {
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
</style>
</head>

<body>

<div class="header">
    <div>❄ Attendance Management</div>
    <div>
        <a class="back-btn" href="records.php">📊 Records</a>
        <a class="back-btn" href="admin.php">⬅ Admin</a>
    </div>
</div>

<div class="container">
<div class="card">

<h2>Youth Attendance</h2>

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="post">

<!-- DATE -->
<label><strong>Attendance Date:</strong></label><br>
<input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
<br><br>

<!-- SAVE BUTTON (TOP) -->
<button type="submit">💾 Save Attendance</button>

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

<h3 style="margin-top:25px;">Special Event (Optional)</h3>
<input type="text" name="special_event" placeholder="Event name">

<table style="margin-top:10px;">
<tr>
    <th>Name</th>
    <th>Present</th>
</tr>

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

<!-- SAVE BUTTON (BOTTOM) -->
<button type="submit">💾 Save Attendance</button>

</form>

</div>
</div>

</body>
</html>
