<?php
session_start();

// Clear all active session data
session_unset();
session_destroy();

// Redirect user back to the login page
header("Location: login.php");
exit;
?>