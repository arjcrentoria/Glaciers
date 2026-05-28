<?php
try {
    $dbPath = __DIR__ . "/database/glaciers.db";

    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->exec("PRAGMA foreign_keys = ON");

    initializeDatabase($conn);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function initializeDatabase(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('admin','member')),
            first_login INTEGER DEFAULT 1,
            full_name TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            full_name TEXT NOT NULL,
            age INTEGER,
            birthday TEXT,
            address TEXT,
            contact TEXT,
            facebook TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_name TEXT NOT NULL,
            event_date TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(event_name, event_date)
        );

        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id INTEGER NOT NULL,
            event_id INTEGER NOT NULL,
            present INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            UNIQUE(member_id, event_id)
        );

        CREATE TABLE IF NOT EXISTS offerings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id INTEGER NOT NULL,
            offering_date TEXT NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            payment_method TEXT NOT NULL DEFAULT 'Cash',
            event_name TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            UNIQUE(member_id, offering_date)
        );

        CREATE TABLE IF NOT EXISTS points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id INTEGER NOT NULL,
            points INTEGER NOT NULL DEFAULT 0,
            reason TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            audit_date TEXT NOT NULL,
            type TEXT NOT NULL CHECK (type IN ('IN','OUT')),
            amount REAL NOT NULL DEFAULT 0,
            description TEXT NOT NULL,
            payment_method TEXT NOT NULL DEFAULT 'Cash',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    migrateDatabase($conn);
    seedDatabase($conn);
}

function migrateDatabase(PDO $conn): void
{
    addColumnIfMissing($conn, "users", "first_login", "INTEGER DEFAULT 1");
    addColumnIfMissing($conn, "users", "full_name", "TEXT");
    addColumnIfMissing($conn, "users", "created_at", "TEXT");

    addColumnIfMissing($conn, "members", "user_id", "INTEGER");
    addColumnIfMissing($conn, "members", "created_at", "TEXT");

    addColumnIfMissing($conn, "events", "created_at", "TEXT");
    addColumnIfMissing($conn, "attendance", "created_at", "TEXT");
    addColumnIfMissing($conn, "offerings", "payment_method", "TEXT NOT NULL DEFAULT 'Cash'");
    addColumnIfMissing($conn, "offerings", "created_at", "TEXT");
    addColumnIfMissing($conn, "audit_logs", "created_at", "TEXT");

    createUniqueIndexIfMissing($conn, "idx_events_name_date", "events", "event_name, event_date");
    createUniqueIndexIfMissing($conn, "idx_attendance_member_event", "attendance", "member_id, event_id");
    createUniqueIndexIfMissing($conn, "idx_offerings_member_date", "offerings", "member_id, offering_date");
}

function addColumnIfMissing(PDO $conn, string $table, string $column, string $definition): void
{
    $stmt = $conn->query("PRAGMA table_info($table)");
    foreach ($stmt->fetchAll() as $row) {
        if (strcasecmp($row["name"], $column) === 0) {
            return;
        }
    }

    $conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}

function createUniqueIndexIfMissing(PDO $conn, string $index, string $table, string $columns): void
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM sqlite_master
        WHERE type = 'index' AND name = ?
    ");
    $stmt->execute([$index]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $conn->exec("CREATE UNIQUE INDEX $index ON $table ($columns)");
}

function seedDatabase(PDO $conn): void
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(["Arj"]);
    if ((int) $stmt->fetchColumn() === 0) {
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, role, first_login, full_name, created_at)
            VALUES (?, ?, 'admin', 1, ?, ?)
        ");
        $stmt->execute([
            "Arj",
            '$2y$10$Nmd39vZr.3y6dYZFmfy5XuoTUvwPvxKtB/AZaSbOQmLO4qDKghbjK',
            "Ar-J C. Rentoria",
            "2026-02-03 00:40:26"
        ]);
    }

    $adminCount = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount === 0) {
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, role, first_login, full_name, created_at)
            VALUES (?, ?, 'admin', 1, ?, ?)
        ");
        $stmt->execute([
            "Arj",
            '$2y$10$Nmd39vZr.3y6dYZFmfy5XuoTUvwPvxKtB/AZaSbOQmLO4qDKghbjK',
            "Ar-J C. Rentoria",
            "2026-02-03 00:40:26"
        ]);
    }

    $memberCount = (int) $conn->query("SELECT COUNT(*) FROM members")->fetchColumn();
    if ($memberCount > 0) {
        return;
    }

    $members = [
        "Sean Eliab Acuesta",
        "Angela Blanker",
        "Janevielle Kerine Boitmann",
        "Jashleen Kate Boitmann",
        "Jennica Kyle Boitmann",
        "Arnaldo Bustamante",
        "Keziah Comandante",
        "Kurt Russel de Asis",
        "Leona de Guzman",
        "Ma. Juliet de Jesus",
        "Rhean Jade de Jesus",
        "Sophia Ann de Vera",
        "David Clyde Dizon",
        "Hannah Louise Dizon",
        "Charles Gludo",
        "Princess Jacee Juaiting",
        "Brandon Lim-it",
        "Byron Lim-it",
        "Rosa Jane Maullion",
        "Lian Pegoria",
        "Michael Deiyrick Plaus",
        "Ar-j Rentoria",
        "Aille Santelices",
        "Airaine Santelices",
        "Aldrin Santelices",
        "Christian Santelices",
        "Akiro Jetro Taal",
        "Hazel Tabagan",
        "Vince Jhared Tabagan",
        "Hannah Daniella Tamayo",
        "Hannah Eunice Tamayo",
        "Blessie Grace Tuazon",
        "Daisy Gail Ventura",
        "Sophia Villacrusis"
    ];

    $stmt = $conn->prepare("INSERT INTO members (full_name, created_at) VALUES (?, ?)");
    foreach ($members as $name) {
        $stmt->execute([$name, "2026-02-03 00:57:23"]);
    }
}
