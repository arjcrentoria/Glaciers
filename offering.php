<?php
require "auth.php";
require "db.php";

/* ===============================
    PROTECT ADMIN PAGE
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
    GLOBAL DELETE HANDLER
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['global_reset_all'])) {
    try {
        $conn->exec("DELETE FROM offerings");
        $success = "🧨 ALL offering records have been permanently deleted.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ===============================
    FETCH MEMBERS
================================ */
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
    GET ALL SUNDAYS OF 2026
================================ */
$sundays = [];
$date = new DateTime("2026-01-01");
$date->modify("next sunday");
while ($date->format("Y") == "2026") {
    $sundays[] = $date->format("Y-m-d");
    $date->modify("+7 days");
}

/* ===============================
    SAVE DATA LOGIC (MANUAL UPSERT)
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['global_reset_all'])) {
    // 1. Handling Sunday Offerings
    if (!empty($_POST["offering"])) {
        foreach ($_POST["offering"] as $member_id => $dates) {
            foreach ($dates as $offering_date => $amount) {
                if ($amount === "" || !is_numeric($amount)) continue;
                
                // Check if record exists for this Sunday (event_name is NULL)
                $check = $conn->prepare("SELECT id FROM offerings WHERE member_id = ? AND offering_date = ? AND event_name IS NULL");
                $check->execute([$member_id, $offering_date]);
                $exists = $check->fetch();

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE offerings SET amount = ? WHERE id = ?");
                    $stmt->execute([$amount, $exists['id']]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO offerings (member_id, offering_date, amount, event_name) VALUES (?, ?, ?, NULL)");
                    $stmt->execute([$member_id, $offering_date, $amount]);
                }
            }
        }
    }

    // 2. Handling Special Event Offerings
    if (!empty($_POST["event_name"]) && !empty($_POST["event_date"])) {
        $event_name = $_POST["event_name"];
        $event_date = $_POST["event_date"];
        foreach ($_POST["special"] ?? [] as $member_id => $amount) {
            if ($amount === "" || !is_numeric($amount)) continue;
            
            $check = $conn->prepare("SELECT id FROM offerings WHERE member_id = ? AND offering_date = ? AND event_name = ?");
            $check->execute([$member_id, $event_date, $event_name]);
            $exists = $check->fetch();

            if ($exists) {
                $stmt = $conn->prepare("UPDATE offerings SET amount = ? WHERE id = ?");
                $stmt->execute([$amount, $exists['id']]);
            } else {
                $stmt = $conn->prepare("INSERT INTO offerings (member_id, offering_date, amount, event_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$member_id, $event_date, $amount, $event_name]);
            }
        }
    }
    $success = "✅ Offerings saved successfully!";
}

/* ===============================
    FETCH EXISTING DATA FOR DISPLAY
================================ */
$existing = [];
$stmt = $conn->query("SELECT * FROM offerings WHERE event_name IS NULL");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row["member_id"]][$row["offering_date"]] = $row["amount"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offerings 2026</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { margin: 0; background: #eef7ff; color: #333; }
        
        .header { 
            background: linear-gradient(135deg, #4db8ff, #6fd3ff); 
            padding: 16px 20px; color: white; 
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .header-btns { display: flex; gap: 10px; }
        .header a, .btn-danger-top { color: white; text-decoration: none; font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 13px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-nav { background: rgba(0,0,0,0.1); }
        .btn-danger-top { background: #d32f2f; }
        
        .container { padding: 20px; max-width: 1400px; margin: auto; }
        .card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 25px; }
        h2 { color: #0088cc; border-left: 5px solid #4db8ff; padding-left: 10px; margin-top: 0; }
        
        .table-wrap { width: 100%; max-height: 70vh; overflow: auto; border: 1px solid #eee; border-radius: 10px; }
        table { border-collapse: separate; border-spacing: 0; font-size: 13px; min-width: 1200px; width: 100%; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; border-right: 1px solid #f0f0f0; text-align: center; }

        th { background: #f0f8ff; position: sticky; top: 0; z-index: 100; border-bottom: 2px solid #4db8ff; }
        .sticky-name { position: sticky; left: 0; background: white; z-index: 110; text-align: left !important; font-weight: bold; border-right: 2px solid #eef7ff; min-width: 180px; }
        th.sticky-name { z-index: 120; background: #f0f8ff; top: 0; }
        
        tr.selected td, tr.selected .sticky-name { background-color: #d1ecff !important; }
        tr:hover td:not(.sticky-name) { background-color: #f5fbff; }
        
        input { padding: 6px; border-radius: 6px; border: 1px solid #ccc; width: 85px; text-align: center; }
        .btn-save { padding: 14px 25px; border: none; border-radius: 12px; background: #2e7d32; color: white; font-weight: bold; cursor: pointer; width: 100%; font-size: 16px; margin-top: 15px; transition: 0.3s; }
        .btn-save:hover { background: #1b5e20; transform: translateY(-2px); }
        
        .msg { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: bold; border: 1px solid #ccc; }
        .success { background: #e8f5e9; color: #2e7d32; border-color: #c8e6c9; }
        .error { background: #ffebee; color: #c62828; border-color: #ef9a9a; }
    </style>
</head>
<body>

<div class="header">
    <div>❄ 2026 Offerings Dashboard</div>
    <div class="header-btns">
        <form method="post" onsubmit="return confirm('CRITICAL: Delete ALL records?')">
            <input type="hidden" name="global_reset_all" value="1">
            <button type="submit" class="btn-danger-top">⚠️ Reset All Data</button>
        </form>
        <a href="admin.php" class="btn-nav">← Back</a>
    </div>
</div>

<div class="container">
    <?php if (isset($success)) echo "<div class='msg success'>$success</div>"; ?>
    <?php if (isset($error)) echo "<div class='msg error'>$error</div>"; ?>
    
    <div class="card">
        <h2>Sunday Offerings (2026)</h2>
        <form method="post" onsubmit="cleanForm(this)">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="sticky-name">Full Name</th>
                            <?php foreach ($sundays as $d): ?>
                                <th><?= date("M j", strtotime($d)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr onclick="toggleRow(this, event)">
                            <td class="sticky-name"><?= htmlspecialchars($m["full_name"]) ?></td>
                            <?php foreach ($sundays as $d): ?>
                            <td>
                                <input type="number" step="0.01" 
                                       name="offering[<?= $m["id"] ?>][<?= $d ?>]" 
                                       value="<?= $existing[$m["id"]][$d] ?? "" ?>" 
                                       onclick="event.stopPropagation()">
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn-save">💾 Save Sunday Offerings</button>
        </form>
    </div>

    <div class="card">
        <h2>Special Event Offering</h2>
        <form method="post" onsubmit="cleanForm(this)">
            <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items:center;">
                <input type="text" name="event_name" placeholder="Event Name" required style="width: 250px; text-align:left;">
                <input type="date" name="event_date" required style="width: 150px;">
            </div>
            <div class="table-wrap">
                <table style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th class="sticky-name">Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr onclick="toggleRow(this, event)">
                            <td class="sticky-name"><?= htmlspecialchars($m["full_name"]) ?></td>
                            <td>
                                <input type="number" step="0.01" name="special[<?= $m["id"] ?>]" 
                                       style="width: 150px;" onclick="event.stopPropagation()">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn-save" style="background: #ef6c00;">💰 Save Special Event Offering</button>
        </form>
    </div>
</div>

<script>
function cleanForm(f){ 
    f.querySelectorAll('input[type="number"]').forEach(i => {
        if(i.value === "") i.disabled = true;
    }); 
}
function toggleRow(r, e){ 
    if(!e.ctrlKey) document.querySelectorAll('tr.selected').forEach(x => x.classList.remove('selected')); 
    r.classList.toggle('selected'); 
}
</script>
</body>
</html>