<?php
// Memulai session dan memastikan user telah login
session_start();

if (!isset($_SESSION['user_id'])) {
    // Jika tidak ada session user_id, alihkan ke halaman login
    header("Location: login.php");
    exit;
}

// Memuat konfigurasi database
require 'config.php';

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="dashboard-header">
        <div class="header-logo">CProg <span class="text-accent-yellow">Viewer</span></div>
        <div class="user-profile">
            <span><?php echo htmlspecialchars($email); ?></span>
            <a href="logout.php" class="btn-logout">Keluar</a>
        </div>
    </header>

    <main class="dashboard-container">
        
        <section class="dashboard-summary">
            <div class="summary-card">
                <h3>Codeforces Rating</h3>
                <p class="rating-value text-accent-blue">0</p>
                <span class="sub-text">Belum dihubungkan</span>
            </div>
            <div class="summary-card">
                <h3>LeetCode Solved</h3>
                <p class="rating-value text-accent-pink">0</p>
                <span class="sub-text">Belum dihubungkan</span>
            </div>
            <div class="summary-card">
                <h3>Custom Problems</h3>
                <p class="rating-value text-accent-yellow">0</p>
                <span class="sub-text">Total soal manual</span>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="grid-card">
                <h3>Hubungkan Platform</h3>
                <p class="card-desc">Masukkan username Anda untuk menarik data statistik secara otomatis.</p>
                </div>

            <div class="grid-card">
                <h3>Rekomendasi Soal Teroptimasi</h3>
                <p class="card-desc">Berdasarkan rating Anda saat ini, berikut adalah soal yang disarankan:</p>
                <div class="placeholder-list">
                    <p>Silakan hubungkan akun atau tambah data soal manual terlebih dahulu untuk memuat rekomendasi.</p>
                </div>
            </div>
        </section>

    </main>

</body>
</html>