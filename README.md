# ❄️ Glaciers – PHP + SQLite Web Application (Nginx / Linux Setup)

This project is a **PHP + SQLite** web application designed to run on a **Linux server using Nginx**.

---

## ✅ Requirements

Before running the project, make sure you have:

- **Linux (Ubuntu recommended)**
- **PHP 8.0+**
- **PHP SQLite extension enabled**
- **Nginx**
- **Git** (optional, for cloning)

---

## 📁 Project Structure

Glaciers/
├── admin.php
├── login.php
├── logout.php
├── db.php
├── database/
│ └── glaciers.db
├── README.md


---

## 🚀 How to Run on Linux (Nginx)

### 1️⃣ Install Required Packages

```bash
sudo apt update
sudo apt install -y nginx php php-fpm php-sqlite3 git

Start services:

sudo systemctl start nginx
sudo systemctl start php8.3-fpm
2️⃣ Upload / Clone Project Files
Move or clone the project into the web directory:

sudo mv Glaciers /var/www/Glaciers
Or clone directly:

cd /var/www
sudo git clone <YOUR_REPOSITORY_URL> Glaciers
3️⃣ Set File Permissions
Make sure Nginx can access the files and SQLite database:

sudo chown -R www-data:www-data /var/www/Glaciers
sudo chmod -R 755 /var/www/Glaciers
sudo chmod 775 /var/www/Glaciers/database
sudo chmod 664 /var/www/Glaciers/database/glaciers.db
4️⃣ Configure db.php
Open db.php and ensure SQLite uses an absolute path:

<?php
$conn = new PDO(
    "sqlite:" . __DIR__ . "/database/glaciers.db"
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
5️⃣ Configure Nginx
Edit the default Nginx site:

sudo nano /etc/nginx/sites-available/default
Replace the server block with:

server {
    listen 80;
    server_name _;

    root /var/www/Glaciers;
    index index.php login.php index.html;

    location / {
        try_files $uri $uri/ /login.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
Test and reload Nginx:

sudo nginx -t
sudo systemctl reload nginx
6️⃣ Open in Browser
On the server:

http://localhost
Or using server IP:

http://SERVER_IP/login.php
