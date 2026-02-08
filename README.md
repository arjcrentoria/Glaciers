# Glaciers – PHP + SQLite Web Application (Linux Setup)

This project is a **PHP + SQLite** web application designed to run on a Linux web server.

---

## Requirements

Before running the project, make sure you have:

- **PHP 8.0+**
- **SQLite enabled** in PHP
- A web server (**Apache** / **Nginx**)
- **Git** (optional, for cloning)

---

## Project Structure

Important files:

Glaciers/
├── admin.php
├── login.php
├── logout.php
├── db.php
├── database/
│ └── glaciers.db
├── README.md

---

## How to Run on a Linux Server

1. **Upload Files**  
   Upload the entire `Glaciers` folder to your web server’s public directory, for example:
/var/www/html/

2. **Set Permissions**  
Make sure the SQLite file and its folder are writable:

```bash
chmod 664 database/glaciers.db
chmod 775 database
chmod 775 database

3.Configure db.php
Open db.php and ensure the SQLite path uses __DIR__:
<?php
$conn = new PDO(
    "sqlite:" . __DIR__ . "/database/glaciers.db"
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

4.Open in Browser
Go to:

https://yourdomain.com/Glaciers/login.php


5.Login
Use the credentials stored in the database.

Admin pages are protected with PHP sessions.

Direct access without login is blocked.
Security Notes

Pages are protected using:

session_start();
if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}


Direct .php access without login is blocked.

Logging out only affects the current browser/device.
