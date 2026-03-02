<?php
try {
   $dbPath = "/home/arj14/glaciers.db";
    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("PRAGMA foreign_keys = ON");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}