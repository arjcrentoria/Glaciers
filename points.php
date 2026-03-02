<?php
require "auth.php";
require "db.php";

/* ===============================
   ADMIN ONLY
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===============================
   AJAX: RESET ALL POINTS
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "reset_all") {
    $conn->exec("DELETE FROM points");
    echo "OK";
    exit;
}

/* ===============================
   AJAX: BULK ADD
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "bulk_add") {
    $stmt = $conn->prepare("
        INSERT INTO points (member_id, points, reason, created_at)
        VALUES (?, ?, ?, datetime('now'))
    ");

    foreach ($_POST["points"] as $member_id => $pts) {
        if ($pts === "" || !is_numeric($pts)) continue;

        $stmt->execute([
            (int)$member_id,
            (int)$pts,
            $_POST["reason"][$member_id] ?? ""
        ]);
    }

    echo "OK";
    exit;
}

/* ===============================
   AJAX: SINGLE ADD
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "single_add") {
    $stmt = $conn->prepare("
        INSERT INTO points (member_id, points, reason, created_at)
        VALUES (?, ?, ?, datetime('now'))
    ");

    $stmt->execute([
        (int)$_POST["member_id"],
        (int)$_POST["points"],
        $_POST["reason"] ?? ""
    ]);

    echo "OK";
    exit;
}

/* ===============================
   AJAX: EDIT
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "edit") {
    $stmt = $conn->prepare("
        UPDATE points
        SET points = ?, reason = ?
        WHERE id = ?
    ");

    $stmt->execute([
        (int)$_POST["points"],
        $_POST["reason"],
        (int)$_POST["id"]
    ]);

    echo "OK";
    exit;
}

/* ===============================
   AJAX: DELETE
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $stmt = $conn->prepare("DELETE FROM points WHERE id = ?");
    $stmt->execute([(int)$_POST["id"]]);

    echo "OK";
    exit;
}

/* ===============================
   FETCH MEMBERS + TOTALS
================================ */
$members = $conn->query("
    SELECT
        m.id,
        m.full_name,
        IFNULL(SUM(p.points),0) AS total_points
    FROM members m
    LEFT JOIN points p ON p.member_id = m.id
    GROUP BY m.id
    ORDER BY m.full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FETCH POINT RECORDS
================================ */
$records = $conn->query("
    SELECT
        p.id,
        m.full_name,
        p.points,
        p.reason,
        p.created_at
    FROM points p
    JOIN members m ON m.id = p.member_id
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Points System</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{box-sizing:border-box;font-family:"Segoe UI",Arial}
body{margin:0;background:#eef7ff}
.header{
    background:#9c27b0;color:#fff;padding:15px;
    display:flex;justify-content:space-between;align-items:center;
    position:sticky;top:0;z-index:100
}
.container{padding:15px}
.card{
    background:#fff;border-radius:14px;padding:15px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    margin-bottom:20px
}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:600px}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:center}
th{background:#f3e5f5;position:sticky;top:0}
.sticky{position:sticky;left:0;background:#fff;font-weight:bold}
button{
    padding:8px 12px;border:none;border-radius:8px;
    cursor:pointer;font-weight:bold
}
.add{background:#9c27b0;color:#fff}
.edit{background:#0284c7;color:#fff}
.del{background:#f44336;color:#fff}
.reset{background:#ff5252;color:#fff}
input{width:100%;padding:6px}
</style>
</head>

<body>

<div class="header">
    <strong>🏆 Points System</strong>
    <div>
        <button class="reset" onclick="resetAll()">Reset All</button>
        <a href="admin.php" style="color:white;text-decoration:none;margin-left:10px">← Admin</a>
    </div>
</div>

<div class="container">

<div class="card">
<h3>Add Points</h3>

<form id="bulkForm">
<input type="hidden" name="action" value="bulk_add">

<div class="table-wrap">
<table>
<tr>
    <th class="sticky">Name</th>
    <th>Points</th>
    <th>Reason</th>
    <th>Total</th>
    <th>Action</th>
</tr>
<?php foreach($members as $m): ?>
<tr>
    <td class="sticky"><?= htmlspecialchars($m["full_name"]) ?></td>
    <td><input type="number" name="points[<?= $m['id'] ?>]"></td>
    <td><input type="text" name="reason[<?= $m['id'] ?>]"></td>
    <td><strong><?= $m["total_points"] ?></strong></td>
    <td>
        <button type="button" class="add" onclick="addSingle(<?= $m['id'] ?>,this)">Add</button>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<br>
<button class="add" style="width:100%">Save Bulk</button>
</form>
</div>

<div class="card">
<h3>Point Records</h3>

<div class="table-wrap">
<table>
<tr>
    <th class="sticky">Name</th>
    <th>Pts</th>
    <th>Reason</th>
    <th>Date</th>
    <th>Action</th>
</tr>
<?php foreach($records as $r): ?>
<tr>
    <td class="sticky"><?= htmlspecialchars($r["full_name"]) ?></td>
    <td><input type="number" value="<?= $r["points"] ?>"></td>
    <td><input type="text" value="<?= htmlspecialchars($r["reason"]) ?>"></td>
    <td><?= date("m/d H:i", strtotime($r["created_at"])) ?></td>
    <td>
        <button class="edit" onclick="editPoint(<?= $r['id'] ?>,this)">✏️</button>
        <button class="del" onclick="deletePoint(<?= $r['id'] ?>)">🗑</button>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

</div>

<script>
function resetAll(){
    if(!confirm("Delete ALL point history?")) return;
    fetch("points.php",{method:"POST",body:"action=reset_all",
        headers:{"Content-Type":"application/x-www-form-urlencoded"}})
        .then(()=>location.reload());
}

document.getElementById("bulkForm").onsubmit=e=>{
    e.preventDefault();
    fetch("points.php",{method:"POST",body:new FormData(e.target)})
        .then(()=>location.reload());
};

function addSingle(id,btn){
    let r=btn.closest("tr").querySelectorAll("input");
    fetch("points.php",{method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"action=single_add&member_id="+id+
             "&points="+r[0].value+
             "&reason="+encodeURIComponent(r[1].value)})
        .then(()=>location.reload());
}

function editPoint(id,btn){
    let r=btn.closest("tr").querySelectorAll("input");
    fetch("points.php",{method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"action=edit&id="+id+
             "&points="+r[0].value+
             "&reason="+encodeURIComponent(r[1].value)})
        .then(()=>location.reload());
}

function deletePoint(id){
    if(!confirm("Delete this record?"))return;
    fetch("points.php",{method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"action=delete&id="+id})
        .then(()=>location.reload());
}
</script>

</body>
</html>