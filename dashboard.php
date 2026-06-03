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
// Jika user belum memiliki rating (0), target default adalah 800. Jika ada, batas atas adalah +200 poin.
$target_min = $cf_rating > 0 ? $cf_rating : 800;
$target_max = $target_min + 200;

// Query untuk mencari soal dalam rentang rating yang BELUM diselesaikan oleh user
$reco_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
               FROM problems p 
               JOIN platforms pl ON p.platform_id = pl.id 
               WHERE p.equivalent_rating BETWEEN $target_min AND $target_max 
               AND p.id NOT IN (SELECT problem_id FROM solved_problems WHERE user_id = $user_id)
               ORDER BY RAND() LIMIT 5";
$reco_result = mysqli_query($conn, $reco_query);
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
                <h3>Rekomendasi Soal Teroptimasi</h3>
                <p class="card-desc">Target latihan: <strong><?php echo $target_min; ?> - <?php echo $target_max; ?></strong> <em>rating</em>.</p>
                
                <?php if(mysqli_num_rows($reco_result) > 0): ?>
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
                        <p>Belum ada soal dalam rentang <em>rating</em> ini di repositori yang belum Anda selesaikan. Silakan tambahkan soal melalui menu Kelola Soal Mandiri.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
</body>
</html>