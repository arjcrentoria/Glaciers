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
   ADD POINTS
================================ */
if (isset($_POST["add_points"])) {
    $stmt = $conn->prepare("
        INSERT INTO points (member_id, points, reason)
        VALUES (?, ?, ?)
    ");

    foreach ($_POST["points"] as $member_id => $points) {
        if ($points === "" || !is_numeric($points)) continue;

        $reason = $_POST["reason"][$member_id] ?? null;
        $stmt->execute([$member_id, $points, $reason]);
    }

    $success = "Points added successfully!";
}

/* ===============================
   RESET POINTS
================================ */
if (isset($_POST["reset_points"])) {
    $conn->exec("DELETE FROM points");
    $success = "All points have been reset!";
}

/* ===============================
   TOTAL POINTS PER MEMBER
================================ */
$totals = [];
$stmt = $conn->query("
    SELECT member_id, SUM(points) AS total
    FROM points
    GROUP BY member_id
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $totals[$row["member_id"]] = $row["total"];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Points System</title>

<style>
* { box-sizing:border-box; font-family:Segoe UI, Arial; }
body { margin:0; background:#eef7ff; }

.header {
    background:linear-gradient(135deg,#9c27b0,#b44cff);
    padding:18px 25px;
    color:white;
    font-size:22px;
    display:flex;
    justify-content:space-between;
}

.header a {
    color:white;
    text-decoration:none;
    font-size:14px;
    font-weight:bold;
}

.container { padding:25px; }

.card {
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

table {
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

th, td {
    padding:10px;
    border-bottom:1px solid #eee;
    text-align:center;
}

th { background:#f7e9ff; }

input[type="number"] {
    width:70px;
    padding:6px;
    border-radius:6px;
    border:1px solid #ccc;
}

input[type="text"] {
    width:140px;
    padding:6px;
    border-radius:6px;
    border:1px solid #ccc;
}

button {
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:#9c27b0;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

.reset {
    background:#f44336;
    margin-top:10px;
}

.success {
    color:green;
    margin-bottom:10px;
    font-weight:bold;
}
</style>
</head>

<body>

<div class="header">
    <div>🏆 Points System</div>
    <a href="admin.php">← Back to Admin</a>
</div>

<div class="container">
<div class="card">

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="post">

<table>
<tr>
    <th>Name</th>
    <th>Add Points</th>
    <th>Reason</th>
    <th>Total Points</th>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>

    <td>
        <input type="number" name="points[<?= $m["id"] ?>]" min="1">
    </td>

    <td>
        <input type="text" name="reason[<?= $m["id"] ?>]" placeholder="Reason (optional)">
    </td>

    <td><strong><?= $totals[$m["id"]] ?? 0 ?></strong></td>
</tr>
<?php endforeach; ?>

</table>

<button type="submit" name="add_points">Save Points</button>

</form>

<form method="post" onsubmit="return confirm('Reset ALL points?');">
    <button type="submit" name="reset_points" class="reset">Reset All Points</button>
</form>

</div>
</div>

</body>
</html>
