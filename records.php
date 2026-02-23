<?php
require "auth.php";
require "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$fixed_events = ["SPM", "SS", "AM", "YP", "PM"];
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   AJAX: DELETE DATE RECORD
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_date'])) {
    $date_to_delete = $_POST['delete_date'];
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("DELETE FROM attendance WHERE event_id IN (SELECT id FROM events WHERE event_date = ?)");
        $stmt->execute([$date_to_delete]);
        $stmt = $conn->prepare("DELETE FROM events WHERE event_date = ?");
        $stmt->execute([$date_to_delete]);
        $conn->commit();
        echo json_encode(["status" => "ok"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error"]);
    }
    exit;
}

/* ===============================
   AJAX: LOAD RECORDS
================================ */
if (isset($_GET['fetch_date_records'])) {
    $date = $_GET['fetch_date_records'];
    $stmt = $conn->prepare("
        SELECT a.member_id, e.event_name, a.present 
        FROM attendance a
        JOIN events e ON e.id = a.event_id
        WHERE e.event_date = ?
    ");
    $stmt->execute([$date]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* ===============================
   AJAX: SAVE EDITED RECORDS
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_save'])) {
    $selected_date = $_POST["date"];
    $attendance = $_POST["attendance"] ?? [];
    $special_name = trim($_POST["special_event"] ?? "");
    $special_attendance = $_POST["special"] ?? [];

    $conn->beginTransaction();
    try {
        // Update Fixed Events
        foreach ($fixed_events as $event_name) {
            $stmt = $conn->prepare("SELECT id FROM events WHERE event_name = ? AND event_date = ?");
            $stmt->execute([$event_name, $selected_date]);
            $event_id = $stmt->fetchColumn();

            if ($event_id) {
                $conn->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$event_id]);
                $stmt_ins = $conn->prepare("INSERT INTO attendance (member_id, event_id, present) VALUES (?, ?, ?)");
                foreach ($members as $m) {
                    $present = isset($attendance[$event_name][$m["id"]]) ? 1 : 0;
                    $stmt_ins->execute([$m["id"], $event_id, $present]);
                }
            }
        }

        // Update Special Event
        if ($special_name !== "") {
            $stmt = $conn->prepare("SELECT id FROM events WHERE event_date = ? AND event_name NOT IN ('SPM','SS','AM','YP','PM') LIMIT 1");
            $stmt->execute([$selected_date]);
            $special_event_id = $stmt->fetchColumn();

            if (!$special_event_id) {
                $ins = $conn->prepare("INSERT INTO events (event_name, event_date) VALUES (?, ?)");
                $ins->execute([$special_name, $selected_date]);
                $special_event_id = $conn->lastInsertId();
            } else {
                $conn->prepare("UPDATE events SET event_name = ? WHERE id = ?")->execute([$special_name, $special_event_id]);
                $conn->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$special_event_id]);
            }

            $stmt_ins = $conn->prepare("INSERT INTO attendance (member_id, event_id, present) VALUES (?, ?, ?)");
            foreach ($members as $m) {
                $present = isset($special_attendance[$m["id"]]) ? 1 : 0;
                $stmt_ins->execute([$m["id"], $special_event_id, $present]);
            }
        }
        $conn->commit();
        echo json_encode(["status" => "ok"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error"]);
    }
    exit;
}

