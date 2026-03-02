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
   GLOBAL RESET
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["global_reset_all"])) {
    try {
        $conn->exec("DELETE FROM offerings");
        $success = "🧨 ALL offering records have been permanently deleted.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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
   GET ALL SUNDAYS OF 2026
================================ */
$sundays = [];
$d = new DateTime("2026-01-01");
$d->modify("next sunday");
while ($d->format("Y") === "2026") {
    $sundays[] = $d->format("Y-m-d");
    $d->modify("+7 days");
}

/* ===============================
   SAVE OFFERINGS (SQLITE SAFE)
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["global_reset_all"])) {

    /* ---- SUNDAY OFFERINGS ---- */
    if (!empty($_POST["offering"])) {
        foreach ($_POST["offering"] as $member_id => $dates) {
            foreach ($dates as $date => $amount) {

                if ($amount === "" || !is_numeric($amount)) continue;

                $stmt = $conn->prepare("
                    INSERT INTO offerings (member_id, offering_date, amount, event_name)
                    VALUES (?, ?, ?, NULL)
                    ON CONFLICT(member_id, offering_date)
                    DO UPDATE SET amount = excluded.amount
                ");
                $stmt->execute([$member_id, $date, $amount]);
            }
        }
    }

    /* ---- SPECIAL EVENT OFFERINGS ---- */
    if (!empty($_POST["event_name"]) && !empty($_POST["event_date"])) {
        $event_name = trim($_POST["event_name"]);
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

    $success = "✅ Offerings saved successfully!";
}

/* ===============================
   LOAD EXISTING SUNDAY DATA
================================ */
$existing = [];
$q = $conn->query("
    SELECT member_id, offering_date, amount
    FROM offerings
    WHERE event_name IS NULL
");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $existing[$r["member_id"]][$r["offering_date"]] = $r["amount"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Offerings 2026</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { margin:0; background:#eef7ff; font-family:Segoe UI, Arial }
.header { background:#4db8ff; color:#fff; padding:15px; display:flex; justify-content:space-between }
.container { padding:20px; max-width:1400px; margin:auto }
.card { background:#fff; padding:20px; border-radius:14px; margin-bottom:25px }
.table-wrap { max-height:70vh; overflow:auto; border:1px solid #ddd }
table { border-collapse:collapse; min-width:1200px; width:100% }
th,td { border:1px solid #eee; padding:8px; text-align:center }
th { background:#f0f8ff; position:sticky; top:0 }
.sticky { position:sticky; left:0; background:#fff; font-weight:bold }
input { width:70px; text-align:center }
.success { background:#e8f5e9; padding:12px; border-radius:8px; margin-bottom:15px }
button { padding:14px; width:100%; background:#2e7d32; color:#fff; border:none; border-radius:10px }
</style>
</head>

<body>

<div class="header">
    <div>❄ 2026 Offerings</div>
    <a href="admin.php" style="color:white;text-decoration:none;">← Back</a>
</div>

<div class="container">

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>
<?php if (isset($error)) echo "<div class='success' style='background:#ffebee'>$error</div>"; ?>

<div class="card">
<h3>Sunday Offerings</h3>

<form method="post">
<div class="table-wrap">
<table>
<thead>
<tr>
<th class="sticky">Name</th>
<?php foreach ($sundays as $d): ?>
<th><?= date("M j", strtotime($d)) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($members as $m): ?>
<tr>
<td class="sticky"><?= htmlspecialchars($m["full_name"]) ?></td>
<?php foreach ($sundays as $d): ?>
<td>
<input type="number" step="0.01"
name="offering[<?= $m["id"] ?>][<?= $d ?>]"
value="<?= $existing[$m["id"]][$d] ?? "" ?>">
</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<button type="submit">💾 Save Sunday Offerings</button>
</form>
</div>

<div class="card">
<h3>Special Event Offering</h3>
<form method="post">
<input type="text" name="event_name" placeholder="Event Name" required>
<input type="date" name="event_date" required>

<table>
<tr><th>Name</th><th>Amount</th></tr>
<?php foreach ($members as $m): ?>
<tr>
<td><?= htmlspecialchars($m["full_name"]) ?></td>
<td><input type="number" step="0.01" name="special[<?= $m["id"] ?>]"></td>
</tr>
<?php endforeach; ?>
</table>

<button type="submit" style="background:#ef6c00">💰 Save Special Event</button>
</form>
</div>

<form method="post" onsubmit="return confirm('DELETE ALL OFFERINGS?')">
<input type="hidden" name="global_reset_all" value="1">
<button style="background:#c62828">⚠️ RESET ALL DATA</button>
</form>

</div>
</body>
</html>