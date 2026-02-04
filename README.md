Glaciers – Project Setup & Run Guide
This project is a PHP + SQLite web application designed to run on a local server (XAMPP) or a Linux web server.

Requirements
Before running the project, make sure you have:

PHP 8.0+

SQLite enabled in PHP

A web server (Apache / Nginx)

Git (optional, for cloning)

Project Structure (Important Files)
Glaciers/
├── admin.php
├── login.php
├── logout.php
├── db.php
├── database/
│   └── glaciers.db
├── README.md


How to Run (Local – XAMPP)
1.)Install XAMPP
Download and install XAMPP from:
https://www.apachefriends.org/
Enable:
Apache
PHP
2️.)Move the Project
Copy the Glaciers folder into:
XAMPP/htdocs/
Example:
D:/Xampp/htdocs/Glaciers
3️.)Configure db.php
Open db.php and make sure the path matches your system.
Windows (XAMPP):
<?php
$conn = new PDO(
    "sqlite:" . __DIR__ . "/database/glaciers.db"
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Using __DIR__ works on both Windows and Linux
4️.)Start the Server
Open XAMPP Control Panel and start:
Apache
5️.)Open the Project in Browser
Go to:
http://localhost/Glaciers/login.php
6️.)Login
Use the credentials stored in the database.
After login:
You cannot access admin pages directly
Pages are protected using PHP sessions
Security Notes
Pages are protected using:
session_start();
if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}
Direct access via .php URL without login is blocked
Session logout only affects the current browser/device


How to Run on a Linux Server
1️.)Upload Files
Upload the entire Glaciers folder to:
/var/www/html/
or your hosting’s public directory.

2️.)Set Permissions
Make sure SQLite file is writable:

chmod 664 database/glaciers.db
chmod 775 database
3️.)Update db.php
No change needed if you used __DIR__.

4️.)Open in Browser
https://yourdomain.com/Glaciers/login.php
