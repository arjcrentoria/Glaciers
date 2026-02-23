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
   AJAX: ADD POINTS (BULK)
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "bulk_add") {
    $stmt = $conn->prepare("
        INSERT INTO points (member_id, points, reason, created_at)
        VALUES (?, ?, ?, datetime('now'))
    ");

    foreach ($_POST["points"] as $member_id => $pts) {
        if ($pts === "" || !is_numeric($pts)) continue;
        $reason = $_POST["reason"][$member_id] ?? "";
        $stmt->execute([$member_id, (int)$pts, $reason]);
    }

    echo "OK";
    exit;
}

/* ===============================
   AJAX: ADD POINT (INDIVIDUAL)
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
   AJAX: DELETE
================================ */
if (($_POST["action"] ?? "") === "delete") {
    $stmt = $conn->prepare("DELETE FROM points WHERE id = ?");
    $stmt->execute([$_POST["id"]]);
    echo "OK";
    exit;
}

/* ===============================
   AJAX: EDIT
================================ */
if (($_POST["action"] ?? "") === "edit") {
    $stmt = $conn->prepare("
        UPDATE points SET points = ?, reason = ?
        WHERE id = ?
    ");
    $stmt->execute([
        (int)$_POST["points"],
        $_POST["reason"],
        $_POST["id"]
    ]);
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
   FETCH RECORDS
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
<html>
<head>
<meta charset="UTF-8">
<title>Points System</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

<style>
* { box-sizing:border-box; font-family:"Segoe UI", Arial, sans-serif }
body { margin:0; background:#eef7ff; color:#333 }

.header {
    background:#9c27b0;
    padding:16px;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    position: sticky;
    top: 0;
    z-index: 100;
}

.container { padding:15px }

.card {
    background:white;
    border-radius:14px;
    padding:15px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    margin-bottom:20px;
}

/* Mobile-friendly table container */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table { width:100%; border-collapse:collapse; font-size:14px; min-width: 500px; }
th,td { padding:12px 10px; border-bottom:1px solid #eee; text-align: center; }

th { background:#f3e5f5; position: sticky; top: 0; }

/* Sticky Name Column */
.sticky-col {
    position: sticky;
    left: 0;
    background: white;
    z-index: 2;
    text-align: left;
    min-width: 120px;
    font-weight: bold;
}
th.sticky-col { background: #f3e5f5; z-index: 3; }

tr:hover td { background:#f9f5fc }
tr.selected td { background:#d1b3e0 !important }

input { 
    width:100%; 
    padding:8px; 
    border: 1px solid #ccc; 
    border-radius: 4px;
    font-size: 14px;
}

button {
    padding:8px 12px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight: bold;
    transition: 0.2s;
}

.add { background:#9c27b0; color:white }
.add:active { background: #7b1fa2; transform: scale(0.95); }

.edit { background:#0284c7; color:white }
.del  { background:#f44336; color:white }

.btn-reset { background: #ff5252; color: white; padding: 6px 12px; font-size: 13px; }

@media (max-width:600px){
    table { font-size:13px }
    .container { padding: 10px; }
}
</style>
</head>

<body>

<div class="header">
    <strong>🏆 Points System</strong>
    <div>
        <button class="btn-reset" onclick="resetAll()">Reset All</button>
        <a href="admin.php" style="color:white;text-decoration:none; margin-left:10px;">← Admin</a>
    </div>
</div>

<div class="container">

<div class="card">
<h3 style="margin-top:0">Add Points</h3>

<form id="bulkForm">
<input type="hidden" name="action" value="bulk_add">

<div class="table-responsive">
<table>
<tr>
    <th class="sticky-col">Name</th>
    <th>Points</th>
    <th>Reason</th>
    <th>Total</th>
    <th>Action</th>
</tr>

<?php foreach ($members as $m): ?>
<tr onclick="toggleRow(this, event)">
    <td class="sticky-col"><?= htmlspecialchars($m["full_name"]) ?></td>
    <td><input type="number" name="points[<?= $m["id"] ?>]" placeholder="Pts"></td>
    <td><input type="text" name="reason[<?= $m["id"] ?>]" placeholder="Why?"></td>
    <td><strong><?= $m["total_points"] ?></strong></td>
    <td>
        <button type="button" class="add" onclick="addSingle(<?= $m['id'] ?>, this)">Add</button>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<br>
<button class="add" style="width:100%; padding: 15px;">Save Bulk / Selected</button>
</form>
</div>

<div class="card">
<h3 style="margin-top:0">Point Records</h3>

<div class="table-responsive">
<table>
<tr>
    <th class="sticky-col">Name</th>
    <th>Pts</th>
    <th>Reason</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php foreach ($records as $r): ?>
<tr>
    <td class="sticky-col"><?= htmlspecialchars($r["full_name"]) ?></td>
    <td><input type="number" value="<?= $r["points"] ?>" style="width:60px"></td>
    <td><input type="text" value="<?= htmlspecialchars($r["reason"]) ?>"></td>
    <td><small><?= date("m/d H:i", strtotime($r["created_at"])) ?></small></td>
    <td style="min-width: 100px;">
        <button class="edit" onclick="editPoint(<?= $r['id'] ?>, this)">✏️</button>
        <button class="del" onclick="deletePoint(<?= $r['id'] ?>)">🗑</button>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

</div>

<script>
// RESET ALL
function resetAll() {
    if(!confirm("⚠️ DANGER: This will delete ALL point history for EVERYONE. You cannot undo this.\n\nAre you absolutely sure?")) return;
    
    fetch("points.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=reset_all"
    }).then(() => location.reload());
}

// MULTI-SELECT (Works with tap/click)
function toggleRow(row, e){
    if(e.ctrlKey){
        row.classList.toggle("selected");
    } else {
        document.querySelectorAll("tr.selected").forEach(r=>r.classList.remove("selected"));
        row.classList.add("selected");
    }
}

// BULK ADD
document.getElementById("bulkForm").onsubmit = e => {
    e.preventDefault();
    fetch("points.php", {
        method:"POST",
        body:new FormData(e.target)
    }).then(()=>location.reload());
};

// INDIVIDUAL ADD
function addSingle(id, btn){
    let row = btn.closest("tr");
    let inputs = row.querySelectorAll("input");

    fetch("points.php", {
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:
            "action=single_add" +
            "&member_id=" + id +
            "&points=" + inputs[0].value +
            "&reason=" + encodeURIComponent(inputs[1].value)
    }).then(()=>location.reload());
}

// EDIT
function editPoint(id, btn){
    let row = btn.closest("tr");
    let i = row.querySelectorAll("input");

    fetch("points.php", {
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:
            "action=edit&id="+id+
            "&points="+i[0].value+
            "&reason="+encodeURIComponent(i[1].value)
    }).then(()=>location.reload());
}

// DELETE
function deletePoint(id){
    if(!confirm("Delete this specific record?")) return;
    fetch("points.php",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"action=delete&id="+id
    }).then(()=>location.reload());
}
</script>

</body>
</html>