$date_history = $conn->query("SELECT DISTINCT event_date FROM events ORDER BY event_date DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Records</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial; }
        body { margin: 0; background: #eef7ff; display: flex; flex-direction: column; min-height: 100vh; }
        .header { background: linear-gradient(135deg, #4db8ff, #6fd3ff); padding: 15px 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .main-layout { display: flex; flex: 1; padding: 15px; gap: 15px; }
        .history-panel { width: 280px; background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); max-height: 85vh; overflow-y: auto; }
        .history-item-container { display: flex; align-items: center; gap: 5px; margin-bottom: 8px; }
        .history-item { flex: 1; padding: 12px; background: #f8fbff; border: 1px solid #e0efff; border-radius: 8px; color: #333; font-weight: 500; cursor: pointer; }
        .history-item.active { background: #4db8ff; color: white; }
        .btn-delete-small { background: #ffeded; color: #ff4d4d; border: 1px solid #ffcccc; border-radius: 8px; padding: 10px; cursor: pointer; }
        .edit-panel { flex: 1; background: white; border-radius: 16px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .table-wrapper { overflow-x: auto; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #f0f8ff; }
        .btn-save { background: #4db8ff; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 20px; }
        .status-badge { display: none; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 10px; }
        .section-title { background: #f0f8ff; padding: 10px; border-radius: 8px; margin-top: 20px; }
        @media (max-width: 800px) { .main-layout { flex-direction: column; } .history-panel { width: 100%; max-height: 200px; } }
    </style>
</head>
<body>

<div class="header">
    <div>📊 Manage Records</div>
    <a href="attendance.php" style="color:white;text-decoration:none;">⬅ Back</a>
</div>

<div class="main-layout">
    <div class="history-panel">
        <h4 style="margin-top:0;">Available Dates</h4>
        <?php foreach ($date_history as $date): ?>
            <div class="history-item-container" id="row-<?= $date ?>">
                <div class="history-item" onclick="loadDateData('<?= $date ?>', this)">📅 <?= date("M d, Y", strtotime($date)) ?></div>
                <button class="btn-delete-small" onclick="deleteDate('<?= $date ?>')">🗑</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="edit-panel" id="editPanel" style="display:none;">
        <h3 id="viewingDateTitle">Edit Record</h3>
        <div id="statusMsg" class="status-badge"></div>

        <form id="editForm">
            <input type="hidden" name="ajax_save" value="1">
            <input type="hidden" name="date" id="targetDate">

            <div id="fixedEventsSection">
                <h4 class="section-title">Standard Programs</h4>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align:left">Name</th>
                                <?php foreach ($fixed_events as $e): ?><th><?= $e ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                            <tr>
                                <td style="text-align:left"><?= htmlspecialchars($m["full_name"]) ?></td>
                                <?php foreach ($fixed_events as $e): ?>
                                    <td><input type="checkbox" name="attendance[<?= $e ?>][<?= $m['id'] ?>]"></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="specialEventSection">
                <h4 class="section-title">Special Event</h4>
                <input type="text" name="special_event" id="special_name" placeholder="Event name (e.g. Prayer Meeting)">
                <div class="table-wrapper">
                    <table>
                        <tr><th style="text-align:left">Name</th><th>Present</th></tr>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td style="text-align:left"><?= htmlspecialchars($m["full_name"]) ?></td>
                            <td><input type="checkbox" name="special[<?= $m['id'] ?>]"></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn-save">💾 Update Records</button>
        </form>
    </div>

    <div id="placeholderText" style="flex:1; display:flex; align-items:center; justify-content:center; color:#999; text-align:center;">
        <h3>Select a date from the left history list</h3>
    </div>
</div>

<script>
const editForm = document.getElementById('editForm');
const targetDateInput = document.getElementById('targetDate');
const specialNameInput = document.getElementById('special_name');
const fixedSection = document.getElementById('fixedEventsSection');

function loadDateData(date, element) {
    document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    document.getElementById('placeholderText').style.display = 'none';
    document.getElementById('editPanel').style.display = 'block';
    targetDateInput.value = date;
    document.getElementById('viewingDateTitle').innerText = "Editing: " + date;

    fetch(`records.php?fetch_date_records=${date}`)
    .then(res => res.json())
    .then(data => {
        // Reset all checks
        editForm.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        specialNameInput.value = "";
        
        let hasFixedData = false;
        const fixedEvents = ["SPM", "SS", "AM", "YP", "PM"];

        data.forEach(row => {
            if (fixedEvents.includes(row.event_name)) {
                const cb = editForm.querySelector(`input[name="attendance[${row.event_name}][${row.member_id}]"]`);
                if(cb) {
                    cb.checked = (parseInt(row.present) === 1);
                    if(cb.checked) hasFixedData = true; // Mark that standard data exists
                }
            } else {
                specialNameInput.value = row.event_name;
                const cb = editForm.querySelector(`input[name="special[${row.member_id}]"]`);
                if(cb) cb.checked = (parseInt(row.present) === 1);
            }
        });

        // HIDE fixed section if no checkboxes were checked for fixed events
        fixedSection.style.display = hasFixedData ? 'block' : 'none';
    });
}

function deleteDate(date) {
    if(!confirm(`Permanently delete all records for ${date}?`)) return;
    const fd = new FormData();
    fd.append('delete_date', date);
    fetch('records.php', { method: 'POST', body: fd }).then(() => location.reload());
}

editForm.addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('records.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json()).then(data => {
        const msg = document.getElementById('statusMsg');
        msg.style.display = 'block';
        msg.style.background = data.status === 'ok' ? '#e6fffa' : '#fff5f5';
        msg.style.color = data.status === 'ok' ? '#047857' : '#c53030';
        msg.innerText = data.status === 'ok' ? "✅ Update Successful" : "❌ Error saving";
        setTimeout(() => msg.style.display = 'none', 3000);
    });
});
</script>
</body>
</html>