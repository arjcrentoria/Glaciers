<?php
session_start();
require "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user["password"]) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["role"] = $user["role"];
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login</title>

<style>
body {
    margin:0;
    font-family:"Segoe UI", Arial;
    background:#eef7ff;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.card {
    background:white;
    padding:30px;
    width:350px;
    border-radius:16px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);
}

h2 {
    text-align:center;
    color:#4db8ff;
    margin-top:0;
}

input[type="text"],
input[type="password"] {
    width:100%;
    padding:10px;
    margin-top:10px;
    border-radius:8px;
    border:1px solid #ccc;
}

button {
    margin-top:20px;
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#4db8ff;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

.error {
    color:red;
    margin-bottom:10px;
    text-align:center;
}

/* ✅ PERFECT CHECKBOX ALIGNMENT */
.show {
    display: flex;
    align-items: center;
    margin-top: 8px;
    font-size: 13px;
}

.show input {
    width: 14px;
    height: 14px;
    margin: 0 6px 0 0;
}
</style>

<script>
function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}
</script>
</head>

<body>

<div class="card">
<h2>Admin Login</h2>

<?php if ($error): ?>
<div class="error"><?= $error ?></div>
<?php endif; ?>

<form method="post">
    <input type="text" name="username" placeholder="Username" required>

    <input type="password" id="password" name="password" placeholder="Password" required>

    <label class="show">
        <input type="checkbox" onclick="togglePassword()">
        Show Password
    </label>

    <button type="submit">Login</button>
</form>
</div>

</body>
</html>
