<?php
require "auth.php";
require "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    echo json_encode(["success"=>false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$date = $data["date"] ?? date("Y-m-d");
$attendance = $data["attendance"] ?? [];
$special_name = trim($data["special_event"] ?? "");

$fixed_events = ["SPM","SS","AM","YP","PM"];

/* ===============================
   SAVE FIXED EVENTS
================================ */
foreach ($fixed_events as $event_name) {

    $stmt = $conn->prepare("
        SELECT id FROM events
        WHERE event_name=? AND event_date=?
    ");
    $stmt->execute([$event_name,$date]);
    $event = $stmt->fetch();

    if ($event) {
        $event_id = $event["id"];
        $conn->prepare("DELETE FROM attendance WHERE event_id=?")
              ->execute([$event_id]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO events (event_name,event_date)
            VALUES (?,?)
        ");
        $stmt->execute([$event_name,$date]);
        $event_id = $conn->lastInsertId();
    }

    $present_ids = $attendance[$event_name] ?? [];

    $members = $conn->query("SELECT id FROM members")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($members as $mid) {
        $present = in_array($mid,$present_ids) ? 1 : 0;
        $conn->prepare("
            INSERT INTO attendance (member_id,event_id,present)
            VALUES (?,?,?)
        ")->execute([$mid,$event_id,$present]);
    }
}

/* ===============================
   SPECIAL EVENT
================================ */
if ($special_name !== "") {

    $stmt = $conn->prepare("
        INSERT INTO events (event_name,event_date)
        VALUES (?,?)
    ");
    $stmt->execute([$special_name,$date]);
    $event_id = $conn->lastInsertId();

    $present_ids = $attendance["SPECIAL"] ?? [];
    $members = $conn->query("SELECT id FROM members")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($members as $mid) {
        $present = in_array($mid,$present_ids) ? 1 : 0;
        $conn->prepare("
            INSERT INTO attendance (member_id,event_id,present)
            VALUES (?,?,?)
        ")->execute([$mid,$event_id,$present]);
    }
}

echo json_encode(["success"=>true]);