<?php
try {
    $conn = new PDO(
        "sqlite:D:/Xampp/htdocs/Glaciers/database/glaciers.db"
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
