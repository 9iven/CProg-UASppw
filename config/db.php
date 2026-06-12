<?php
// --- DATABASE CONNECTION CONFIGURATION ---
// This file is used to connect the PHP application with the MySQL database.

$host     = "localhost";  // Database server address (localhost for local development)
$user     = "root";       // Default XAMPP database username
$password = "";           // Default XAMPP database password (empty by default)
$db_name  = "cp_viewer";  // Database name used for this project

// Establish connection to database using mysqli_connect
/** @var mysqli $conn */
$conn = mysqli_connect($host, $user, $password, $db_name);

// Check if connection is successful. If failed, terminate execution and print error message.
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- CENTRALIZED PLATFORM SEEDING ---
$seeding_platforms = [
    1 => ['Codeforces', 1],
    2 => ['LeetCode', 1],
    3 => ['Other (External/Contest)', 0],
    4 => ['AtCoder', 0],
    5 => ['CodeChef', 0],
    6 => ['CSES', 0],
    7 => ['SPOJ', 0],
    8 => ['HackerRank', 0],
    9 => ['Topcoder', 0]
];

foreach ($seeding_platforms as $pid => $pinfo) {
    $pname = mysqli_real_escape_string($conn, $pinfo[0]);
    $papi = (int)$pinfo[1];
    
    $check_p = mysqli_query($conn, "SELECT id FROM platforms WHERE id = $pid");
    if (mysqli_num_rows($check_p) == 0) {
        mysqli_query($conn, "INSERT INTO platforms (id, name, has_free_api) VALUES ($pid, '$pname', $papi)");
    } else {
        mysqli_query($conn, "UPDATE platforms SET name = '$pname' WHERE id = $pid");
    }
}
?>