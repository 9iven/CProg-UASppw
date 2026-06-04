<?php
// --- CONFIGURASI KONEKSI DATABASE ---
// File ini berfungsi untuk menghubungkan aplikasi PHP dengan database MySQL.

$host     = "localhost";  // Alamat server database (karena lokal, kita pakai localhost)
$user     = "root";       // Username database bawaan XAMPP
$password = "";           // Password database bawaan XAMPP (kosong secara default)
$db_name  = "cp_viewer";  // Nama database yang kita gunakan untuk projek ini

// Membuat koneksi ke database menggunakan fungsi mysqli_connect
/** @var mysqli $conn */
$conn = mysqli_connect($host, $user, $password, $db_name);

// Memeriksa apakah koneksi berhasil. Jika gagal, hentikan program dan tampilkan pesan error.
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
?>