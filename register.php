<?php
require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username  = trim($_POST["username"]);
    $password  = $_POST["password"];
    $full_name = trim($_POST["full_name"]);

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {

        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $error = "Username already exists";
        } else {

            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, role)
                VALUES (:u, :p, 'member')
            ");
            $stmt->execute([
                ':u' => $username,
                ':p' => $hashed
            ]);

            $user_id = $conn->lastInsertId();

            // Insert into members
            $stmt = $conn->prepare("
                INSERT INTO members (user_id, full_name)
                VALUES (:id, :name)
            ");
            $stmt->execute([
                ':id' => $user_id,
                ':name' => $full_name
            ]);

            $success = "Account created successfully! You can now log in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Account</title>

<style>
* {
  box-sizing: border-box;
  font-family: "Segoe UI", Arial, sans-serif;
}

body {
  margin: 0;
  height: 100vh;
  background: linear-gradient(135deg, #cfefff, #eaf8ff);
  display: flex;
  justify-content: center;
  align-items: center;
}

.container {
  width: 380px;
  padding: 35px;
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(10px);
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(0,150,255,0.2);
}

h1 {
  text-align: center;
  color: #4db8ff;
}

.input-group {
  margin-bottom: 15px;
}

label {
  font-size: 13px;
  color: #3a7ca5;
}

input[type="text"],
input[type="password"] {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #b9e4ff;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #3a7ca5;
  margin-bottom: 15px;
}

.checkbox-group input {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

button {
  width: 100%;
  padding: 13px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #6fd3ff, #4db8ff);
  color: white;
  font-weight: bold;
  cursor: pointer;
}

.msg {
  font-size: 13px;
  margin-bottom: 10px;
}

.error { color: red; }
.success { color: green; }

.link {
  text-align: center;
  margin-top: 15px;
  font-size: 13px;
}
</style>

<script>
function togglePassword() {
  const p = document.getElementById("password");
  p.type = p.type === "password" ? "text" : "password";
}
</script>
</head>

<body>

<div class="container">
  <h1>Create Account</h1>

  <?php if (isset($error))   echo "<div class='msg error'>$error</div>"; ?>
  <?php if (isset($success)) echo "<div class='msg success'>$success</div>"; ?>

  <form method="post">
    <div class="input-group">
      <label>Full Name</label>
      <input type="text" name="full_name" required>
    </div>

    <div class="input-group">
      <label>Username</label>
      <input type="text" name="username" required>
    </div>

    <div class="input-group">
      <label>Password</label>
      <input type="password" name="password" id="password" required>
    </div>

    <div class="checkbox-group">
      <input type="checkbox" id="showPass" onclick="togglePassword()">
      <label for="showPass">Show Password</label>
    </div>

    <button type="submit">Create Account</button>
  </form>

  <div class="link">
    Already have an account? <a href="login.php">Login</a>
  </div>
</div>

</body>
</html>
