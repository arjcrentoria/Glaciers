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

        SUM(CASE WHEN e.event_name='SPM' AND a.present=1 THEN 1 ELSE 0 END) AS spm,
        SUM(CASE WHEN e.event_name='SS'  AND a.present=1 THEN 1 ELSE 0 END) AS ss,
        SUM(CASE WHEN e.event_name='AM'  AND a.present=1 THEN 1 ELSE 0 END) AS am,
        SUM(CASE WHEN e.event_name='YP'  AND a.present=1 THEN 1 ELSE 0 END) AS yp,
        SUM(CASE WHEN e.event_name='PM'  AND a.present=1 THEN 1 ELSE 0 END) AS pm,

        COUNT(CASE WHEN a.present=1 THEN 1 END) AS total_present,
        COUNT(CASE WHEN a.present=1 THEN 1 END) * 10 AS points,

        IFNULL(SUM(o.amount),0) AS total_offering

    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id
    LEFT JOIN events e ON a.event_id = e.id
    LEFT JOIN offerings o ON m.id = o.member_id

    GROUP BY m.id
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
        $row["total_offering"]
    ]);
}

fclose($output);
exit;
