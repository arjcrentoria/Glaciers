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
   SAVE AUDIT ENTRY
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $conn->prepare("
        INSERT INTO audit_logs 
        (audit_date, type, amount, description, payment_method)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST["audit_date"],
        $_POST["type"],
        $_POST["amount"],
        $_POST["description"],
        $_POST["payment_method"]
    ]);

    $success = "Audit record saved!";
}

/* ===============================
   FETCH AUDIT RECORDS
================================ */
$records = $conn->query("
    SELECT *
    FROM audit_logs
    ORDER BY audit_date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   TOTALS
================================ */
$totals = $conn->query("
    SELECT
        SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) AS total_out
    FROM audit_logs
")->fetch(PDO::FETCH_ASSOC);

$balance = ($totals["total_in"] ?? 0) - ($totals["total_out"] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Auditing</title>

<style>
body {
    margin:0;
    font-family:"Segoe UI", Arial;
    background:#eef7ff;
}

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
    font-size:14px;
    font-weight:bold;
}

.container { padding:25px }

.card {
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    margin-bottom:25px;
}

h2 { color:#4db8ff; margin-top:0 }

input, select {
    padding:8px;
    border-radius:6px;
    border:1px solid #ccc;
    width:100%;
}

.form-grid {
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap:15px;
}

button {
    margin-top:15px;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#4db8ff;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

.success { color:green; margin-bottom:10px }

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

th { background:#f0f8ff }

.in { color:green; font-weight:bold }
.out { color:red; font-weight:bold }
</style>
</head>

<body>

<div class="header">
    <div>💰 Financial Auditing</div>
    <a href="admin.php">⬅ Back to Admin</a>
</div>

<div class="container">

<!-- ================= ADD RECORD ================= -->
<div class="card">
<h2>Add Audit Record</h2>

<?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

<form method="post">
<div class="form-grid">

<div>
<label>Date</label>
<input type="date" name="audit_date" value="<?= date('Y-m-d') ?>" required>
</div>

<div>
<label>Type</label>
<select name="type" required>
    <option value="IN">Money In</option>
    <option value="OUT">Money Out</option>
</select>
</div>

<div>
<label>Amount</label>
<input type="number" step="0.01" name="amount" required>
</div>

<div>
<label>Payment Method</label>
<select name="payment_method" required>
    <option value="Cash">Cash</option>
    <option value="GCash">GCash</option>
    <option value="Bank">Bank</option>
</select>
</div>

<div style="grid-column: span 2;">
<label>Description / Source</label>
<input type="text" name="description" placeholder="e.g. Sunday Offering, Snacks, Sound System" required>
</div>

</div>

<button type="submit">Save Record</button>
</form>
</div>

<!-- ================= TOTAL SUMMARY ================= -->
<div class="card">
<h2>Financial Summary</h2>

<table>
<tr>
    <th>Total Money In</th>
    <th>Total Money Out</th>
    <th>Balance</th>
</tr>
<tr>
    <td class="in">₱<?= number_format($totals["total_in"] ?? 0, 2) ?></td>
    <td class="out">₱<?= number_format($totals["total_out"] ?? 0, 2) ?></td>
    <td><strong>₱<?= number_format($balance, 2) ?></strong></td>
</tr>
</table>
</div>

<!-- ================= RECORD LIST ================= -->
<div class="card">
<h2>Audit Records</h2>

<table>
<tr>
    <th>Date</th>
    <th>Type</th>
    <th>Amount</th>
    <th>Payment</th>
    <th>Description</th>
</tr>

<?php foreach ($records as $r): ?>
<tr>
    <td><?= $r["audit_date"] ?></td>
    <td class="<?= strtolower($r["type"]) ?>">
        <?= $r["type"] == "IN" ? "Money In" : "Money Out" ?>
    </td>
    <td>₱<?= number_format($r["amount"], 2) ?></td>
    <td><?= $r["payment_method"] ?></td>
    <td><?= htmlspecialchars($r["description"]) ?></td>
</tr>
<?php endforeach; ?>

<?php if (empty($records)): ?>
<tr><td colspan="5">No audit records yet</td></tr>
<?php endif; ?>
</table>

</div>

</div>

</body>
</html>
