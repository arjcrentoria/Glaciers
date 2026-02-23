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

$fixed_events = ["SPM", "SS", "AM", "YP", "PM"];

/* ===============================
   FETCH MEMBERS
================================ */
$members = $conn->query("SELECT id, full_name FROM members ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   AJAX FETCH EXISTING DATA
================================ */
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["fetch_date"])) {
    $date = $_GET["fetch_date"];
    
    $stmt = $conn->prepare("
        SELECT a.member_id, e.event_name, a.present 
        FROM attendance a
        JOIN events e ON a.event_id = e.id
        WHERE e.event_date = ?
    ");
    $stmt->execute([$date]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* ===============================
   AJAX SAVE ATTENDANCE
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"])) {

    $event_date = $_POST["attendance_date"];
    $attendance = $_POST["attendance"] ?? [];
    $special_name = trim($_POST["special_event"] ?? "");
    $special_attendance = $_POST["special"] ?? [];

    $conn->beginTransaction();

    try {
        // 1. Process Fixed Events
        foreach ($fixed_events as $event_name) {
            $stmt = $conn->prepare("SELECT id FROM events WHERE event_name = ? AND event_date = ?");
            $stmt->execute([$event_name, $event_date]);
            $event_id = $stmt->fetchColumn();

            if (!$event_id) {
                $stmt = $conn->prepare("INSERT INTO events (event_name, event_date) VALUES (?, ?)");
                $stmt->execute([$event_name, $event_date]);
                $event_id = $conn->lastInsertId();
            }

            // Clear old records for this specific event/day
            $conn->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$event_id]);

            $stmt_ins = $conn->prepare("INSERT INTO attendance (member_id, event_id, present) VALUES (?, ?, ?)");
            foreach ($members as $m) {
                $present = isset($attendance[$event_name][$m["id"]]) ? 1 : 0;
                $stmt_ins->execute([$m["id"], $event_id, $present]);
            }
        }

        // 2. Process Special Event
        // First, check if a special event (not in fixed list) already exists for this date
        $stmt_s = $conn->prepare("SELECT id FROM events WHERE event_date = ? AND event_name NOT IN ('SPM','SS','AM','YP','PM') LIMIT 1");
        $stmt_s->execute([$event_date]);
        $existing_special_id = $stmt_s->fetchColumn();

        if ($special_name !== "") {
            if (!$existing_special_id) {
                $stmt = $conn->prepare("INSERT INTO events (event_name, event_date) VALUES (?, ?)");
                $stmt->execute([$special_name, $event_date]);
                $existing_special_id = $conn->lastInsertId();
            } else {
                $stmt = $conn->prepare("UPDATE events SET event_name = ? WHERE id = ?");
                $stmt->execute([$special_name, $existing_special_id]);
            }

            $conn->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$existing_special_id]);
            $stmt_ins = $conn->prepare("INSERT INTO attendance (member_id, event_id, present) VALUES (?, ?, ?)");
            foreach ($members as $m) {
                $present = isset($special_attendance[$m["id"]]) ? 1 : 0;
                $stmt_ins->execute([$m["id"], $existing_special_id, $present]);
            }
        } elseif ($existing_special_id && $special_name === "") {
            // If the user cleared the text box, delete the special event and its attendance
            $conn->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$existing_special_id]);
            $conn->prepare("DELETE FROM events WHERE id = ?")->execute([$existing_special_id]);
        }

        $conn->commit();
        echo json_encode(["status" => "ok"]);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Youth Attendance</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
