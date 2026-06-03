<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// 1. Menarik data handle dan metrik Codeforces
$cf_rating = 0;
$cf_status = "Belum dihubungkan";

$query_cf = "SELECT username, current_rating FROM user_handles WHERE user_id = $user_id AND platform_id = 1";
$result_cf = mysqli_query($conn, $query_cf);

if (mysqli_num_rows($result_cf) > 0) {
    $row = mysqli_fetch_assoc($result_cf);
    $cf_rating = $row['current_rating'];
    $cf_status = htmlspecialchars($row['username']);
}

// 2. Logika Algoritma Rekomendasi
$reco_result = null;
if ($cf_rating > 0) {
    $target_min = $cf_rating;
    $target_max = $target_min + 200;

    $reco_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   WHERE p.equivalent_rating BETWEEN $target_min AND $target_max 
                   AND p.id NOT IN (SELECT problem_id FROM solved_problems WHERE user_id = $user_id)
                   ORDER BY RAND() LIMIT 5";
    $reco_result = mysqli_query($conn, $reco_query);
}

// 3. Menarik 5 soal terakhir yang diselesaikan user untuk ditampilkan di dashboard
$solved_query = "SELECT p.title, p.problem_url, p.equivalent_rating, s.solved_at 
                 FROM solved_problems s
                 JOIN problems p ON s.problem_id = p.id
                 WHERE s.user_id = $user_id
                 ORDER BY s.solved_at DESC LIMIT 5";
$solved_result = mysqli_query($conn, $solved_query);
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
            
            <div class="summary-card">
                <h3>Custom Problems</h3>
                <p class="rating-value text-accent-yellow">CRUD</p>
                <a href="manage_problems.php" class="sub-text" style="color: #facc15; text-decoration: none;">Kelola Soal Mandiri &#8594;</a>
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
                if(isset($_SESSION['error_msg'])) {
                    echo '<div class="alert-error" style="margin-top: 15px;">' . $_SESSION['error_msg'] . '</div>';
                    unset($_SESSION['error_msg']);
                }
                ?>
            </div>

            <div class="grid-card">
                <h3>Hubungkan Platform</h3>
                <p class="card-desc">Masukkan username Codeforces Anda untuk menarik data statistik secara otomatis.</p>
                
                <form action="process_handle.php" method="POST">
                    <div class="form-group">
                        <input type="text" name="cf_username" class="form-control" placeholder="Username Codeforces" required>
                    </div>
                    <button type="submit" class="btn-submit">Sinkronisasi Codeforces</button>
                </form>

                <hr style="border-color: #333; margin: 20px 0;">
                <p class="card-desc">Perbarui bank soal lokal dengan data terbaru dari Codeforces agar sistem dapat memberikan rekomendasi yang akurat.</p>
                <form action="sync_problemset.php" method="POST">
                    <button type="submit" class="btn-submit" style="background-color: #facc15; color: #121212;">Tarik Bank Soal Global (Codeforces)</button>
                </form>

                <?php 
                // Menampilkan notifikasi galat
                if(isset($_SESSION['error_msg'])) {
                    echo '<div class="alert-error" style="margin-top: 15px;">' . $_SESSION['error_msg'] . '</div>';
                    unset($_SESSION['error_msg']);
                }
                // Menampilkan notifikasi sukses (untuk jumlah soal ditarik)
                if(isset($_SESSION['success_msg'])) {
                    echo '<div class="alert-success" style="margin-top: 15px; background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid #2ecc71; padding: 10px; border-radius: 4px;">' . $_SESSION['success_msg'] . '</div>';
                    unset($_SESSION['success_msg']);
                }
                ?>
            </div>

            <div class="grid-card" style="margin-top: 20px;">
                <h3>Aktivitas Terbaru</h3>
                <p class="card-desc">Riwayat penyelesaian soal (*Solved*) terbaru Anda.</p>
                <?php if(mysqli_num_rows($solved_result) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php while($solved = mysqli_fetch_assoc($solved_result)): ?>
                            <li style="padding: 10px 0; border-bottom: 1px solid #333;">
                                <a href="<?php echo htmlspecialchars($solved['problem_url']); ?>" target="_blank" class="text-accent-blue" style="text-decoration: none; font-weight: bold;">
                                    <?php echo htmlspecialchars($solved['title']); ?>
                                </a>
                                <span style="float: right; color: #a1a1aa; font-size: 0.85rem;">
                                    Rating: <?php echo $solved['equivalent_rating']; ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #a1a1aa; font-size: 0.9rem;">Belum ada riwayat soal terselesaikan.</p>
                <?php endif; ?>
            </div>

            <div class="grid-card">
                <h3>Rekomendasi Soal Teroptimasi</h3>
                <?php if($cf_rating > 0): ?>
                    <p class="card-desc">Target latihan: <strong><?php echo $target_min; ?> - <?php echo $target_max; ?></strong> <em>rating</em>.</p>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Soal</th>
                                    <th>Platform</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($reco = mysqli_fetch_assoc($reco_result)): ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($reco['problem_url']); ?>" target="_blank" class="text-accent-yellow" style="text-decoration: none;"><?php echo htmlspecialchars($reco['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($reco['platform_name']); ?></td>
                                    <td><?php echo $reco['equivalent_rating']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="placeholder-list">
                        <p>Status: <strong>Unknown</strong>. Silakan sinkronisasikan <em>username</em> Codeforces Anda terlebih dahulu untuk memuat rekomendasi yang dipersonalisasi.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
</body>
</html>