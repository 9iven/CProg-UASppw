<?php
session_start();
require 'config/db.php';
require_once 'includes/sync_logic.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all handles for the user
$query = "SELECT platform_id, username FROM user_handles WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);

$success_count = 0;
$fail_count = 0;

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $platform_id = $row['platform_id'];
        $username = $row['username'];
        
        // Only attempt to sync platforms that support auto-sync (Codeforces=1, LeetCode=2)
        if ($platform_id == 1 || $platform_id == 2) {
            $sync_res = sync_platform($user_id, $platform_id, $username, $conn);
            if ($sync_res['success']) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
    }
    
    if ($success_count > 0 && $fail_count == 0) {
        $_SESSION['success_msg'] = "Successfully synchronized all connected platforms.";
    } elseif ($success_count > 0 && $fail_count > 0) {
        $_SESSION['success_msg'] = "Synchronized $success_count platform(s), but $fail_count failed.";
    } elseif ($fail_count > 0) {
        $_SESSION['error_msg'] = "Failed to synchronize connected platforms. Please try again later.";
    } else {
        $_SESSION['success_msg'] = "No API-supported platforms to synchronize.";
    }
} else {
    $_SESSION['error_msg'] = "No platforms connected yet. Go to Settings to link your accounts.";
}

header("Location: dashboard.php");
exit;
?>
