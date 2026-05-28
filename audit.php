<?php
require "auth.php";
require "db.php";

/* ===============================
    PROTECT ADMIN
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    if (isset($_GET['action'])) {
        exit(json_encode(['error' => 'Unauthorized']));
    }
    header("Location: login.php");
    exit;
}

/* ===============================
    AJAX API HANDLER
================================ */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // 1. FETCH RECORDS & TOTALS
    if ($_GET['action'] === 'fetch') {
        $records = $conn->query("SELECT * FROM audit_logs ORDER BY audit_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $totals = $conn->query("
            SELECT 
                SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) AS total_in,
                SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) AS total_out
            FROM audit_logs
        ")->fetch(PDO::FETCH_ASSOC);
        $totals['total_offering'] = $conn->query("SELECT IFNULL(SUM(amount),0) FROM offerings")->fetchColumn();
        echo json_encode(['records' => $records, 'totals' => $totals]);
        exit;
    }

    // 2. SAVE (ADD/UPDATE)
    if ($_GET['action'] === 'save' && $_SERVER["REQUEST_METHOD"] === "POST") {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $stmt = $conn->prepare("UPDATE audit_logs SET audit_date=?, type=?, amount=?, description=?, payment_method=? WHERE id=?");
            $stmt->execute([$_POST["audit_date"], $_POST["type"], $_POST["amount"], $_POST["description"], $_POST["payment_method"], $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO audit_logs (audit_date, type, amount, description, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST["audit_date"], $_POST["type"], $_POST["amount"], $_POST["description"], $_POST["payment_method"]]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. DELETE
    if ($_GET['action'] === 'delete' && $_SERVER["REQUEST_METHOD"] === "POST") {
        $stmt = $conn->prepare("DELETE FROM audit_logs WHERE id=?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Auditing (AJAX)</title>
    <style>
        body { margin:0; font-family:"Segoe UI", Arial; background:#eef7ff; color: #333; }
        .header { background:linear-gradient(135deg,#4db8ff,#6fd3ff); padding:18px 25px; color:white; display:flex; justify-content:space-between; align-items:center; position: sticky; top:0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header a { color:white; text-decoration:none; font-size:14px; font-weight:bold; background: rgba(0,0,0,0.1); padding: 8px 12px; border-radius: 8px; }
        .container { padding:25px; max-width: 1200px; margin: auto; }
        .card { background:white; border-radius:16px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,.08); margin-bottom:25px; }
        h2 { color:#0088cc; border-left: 5px solid #4db8ff; padding-left: 10px; margin-top:0; font-size: 1.2rem; }
        
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px; margin-top: 15px; }
        label { font-size: 12px; font-weight: bold; color: #666; display: block; margin-bottom: 5px; }
        input, select { padding:10px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing: border-box; font-size: 14px; outline: none; }
        input:focus { border-color: #4db8ff; }
        
        .btn-save { padding:14px 25px; border:none; border-radius:12px; background:#2e7d32; color:white; font-weight:bold; cursor:pointer; width: 100%; margin-top: 15px; font-size: 16px; transition: 0.2s; }
        .btn-save:hover { background: #1b5e20; transform: translateY(-1px); }
        .btn-cancel { background:#999; color:white; border:none; padding:10px; border-radius:10px; margin-top:10px; cursor:pointer; width:100%; display:none; }

        table { width:100%; border-collapse:collapse; margin-top: 10px; }
        th, td { padding:12px; border-bottom:1px solid #eee; text-align:left; }
        th { background:#f8fcff; color:#0088cc; font-weight: bold; font-size: 13px; }
        
        .in-amt { color:#2e7d32; font-weight:bold }
        .out-amt { color:#d32f2f; font-weight:bold }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge-in { background: #e8f5e9; color: #2e7d32; }
        .badge-out { background: #ffebee; color: #c62828; }

        .loader { position: fixed; top: 0; left: 0; width: 100%; height: 4px; background: #eef7ff; z-index: 2000; overflow: hidden; display: none; }
        .loader-bar { width: 40%; height: 100%; background: #0088cc; position: absolute; animation: load 1.5s infinite linear; }
        @keyframes load { from { left: -40%; } to { left: 100%; } }
    </style>
</head>
<body>

<div id="loader" class="loader"><div class="loader-bar"></div></div>

<div class="header">
    <div>💰 Financial Auditing</div>
    <a href="admin.php">⬅ Back to Admin</a>
</div>

<div class="container">

    <div class="card">
        <div class="form-grid" style="text-align: center;" id="summaryContainer">
            <div><label>Total In</label><div class="in-amt" id="sumIn">₱0.00</div></div>
            <div><label>Total Offering</label><div class="in-amt" id="sumOffering">₱0.00</div></div>
            <div><label>Total Out</label><div class="out-amt" id="sumOut">₱0.00</div></div>
            <div><label>Current Balance</label><div style="font-weight:bold; font-size:18px;" id="sumBalance">₱0.00</div></div>
        </div>
    </div>

    <div class="card">
        <h2 id="formTitle">Add Audit Record</h2>
        <form id="auditForm">
            <input type="hidden" name="id" id="recordId">
            <div class="form-grid">
                <div><label>Date</label><input type="date" name="audit_date" id="fDate" value="<?= date('Y-m-d') ?>" required></div>
                <div><label>Type</label>
                    <select name="type" id="fType">
                        <option value="IN">Money In (+)</option>
                        <option value="OUT">Money Out (-)</option>
                    </select>
                </div>
                <div><label>Amount (₱)</label><input type="number" step="0.01" name="amount" id="fAmount" placeholder="0.00" required></div>
                <div><label>Payment Method</label>
                    <select name="payment_method" id="fMethod">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank">Bank Transfer</option>
                    </select>
                </div>
                <div style="grid-column: span 2;"><label>Description / Source</label><input type="text" name="description" id="fDesc" placeholder="e.g. Monthly Offering / Electricity Bill" required></div>
            </div>
            <button type="submit" class="btn-save" id="saveBtn">💾 Save Transaction</button>
            <button type="button" id="cancelBtn" class="btn-cancel" onclick="resetForm()">Cancel Edit</button>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h2>Recent History</h2>
            <input type="text" id="searchInput" placeholder="Search by description..." style="width: 250px;">
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Type</th><th>Amount</th><th>Method</th><th>Description</th><th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="recordTable"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const auditForm = document.getElementById('auditForm');
const recordTable = document.getElementById('recordTable');
const loader = document.getElementById('loader');

// Initialization
document.addEventListener('DOMContentLoaded', refreshData);

async function refreshData() {
    loader.style.display = 'block';
    try {
        const response = await fetch('audit.php?action=fetch');
        const data = await response.json();
        
        // Render Summary
        const totalIn = parseFloat(data.totals.total_in || 0);
        const totalOffering = parseFloat(data.totals.total_offering || 0);
        const totalOut = parseFloat(data.totals.total_out || 0);
        const balance = totalIn + totalOffering - totalOut;
        document.getElementById('sumIn').innerText = '₱' + parseFloat(data.totals.total_in || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('sumOffering').innerText = '₱' + totalOffering.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('sumOut').innerText = '₱' + parseFloat(data.totals.total_out || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('sumBalance').innerText = '₱' + balance.toLocaleString(undefined, {minimumFractionDigits: 2});

        // Render Table
        recordTable.innerHTML = data.records.map(r => `
            <tr>
                <td>${r.audit_date}</td>
                <td><span class="badge ${r.type == 'IN' ? 'badge-in' : 'badge-out'}">${r.type}</span></td>
                <td class="${r.type == 'IN' ? 'in-amt' : 'out-amt'}">₱${parseFloat(r.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td>${r.payment_method}</td>
                <td>${r.description}</td>
                <td style="text-align:right;">
                    <a href="javascript:void(0)" onclick='prepareEdit(${JSON.stringify(r)})' style="color:#0088cc; text-decoration:none; font-weight:bold; margin-right:10px;">Edit</a>
                    <a href="javascript:void(0)" onclick="deleteEntry(${r.id})" style="color:#ff4d4d; text-decoration:none; font-weight:bold;">Delete</a>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" style="text-align:center;">No records found.</td></tr>';

    } catch (e) { console.error("Fetch failed", e); }
    loader.style.display = 'none';
}

// Save Entry
auditForm.onsubmit = async (e) => {
    e.preventDefault();
    loader.style.display = 'block';
    const formData = new FormData(auditForm);
    
    await fetch('audit.php?action=save', { method: 'POST', body: formData });
    
    resetForm();
    refreshData();
};

// Delete Entry
async function deleteEntry(id) {
    if(!confirm("Are you sure you want to delete this transaction?")) return;
    
    loader.style.display = 'block';
    const formData = new FormData();
    formData.append('id', id);
    
    await fetch('audit.php?action=delete', { method: 'POST', body: formData });
    refreshData();
}

// Edit Mode
function prepareEdit(r) {
    document.getElementById('recordId').value = r.id;
    document.getElementById('fDate').value = r.audit_date;
    document.getElementById('fType').value = r.type;
    document.getElementById('fAmount').value = r.amount;
    document.getElementById('fMethod').value = r.payment_method;
    document.getElementById('fDesc').value = r.description;
    
    document.getElementById('formTitle').innerText = "Edit Transaction #" + r.id;
    document.getElementById('saveBtn').innerText = "💾 Update Transaction";
    document.getElementById('cancelBtn').style.display = "block";
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    auditForm.reset();
    document.getElementById('recordId').value = "";
    document.getElementById('formTitle').innerText = "Add Audit Record";
    document.getElementById('saveBtn').innerText = "💾 Save Transaction";
    document.getElementById('cancelBtn').style.display = "none";
}

// Local Search Filter
document.getElementById('searchInput').onkeyup = function() {
    const val = this.value.toLowerCase();
    const rows = recordTable.getElementsByTagName('tr');
    Array.from(rows).forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
};
</script>

</body>
</html>