/* CSS Reset & Variable Setup */
* { box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
body { margin: 0; background: #eef7ff; color: #333; }

/* Sticky Header */
.header {
    background: linear-gradient(135deg, #4db8ff, #6fd3ff);
    padding: 15px 20px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.container { padding: 15px; max-width: 1000px; margin: auto; }

.card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,.08);
    margin-bottom: 20px;
}

/* Navigation Buttons */
.top-nav { display: flex; gap: 10px; margin-bottom: 15px; }
.btn-secondary {
    flex: 1;
    text-align: center;
    text-decoration: none;
    background: #fff;
    color: #4db8ff;
    border: 2px solid #4db8ff;
    padding: 12px;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.2s;
}
.btn-secondary:active { background: #4db8ff; color: #fff; }

/* Responsive Table Styles */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #eee;
    border-radius: 10px;
    margin: 15px 0;
}

table { width: 100%; border-collapse: collapse; min-width: 500px; }
th, td { padding: 12px 10px; border-bottom: 1px solid #eee; text-align: center; }
th { background: #f0f8ff; font-weight: 600; color: #555; }

/* Sticky Name Column for Mobile */
.sticky-col {
    position: sticky;
    left: 0;
    background: white;
    z-index: 5;
    text-align: left !important;
    border-right: 2px solid #f0f8ff;
    font-weight: bold;
}
th.sticky-col { background: #f0f8ff; z-index: 6; }

/* Touch Optimization */
input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: #4db8ff;
}

.check-cell { cursor: pointer; }
.check-cell:active { background: #f0f8ff; }

/* Form Elements */
input[type="date"], input[type="text"] {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #ddd;
    margin: 10px 0;
    font-size: 16px;
}

button[type="submit"] {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 12px;
    background: #4db8ff;
    color: white;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(77, 184, 255, 0.3);
}

.success-banner {
    background: #e6fffa;
    color: #047857;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
    display: none;
    text-align: center;
    font-weight: bold;
}

@media (max-width: 600px) {
    .container { padding: 10px; }
    h2 { font-size: 1.2rem; }
    th, td { padding: 10px 5px; font-size: 13px; }
}
</style>
</head>

<body>

<div class="header">
    <div style="font-weight:bold; font-size:1.2rem;">❄ Youth Attendance</div>
    <a href="admin.php" style="color:white; text-decoration:none; font-weight:bold;">Admin ⬅</a>
</div>

<div class="container">
    <div class="top-nav">
        <a href="records.php" class="btn-secondary">📊 View History</a>
    </div>

    <div class="card">
        <div class="success-banner" id="successBox">✅ Attendance successfully updated!</div>

        <form id="attendanceForm">
            <input type="hidden" name="ajax" value="1">

            <label><strong>1. Select Attendance Date</strong></label>
            <input type="date" name="attendance_date" id="attendance_date" value="<?= date('Y-m-d') ?>">

            <h3 style="margin-top:20px; color:#4db8ff;">Standard Programs</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="sticky-col">Member Name</th>
                            <?php foreach ($fixed_events as $e): ?>
                                <th><?= $e ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td class="sticky-col"><?= htmlspecialchars($m["full_name"]) ?></td>
                            <?php foreach ($fixed_events as $e): ?>
                                <td class="check-cell">
                                    <input type="checkbox" name="attendance[<?= $e ?>][<?= $m['id'] ?>]">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top:30px; color:#4db8ff;">Special Event (Optional)</h3>
            <input type="text" name="special_event" id="special_event_name" placeholder="Enter Event Name (e.g., Youth Jam, Prayer)">
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="sticky-col">Member Name</th>
                            <th>Present</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td class="sticky-col"><?= htmlspecialchars($m["full_name"]) ?></td>
                            <td class="check-cell">
                                <input type="checkbox" name="special[<?= $m['id'] ?>]">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" id="saveBtn">💾 Save All Attendance</button>
        </form>
    </div>
</div>

<script>
const form = document.getElementById("attendanceForm");
const dateInput = document.getElementById("attendance_date");
const specialInput = document.getElementById("special_event_name");
const successBox = document.getElementById("successBox");

// FUNCTION: Load existing data for the selected date
function loadAttendanceData() {
    const selectedDate = dateInput.value;
    
    fetch(`attendance.php?fetch_date=${selectedDate}`)
    .then(response => response.json())
    .then(data => {
        // Reset all form elements first
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        specialInput.value = "";

        const fixedEvents = ["SPM", "SS", "AM", "YP", "PM"];

        data.forEach(row => {
            let selector = "";
            if (fixedEvents.includes(row.event_name)) {
                selector = `input[name="attendance[${row.event_name}][${row.member_id}]"]`;
            } else {
                // It's a special event
                specialInput.value = row.event_name;
                selector = `input[name="special[${row.member_id}]"]`;
            }

            const checkbox = form.querySelector(selector);
            if (checkbox) checkbox.checked = (parseInt(row.present) === 1);
        });
    })
    .catch(err => console.error("Error loading data:", err));
}

// Event Listeners for Date Change
dateInput.addEventListener("change", loadAttendanceData);
window.addEventListener("DOMContentLoaded", loadAttendanceData);

// Cell-Click Helper: Toggles checkbox when clicking the table cell
document.addEventListener('click', function (e) {
    const cell = e.target.closest('.check-cell');
    if (cell) {
        const cb = cell.querySelector('input[type="checkbox"]');
        // Only toggle if the click wasn't directly on the checkbox (to prevent double-toggle)
        if (e.target !== cb) {
            cb.checked = !cb.checked;
        }
    }
});

// Submit Logic via AJAX
form.addEventListener("submit", function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("saveBtn");
    saveBtn.innerText = "Saving...";
    saveBtn.disabled = true;

    fetch("attendance.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.innerText = "💾 Save All Attendance";
        saveBtn.disabled = false;

        if (data.status === "ok") {
            successBox.style.display = "block";
            window.scrollTo({ top: 0, behavior: "smooth" });
            setTimeout(() => { successBox.style.display = "none"; }, 4000);
        } else {
            alert("Error: " + (data.msg || "Could not save records."));
        }
    })
    .catch(err => {
        saveBtn.innerText = "💾 Save All Attendance";
        saveBtn.disabled = false;
        alert("Network error occurred.");
    });
});
</script>

</body>
</html>