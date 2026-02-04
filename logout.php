<?php
session_start();

/* Destroy all session data */
$_SESSION = [];
session_destroy();

/* Redirect to login */
header("Location: login.php");
exit;
