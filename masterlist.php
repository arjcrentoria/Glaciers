<?php
require "auth.php";
require "db.php";

/* ===============================
   PROTECT ADMIN PAGE
================================ */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$success = "";

/* ===============================
   ADD NEW MEMBER
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add"])) {

    $stmt = $conn->prepare("
        INSERT INTO members
        (full_name, age, birthday, address, contact, facebook)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST["full_name"],
        $_POST["age"],
        $_POST["birthday"],
        $_POST["address"],
        $_POST["contact"],
        $_POST["facebook"]
    ]);

    $success = "New member added successfully!";
}

/* ===============================
   UPDATE MEMBER
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {

    $stmt = $conn->prepare("
        UPDATE members SET
            full_name = ?,
            age = ?,
            birthday = ?,
            address = ?,
            contact = ?,
            facebook = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST["full_name"],
        $_POST["age"],
        $_POST["birthday"],
        $_POST["address"],
        $_POST["contact"],
        $_POST["facebook"],
        $_POST["id"]
    ]);

    $success = "Member updated successfully!";
}

/* ===============================
   FETCH MEMBER TO EDIT
================================ */
$edit = null;
if (isset($_GET["edit"])) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$_GET["edit"]]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ===============================
   FETCH MEMBERS
================================ */
$members = $conn->query("
    SELECT * FROM members
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Masterlist</title>

<style>
* { box-sizing:border-box; font-family:"Segoe UI",Arial }
body { margin:0; background:#eef7ff }

.header {
    background:linear-gradient(135deg,#4db8ff,#6fd3ff);
    padding:18px 25px;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:22px;
}

.header a {
    color:white;
    text-decoration:none;
    font-weight:bold;
    font-size:14px;
}

.container { padding:25px }

.card {
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    margin-bottom:25px;
}

h2 { color:#4db8ff; margin-top:0 }

input {
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    margin-bottom:12px;
}

button {
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:#4db8ff;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

.success {
    color:green;
    margin-bottom:10px;
}

table {
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}
th,td {
    padding:10px;
    border-bottom:1px solid #eee;
    text-align:center;
}
th { background:#f0f8ff }

.editbtn {
    background:#16a34a;
    color:white;
    padding:6px 10px;
    border-radius:8px;
    text-decoration:none;
    font-size:12px;
}
</style>
</head>

<body>

<div class="header">
    <div>❄ Masterlist</div>
    <a href="admin.php">⬅ Back to Admin</a>
</div>

<div class="container">

<!-- ================= ADD / EDIT MEMBER ================= -->
<div class="card">
<h2><?= $edit ? "Edit Member" : "Add New Member" ?></h2>

<?php if ($success): ?>
<div class="success"><?= $success ?></div>
<?php endif; ?>

<form method="post">
    <?php if ($edit): ?>
        <input type="hidden" name="id" value="<?= $edit["id"] ?>">
    <?php endif; ?>

    <input type="text" name="full_name" placeholder="Full Name" required
        value="<?= $edit["full_name"] ?? "" ?>">

    <input type="number" name="age" placeholder="Age" required
        value="<?= $edit["age"] ?? "" ?>">

    <input type="date" name="birthday" required
        value="<?= $edit["birthday"] ?? "" ?>">

    <input type="text" name="address" placeholder="Address" required
        value="<?= $edit["address"] ?? "" ?>">

    <input type="text" name="contact" placeholder="Contact Number" required
        value="<?= $edit["contact"] ?? "" ?>">

    <input type="text" name="facebook" placeholder="Facebook Name" required
        value="<?= $edit["facebook"] ?? "" ?>">

    <button type="submit" name="<?= $edit ? "update" : "add" ?>">
        <?= $edit ? "Update Member" : "Add Member" ?>
    </button>
</form>
</div>

<!-- ================= MEMBER LIST ================= -->
<div class="card">
<h2>Members List</h2>

<table>
<tr>
    <th>Name</th>
    <th>Age</th>
    <th>Birthday</th>
    <th>Address</th>
    <th>Contact</th>
    <th>Facebook</th>
    <th>Action</th>
</tr>

<?php foreach ($members as $m): ?>
<tr>
    <td><?= htmlspecialchars($m["full_name"]) ?></td>
    <td><?= $m["age"] ?></td>
    <td><?= $m["birthday"] ?></td>
    <td><?= htmlspecialchars($m["address"]) ?></td>
    <td><?= htmlspecialchars($m["contact"]) ?></td>
    <td><?= htmlspecialchars($m["facebook"]) ?></td>
    <td>
        <a class="editbtn" href="masterlist.php?edit=<?= $m["id"] ?>">Edit</a>
    </td>
</tr>
<?php endforeach; ?>

<?php if (empty($members)): ?>
<tr><td colspan="7">No members yet</td></tr>
<?php endif; ?>
</table>
</div>

</div>
</body>
</html>
