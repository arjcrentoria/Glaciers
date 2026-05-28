<?php
require "auth.php";
require "db.php";

/* PROTECT ADMIN */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    exit("Unauthorized");
}

/* FETCH DATA */
$data = $conn->query("
    SELECT
        m.full_name,

        IFNULL(att.spm,0) AS spm,
        IFNULL(att.ss,0) AS ss,
        IFNULL(att.am,0) AS am,
        IFNULL(att.yp,0) AS yp,
        IFNULL(att.pm,0) AS pm,
        IFNULL(att.total_present,0) AS total_present,
        IFNULL(att.total_present,0) * 10 AS points,

        IFNULL(off.cash,0) AS cash_offering,
        IFNULL(off.gcash,0) AS gcash_offering,
        IFNULL(off.total,0) AS total_offering

    FROM members m
    LEFT JOIN (
        SELECT
            a.member_id,
            SUM(CASE WHEN e.event_name='SPM' AND a.present=1 THEN 1 ELSE 0 END) AS spm,
            SUM(CASE WHEN e.event_name='SS'  AND a.present=1 THEN 1 ELSE 0 END) AS ss,
            SUM(CASE WHEN e.event_name='AM'  AND a.present=1 THEN 1 ELSE 0 END) AS am,
            SUM(CASE WHEN e.event_name='YP'  AND a.present=1 THEN 1 ELSE 0 END) AS yp,
            SUM(CASE WHEN e.event_name='PM'  AND a.present=1 THEN 1 ELSE 0 END) AS pm,
            COUNT(CASE WHEN a.present=1 THEN 1 END) AS total_present
        FROM attendance a
        JOIN events e ON a.event_id = e.id
        GROUP BY a.member_id
    ) att ON att.member_id = m.id
    LEFT JOIN (
        SELECT
            member_id,
            SUM(CASE WHEN payment_method = 'GCash' THEN amount ELSE 0 END) AS gcash,
            SUM(CASE WHEN payment_method != 'GCash' THEN amount ELSE 0 END) AS cash,
            SUM(amount) AS total
        FROM offerings
        GROUP BY member_id
    ) off ON off.member_id = m.id
    ORDER BY m.full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* CSV HEADERS */
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=YP_Members_Report.csv");

$output = fopen("php://output", "w");

/* COLUMN HEADERS */
fputcsv($output, [
    "Name",
    "SPM",
    "SS",
    "AM",
    "YP",
    "PM",
    "Total Present",
    "Points",
    "Cash Offering",
    "GCash Offering",
    "Total Offering"
]);

/* DATA */
foreach ($data as $row) {
    fputcsv($output, [
        $row["full_name"],
        $row["spm"],
        $row["ss"],
        $row["am"],
        $row["yp"],
        $row["pm"],
        $row["total_present"],
        $row["points"],
        $row["cash_offering"],
        $row["gcash_offering"],
        $row["total_offering"]
    ]);
}

fclose($output);
exit;
