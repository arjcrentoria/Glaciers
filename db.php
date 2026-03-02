<?php
try {
    $dbPath = __DIR__ . "/database/glaciers.db";

    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ VERY IMPORTANT FOR SQLITE
    $conn->exec("PRAGMA foreign_keys = ON");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
