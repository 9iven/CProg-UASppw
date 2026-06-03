<?php
$host = "localhost";
$user = "root";
$password = "";
$db_name = "cp_viewer";

/** @var mysqli $conn */
$conn = mysqli_connect($host, $user, $password, $db_name);

if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
?>