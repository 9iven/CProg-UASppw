<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Menarik data handle dan metrik rating Codeforces dari database (jika sudah dihubungkan)
$cf_rating = 0;
$cf_status = "Belum dihubungkan";

// Menyesuaikan dengan ID platform Codeforces yang di-seed pada tabel platforms (ID = 1)
$query_cf = "SELECT username, current_rating FROM user_handles WHERE user_id = $user_id AND platform_id = 1";
$result_cf = mysqli_query($conn, $query_cf);

if (mysqli_num_rows($result_cf) > 0) {
    $row = mysqli_fetch_assoc($result_cf);
    $cf_rating = $row['current_rating'];
    $cf_status = htmlspecialchars($row['username']);
}
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
                <p class="rating-value text-accent-blue"><?php echo $cf_rating; ?></p>
                <span class="sub-text"><?php echo $cf_status; ?></span>
            </div>
            </section>

        <section class="dashboard-grid">
            <div class="grid-card">
                <h3>Hubungkan Platform</h3>
                <p class="card-desc">Masukkan username Codeforces Anda untuk menarik data statistik secara otomatis.</p>
                
                <form action="process_handle.php" method="POST">
                    <div class="form-group">
                        <input type="text" name="cf_username" class="form-control" placeholder="Username Codeforces" required>
                    </div>
                    <button type="submit" class="btn-submit">Sinkronisasi Codeforces</button>
                </form>

                <?php 
                // Menampilkan notifikasi jika ada galat saat sinkronisasi
                if(isset($_SESSION['error_msg'])) {
                    echo '<div class="alert-error" style="margin-top: 15px;">' . $_SESSION['error_msg'] . '</div>';
                    unset($_SESSION['error_msg']);
                }
                ?>
            </div>

            <div class="grid-card">
                <h3>Rekomendasi Soal Teroptimasi</h3>
                <p class="card-desc">Berdasarkan rating Anda saat ini, berikut adalah soal yang disarankan:</p>
                <div class="placeholder-list">
                    <p>Fungsionalitas algoritma rekomendasi akan dimuat pada iterasi berikutnya.</p>
                </div>
            </div>
        </section>

    </main>
</body>
</html>