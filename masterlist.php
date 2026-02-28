<?php
require "auth.php";
require "db.php";

/* PROTECT ADMIN */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    if (isset($_GET['action'])) exit(json_encode(['error' => 'Unauthorized']));
    header("Location: login.php");
    exit;
}

/* API HANDLER */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 1. FETCH MEMBERS (Sorted A-Z, handles nulls as blanks)
    if ($_GET['action'] === 'fetch') {
      $members = $conn->query(
    "SELECT * FROM members ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($members);
        exit;
    }

    // 2. SAVE (ADD/UPDATE)
    if ($_GET['action'] === 'save' && $_SERVER["REQUEST_METHOD"] === "POST") {
        $id = $_POST['id'] ?? null;
        
        // Ensure values are strings, not null, and format names
        $full_name = ucwords(strtolower(trim($_POST["full_name"] ?? '')));
        $age       = $_POST["age"] ?? '';
        $birthday  = $_POST["birthday"] ?? '';
        $address   = trim($_POST["address"] ?? '');
        $contact   = trim($_POST["contact"] ?? '');
        $facebook  = trim($_POST["facebook"] ?? '');
        
        if ($id) {
            $stmt = $conn->prepare("UPDATE members SET full_name=?, age=?, birthday=?, address=?, contact=?, facebook=? WHERE id=?");
            $stmt->execute([$full_name, $age, $birthday, $address, $contact, $facebook, $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO members (full_name, age, birthday, address, contact, facebook) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $age, $birthday, $address, $contact, $facebook]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. DELETE
    if ($_GET['action'] === 'delete' && $_SERVER["REQUEST_METHOD"] === "POST") {
        $stmt = $conn->prepare("DELETE FROM members WHERE id=?");
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
    <title>Masterlist</title>
    <style>
        body { margin:0; font-family:"Segoe UI", Arial; background:#eef7ff; color: #333; }
        .header { background:linear-gradient(135deg,#4db8ff,#6fd3ff); padding:16px 20px; color:white; display:flex; justify-content:space-between; align-items:center; position: sticky; top:0; z-index: 1000; }
        .header a { color:white; text-decoration:none; font-weight:bold; background: rgba(0,0,0,0.1); padding: 8px 12px; border-radius: 8px; font-size: 13px; }
        .container { padding:20px; max-width: 1100px; margin: auto; }
        .card { background:white; border-radius:16px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,.05); margin-bottom:25px; }
        h2 { color:#0088cc; border-left: 5px solid #4db8ff; padding-left: 10px; margin-top:0; font-size: 1.2rem; }
        
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-top: 15px; }
        .full-width { grid-column: 1 / -1; }
        label { font-size: 11px; font-weight: bold; color: #666; margin-bottom: 4px; display: block; }
        input { padding:10px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing: border-box; font-size: 14px; outline:none; }
        
        .btn-save { padding:14px; border:none; border-radius:12px; background:#2e7d32; color:white; font-weight:bold; cursor:pointer; width: 100%; margin-top: 15px; font-size: 16px; transition: 0.3s; }
        .btn-save:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-cancel { background:#999; color:white; border:none; padding:10px; border-radius:10px; margin-top:8px; cursor:pointer; width:100%; display:none; }

        .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid #eee; }
        table { width:100%; border-collapse:collapse; min-width: 800px; }
        th, td { padding:12px; border-bottom:1px solid #eee; text-align:left; font-size: 13px; }
        th { background:#f8fcff; color:#0088cc; font-weight: bold; }
        tr:hover { background: #f9feff; }
    </style>
</head>
<body>

<div class="header">
    <div>❄ Church Masterlist</div>
    <a href="admin.php">⬅ Back</a>
</div>

<div class="container">
    <div class="card">
        <h2 id="formTitle">Add New Member</h2>
        <form id="memberForm">
            <input type="hidden" name="id" id="mId">
            <div class="form-grid">
                <div class="full-width"><label>Full Name</label><input type="text" name="full_name" id="mName" placeholder="Lastname, Firstname, Middle" required></div>
                <div><label>Age</label><input type="number" name="age" id="mAge"></div>
                <div><label>Birthday</label><input type="date" name="birthday" id="mBirth"></div>
                <div><label>Contact</label><input type="text" name="contact" id="mContact"></div>
                <div><label>Facebook</label><input type="text" name="facebook" id="mFB"></div>
                <div class="full-width"><label>Home Address</label><input type="text" name="address" id="mAddr"></div>
            </div>
            <button type="submit" class="btn-save" id="saveBtn">💾 Save Member</button>
            <button type="button" id="cancelBtn" class="btn-cancel" onclick="resetForm()">Cancel Edit</button>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
            <h2>Members List</h2>
            <input type="text" id="search" placeholder="Search name..." style="width:250px;">
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th><th>Age</th><th>Birthday</th><th>Address</th><th>Contact</th><th>Facebook</th><th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="memberTable"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const memberForm = document.getElementById('memberForm');
const memberTable = document.getElementById('memberTable');

document.addEventListener('DOMContentLoaded', fetchMembers);

async function fetchMembers() {
    const res = await fetch('masterlist.php?action=fetch');
    let data = await res.json();
    
    data.sort((a, b) => a.full_name.localeCompare(b.full_name));

    memberTable.innerHTML = data.map(m => `
        <tr>
            <td style="font-weight:bold;">${m.full_name || ''}</td>
            <td>${m.age || ''}</td>
            <td>${m.birthday || ''}</td>
            <td>${m.address || ''}</td>
            <td>${m.contact || ''}</td>
            <td>${m.facebook || ''}</td>
            <td style="text-align:right;">
                <button onclick='prepareEdit(${JSON.stringify(m)})' style="color:#0088cc; background:none; border:none; font-weight:bold; cursor:pointer;">Edit</button> | 
                <button onclick="deleteMember(${m.id})" style="color:#ff4d4d; background:none; border:none; font-weight:bold; cursor:pointer;">Delete</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="7" style="text-align:center;">No members found.</td></tr>';
}

memberForm.onsubmit = async (e) => {
    e.preventDefault();
    await fetch('masterlist.php?action=save', { method: 'POST', body: new FormData(memberForm) });
    resetForm();
    fetchMembers();
};

async function deleteMember(id) {
    if(!confirm("Are you sure?")) return;
    const fd = new FormData(); fd.append('id', id);
    await fetch('masterlist.php?action=delete', { method: 'POST', body: fd });
    fetchMembers();
}

function prepareEdit(m) {
    document.getElementById('mId').value = m.id;
    document.getElementById('mName').value = m.full_name || '';
    document.getElementById('mAge').value = m.age || '';
    document.getElementById('mBirth').value = m.birthday || '';
    document.getElementById('mContact').value = m.contact || '';
    document.getElementById('mFB').value = m.facebook || '';
    document.getElementById('mAddr').value = m.address || '';
    
    document.getElementById('formTitle').innerText = "Edit Member Info";
    document.getElementById('saveBtn').innerText = "💾 Update Member";
    document.getElementById('cancelBtn').style.display = "block";
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    memberForm.reset();
    document.getElementById('mId').value = "";
    document.getElementById('formTitle').innerText = "Add New Member";
    document.getElementById('saveBtn').innerText = "💾 Save Member";
    document.getElementById('cancelBtn').style.display = "none";
}

document.getElementById('search').onkeyup = function() {
    let val = this.value.toLowerCase();
    Array.from(memberTable.rows).forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
};
</script>
</body>
</html>