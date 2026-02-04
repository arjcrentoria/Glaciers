<?php
require "auth.php";
require "db.php";

/* PROTECT ADMIN */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* FETCH MEMBERS */
$members = $conn->query("
    SELECT id, full_name 
    FROM members 
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* GET ALL SUNDAYS OF 2026 */
$sundays = [];
$date = new DateTime("2026-01-01");
$date->modify("next sunday");
while ($date->format("Y") == "2026") {
    $sundays[] = $date->format("Y-m-d");
    $date->modify("+7 days");
}

/* SAVE DATA */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* SAVE SUNDAY OFFERINGS */
    if (!empty($_POST["offering"])) {
        foreach ($_POST["offering"] as $member_id => $dates) {
            foreach ($dates as $offering_date => $amount) {

                if ($amount === "" || !is_numeric($amount)) continue;

                $stmt = $conn->prepare("
                    INSERT INTO offerings (member_id, offering_date, amount, event_name)
                    VALUES (?, ?, ?, NULL)
                    ON CONFLICT(member_id, offering_date)
                    DO UPDATE SET amount = excluded.amount
                ");
                $stmt->execute([$member_id, $offering_date, $amount]);
            }
        }
    }

    /* SAVE SPECIAL EVENT OFFERING */
    if (!empty($_POST["event_name"]) && !empty($_POST["event_date"])) {

        $event_name = $_POST["event_name"];
        $event_date = $_POST["event_date"];

        foreach ($_POST["special"] ?? [] as $member_id => $amount) {

            if ($amount === "" || !is_numeric($amount)) continue;

            $stmt = $conn->prepare("
                INSERT INTO offerings (member_id, offering_date, amount, event_name)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$member_id, $event_date, $amount, $event_name]);
        }
    }

    $success = "Offerings saved successfully!";
}

/* FETCH EXISTING OFFERINGS */
$existing = [];
$stmt = $conn->query("SELECT * FROM offerings WHERE event_name IS NULL");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row["member_id"]][$row["offering_date"]] = $row["amount"];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Offerings 2026</title>

<style>
body { margin:0; font-family:Segoe UI, Arial; background:#eef7ff }
.header {
    background:linear-gradient(135deg,#4db8ff,#6fd3ff);
    padding:18px 25px;
    color:white;
    font-size:22px;
    display:flex;
    justify-content:space-between;
}
.header a { color:white; text-decoration:none; font-size:14px }
.container { padding:25px }
.card {
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    overflow-x:auto;
    margin-bottom:25px;
}
table {
    border-collapse:collapse;
    font-size:13px;
    min-width:1200px;
}
th, td {
    padding:8px;
    border-bottom:1px solid #eee;
    text-align:center;
}
th { background:#f0f8ff; position:sticky; top:0 }
input[type="number"], input[type="text"], input[type="date"] {
    padding:6px;
    border-radius:6px;
    border:1px solid #ccc;
}
button {
    margin-top:15px;
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:#4db8ff;
    color:white;
    font-weight:bold;
}
.success { color:green; margin-bottom:10px }
</style>
</head>

<body>

<div class="header">
    <div>❄ Offerings – 2026</div>
    <a href="admin.php">← Back to Admin</a>
</div>

<div class="container">

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<!-- ================= SUNDAY OFFERINGS ================= -->
<div class="card">
<h2>Sunday Offerings (2026)</h2>

<form method="post">
<table>
<tr>
    <th>Name</th>
    <?php foreach ($sundays as $d): ?>
        <th><?= date("M j", strtotime($d)) ?></th>
    <?php endforeach; ?>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>
    <?php foreach ($sundays as $d): ?>
    <td>
        <input type="number" step="0.01"
        name="offering[<?= $m["id"] ?>][<?= $d ?>]"
        value="<?= $existing[$m["id"]][$d] ?? "" ?>">
    </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>

<button type="submit">Save Sunday Offerings</button>
</form>
</div>

<!-- ================= SPECIAL EVENT ================= -->
<div class="card">
<h2>Special Event Offering</h2>

<form method="post">
<p>
    <input type="text" name="event_name" placeholder="Event Name (e.g. Youth Camp)" required>
    <input type="date" name="event_date" required>
</p>

<table>
<tr>
    <th>Name</th>
    <th>Offering</th>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>
    <td>
        <input type="number" step="0.01" name="special[<?= $m["id"] ?>]">
    </td>
</tr>
<?php endforeach; ?>
</table>

<button type="submit">Save Special Event</button>
</form>
</div>

</div>
</body>
</html>
