<?php
session_start();
require '../config/db.php';
require_once __DIR__ . '/sync_logic.php';

// Ensure the user is logged in and accessing this page only via POST form
if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../settings.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$platform_id = (int)$_POST['platform_id'];
$handle_username = trim($_POST['cf_username']);

$result = sync_platform($user_id, $platform_id, $handle_username, $conn);

if ($result['success']) {
    if ($platform_id == 1) {
        $_SESSION['success_msg'] = "Codeforces handle and solved history successfully synchronized.";
    } elseif ($platform_id == 2) {
        $_SESSION['success_msg'] = "LeetCode handle and solved history successfully synchronized.";
    } else {
        $_SESSION['success_msg'] = "Platform handle successfully registered manually.";
    }
} else {
    $_SESSION['error_msg'] = $result['message'];
}

header("Location: ../settings.php");
exit;
?>