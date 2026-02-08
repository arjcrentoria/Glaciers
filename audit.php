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
    ADD RECORD
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add"])) {
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
    header("Location: audit.php?success=1");
    exit;
}

/* ===============================
    UPDATE RECORD
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $stmt = $conn->prepare("
        UPDATE audit_logs 
        SET audit_date=?, type=?, amount=?, description=?, payment_method=? 
        WHERE id=?
    ");
    $stmt->execute([
        $_POST["audit_date"],
        $_POST["type"],
        $_POST["amount"],
        $_POST["description"],
        $_POST["payment_method"],
        $_POST["id"]
    ]);
    header("Location: audit.php?updated=1");
    exit;
}

/* ===============================
    DELETE RECORD
================================ */
if (isset($_GET["delete"])) {
    $stmt = $conn->prepare("DELETE FROM audit_logs WHERE id=?");
    $stmt->execute([$_GET["delete"]]);
    header("Location: audit.php?deleted=1");
    exit;
}

/* ===============================
    EDIT MODE DATA FETCHING
================================ */
$edit = null;
if (isset($_GET["edit"])) {
    $stmt = $conn->prepare("SELECT * FROM audit_logs WHERE id=?");
    $stmt->execute([$_GET["edit"]]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ===============================
    FETCH ALL RECORDS & TOTALS
================================ */
$records = $conn->query("SELECT * FROM audit_logs ORDER BY audit_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Financial Auditing</title>
    <style>
        body { margin:0; font-family:"Segoe UI", Arial; background:#eef7ff; }
        .header { background:linear-gradient(135deg,#4db8ff,#6fd3ff); padding:18px 25px; color:white; display:flex; justify-content:space-between; align-items:center; font-size:22px; }
        .header a { color:white; text-decoration:none; font-size:14px; font-weight:bold; }
        .container { padding:25px }
        .card { background:white; border-radius:16px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,.08); margin-bottom:25px; }
        h2 { color:#4db8ff; margin-top:0 }
        input, select { padding:8px; border-radius:6px; border:1px solid #ccc; width:100%; box-sizing: border-box; }
        .form-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; }
        button { margin-top:15px; padding:12px 25px; border:none; border-radius:10px; background:#4db8ff; color:white; font-weight:bold; cursor:pointer; }
        .btn-cancel { background:#ccc; color:#333; text-decoration:none; padding:10px 20px; border-radius:10px; font-size:14px; margin-left:10px; }
        .success { color:green; margin-bottom:10px; font-weight:bold; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:12px; border-bottom:1px solid #eee; text-align:center; }
        th { background:#f0f8ff; color:#555; }
        .in { color:green; font-weight:bold }
        .out { color:red; font-weight:bold }
        .action-links a { text-decoration:none; font-weight:bold; margin:0 5px; }
        .edit-link { color:#4db8ff; }
        .delete-link { color:#ff4d4d; }
    </style>
</head>
<body>

<div class="header">
    <div>💰 Financial Auditing</div>
    <a href="admin.php">⬅ Back to Admin</a>
</div>

<div class="container">

    <div class="card">
        <h2><?= $edit ? "Edit Audit Record" : "Add Audit Record" ?></h2>

        <?php if (isset($_GET["success"])): ?> <div class="success">✓ Record saved successfully!</div> <?php endif; ?>
        <?php if (isset($_GET["updated"])): ?> <div class="success">✓ Record updated successfully!</div> <?php endif; ?>
        <?php if (isset($_GET["deleted"])): ?> <div class="success" style="color:orange;">✓ Record deleted.</div> <?php endif; ?>

        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div class="form-grid">
                <div>
                    <label>Date</label>
                    <input type="date" name="audit_date" value="<?= $edit['audit_date'] ?? date('Y-m-d') ?>" required>
                </div>

                <div>
                    <label>Type</label>
                    <select name="type" required>
                        <option value="IN" <?= (isset($edit['type']) && $edit['type'] == 'IN') ? 'selected' : '' ?>>Money In</option>
                        <option value="OUT" <?= (isset($edit['type']) && $edit['type'] == 'OUT') ? 'selected' : '' ?>>Money Out</option>
                    </select>
                </div>

                <div>
                    <label>Amount</label>
                    <input type="number" step="0.01" name="amount" value="<?= $edit['amount'] ?? '' ?>" required>
                </div>

                <div>
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="Cash" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="GCash" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'GCash') ? 'selected' : '' ?>>GCash</option>
                        <option value="Bank" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Bank') ? 'selected' : '' ?>>Bank</option>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label>Description / Source</label>
                    <input type="text" name="description" value="<?= $edit['description'] ?? '' ?>" required>
                </div>
            </div>

            <button type="submit" name="<?= $edit ? 'update' : 'add' ?>">
                <?= $edit ? "Update Record" : "Save Record" ?>
            </button>
            
            <?php if ($edit): ?>
                <a href="audit.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

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

    <div class="card">
        <h2>Audit Records</h2>
        <table>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Description</th>
                <th>Actions</th>
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
                <td class="action-links">
                    <a href="?edit=<?= $r["id"] ?>" class="edit-link">Edit</a> | 
                    <a href="?delete=<?= $r["id"] ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($records)): ?>
            <tr><td colspan="6">No audit records yet</td></tr>
            <?php endif; ?>
        </table>
    </div>

</div>
</body>
</html>