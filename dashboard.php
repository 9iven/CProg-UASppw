<?php
// --- INISIALISASI SESI & KONEKSI ---
session_start();
require 'config.php';

// Memeriksa apakah user sudah login. Jika belum, lempar kembali ke halaman login.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// --- SEEDING PLATFORM LAINNYA (Platform ID = 3 secara default) ---
// Memastikan platform penampung kustom 'Lainnya (Luar/Contest)' terdaftar di database.
$check_other_platform = mysqli_query($conn, "SELECT id FROM platforms WHERE name = 'Lainnya (Luar/Contest)' OR id = 3");
if (mysqli_num_rows($check_other_platform) == 0) {
    mysqli_query($conn, "INSERT IGNORE INTO platforms (id, name, has_free_api) VALUES (3, 'Lainnya (Luar/Contest)', 0)");
}

$message = ''; // Pesan notifikasi sukses/error untuk dicetak ke layar

// --- PROCESS POST HANDLER: MENAMBAHKAN SOAL KUSTOM ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_custom_problem') {
    $platform_id = (int)$_POST['platform_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $problem_url = mysqli_real_escape_string($conn, $_POST['problem_url']);
    $equivalent_rating = (int)$_POST['equivalent_rating'];
    
    // Format tanggal penyelesaian soal (solved_at)
    $solved_at = mysqli_real_escape_string($conn, $_POST['solved_at']);
    if (empty($solved_at)) {
        $solved_at = date('Y-m-d H:i:s');
    } else {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $solved_at)) {
            $solved_at .= ' ' . date('H:i:s'); // Append jam saat ini
        }
    }
    
    // Logika Pengunggahan Gambar Bukti (Screenshot / Proof Image)
    $proof_path = 'NULL';
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
        if (!is_dir('uploads/proofs')) {
            mkdir('uploads/proofs', 0777, true); // Buat foldernya jika belum ada
        }
        $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
        $filename = "proof_" . time() . "_" . $user_id . "." . $ext;
        $destination = "uploads/proofs/" . $filename;
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
            $proof_path = "'" . mysqli_real_escape_string($conn, $destination) . "'";
        }
    }
    
    // Memeriksa duplikasi URL soal agar data problems tetap unik
    $check_dup = mysqli_query($conn, "SELECT id FROM problems WHERE problem_url = '$problem_url'");
    if (mysqli_num_rows($check_dup) > 0) {
        // Soal sudah ada, langsung tautkan ke riwayat user (solved_problems)
        $dup_row = mysqli_fetch_assoc($check_dup);
        $new_problem_id = $dup_row['id'];
        $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                          VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                          ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
        if (mysqli_query($conn, $insert_solved)) {
            $_SESSION['success_msg'] = "Soal eksternal berhasil ditambahkan ke riwayat Anda.";
        } else {
            $_SESSION['error_msg'] = "Gagal menghubungkan soal ke riwayat Anda.";
        }
    } else {
        // Soal belum terdaftar, simpan data soal baru ke problems
        $insert_query = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom, created_by) 
                         VALUES ($platform_id, '$title', '$problem_url', $equivalent_rating, TRUE, $user_id)";
        
        if (mysqli_query($conn, $insert_query)) {
            $new_problem_id = mysqli_insert_id($conn);
            // Tautkan soal yang baru dibuat ke riwayat penyelesaian user
            $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                              VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                              ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
            mysqli_query($conn, $insert_solved);
            $_SESSION['success_msg'] = "Soal custom berhasil disimpan dan ditambahkan ke riwayat penyelesaian Anda.";
        } else {
            $_SESSION['error_msg'] = "Galat sistem saat menyimpan data: " . mysqli_error($conn);
        }
    }
    header("Location: dashboard.php");
    exit;
}

// Menampung pesan notifikasi dari session flash
if (isset($_SESSION['success_msg'])) {
    $message .= "<div class='alert-success'>" . $_SESSION['success_msg'] . "</div>";
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $message .= "<div class='alert-error'>" . $_SESSION['error_msg'] . "</div>";
    unset($_SESSION['error_msg']);
}

// Menarik daftar platform untuk opsi form modal
$modal_platforms_result = mysqli_query($conn, "SELECT id, name FROM platforms ORDER BY id ASC");

// --- 1. MENARIK INFORMASI METADATA USER ---
$user_display_name = explode('@', $email)[0]; // Fallback nama dari email
$profile_pic = null;

// Query gambar profil
$meta_res = mysqli_query($conn, "SELECT profile_picture FROM users WHERE id = $user_id");
if (mysqli_num_rows($meta_res) > 0) {
    $profile_pic = mysqli_fetch_assoc($meta_res)['profile_picture'];
}

// Ambil username / handle aktif pertama milik user sebagai nama tampilan utama
$handles_res = mysqli_query($conn, "SELECT username FROM user_handles WHERE user_id = $user_id LIMIT 1");
if (mysqli_num_rows($handles_res) > 0) {
    $user_display_name = mysqli_fetch_assoc($handles_res)['username'];
}

// --- 2. KALKULASI RATA-RATA RATING KEMAMPUAN USER ---
$avg_rating_query = "SELECT ROUND(AVG(p.equivalent_rating)) as avg_rating, COUNT(s.id) as total_solved 
                     FROM solved_problems s 
                     JOIN problems p ON s.problem_id = p.id 
                     WHERE s.user_id = $user_id";
$avg_result = mysqli_query($conn, $avg_rating_query);
$avg_data = mysqli_fetch_assoc($avg_result);

$avg_solved_rating = $avg_data['avg_rating'] ? (int)$avg_data['avg_rating'] : 0;
$total_solved_problems = $avg_data['total_solved'] ? (int)$avg_data['total_solved'] : 0;

// --- 3. LOGIKA ALGORITMA REKOMENDASI SOAL ---
// Merekomendasikan soal secara acak yang ratingnya setara s/d +300 di atas kemampuan rata-rata user saat ini.
$reco_result = null;
if ($avg_solved_rating > 0) {
    $target_min = $avg_solved_rating;
    $target_max = $avg_solved_rating + 300;

    $reco_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   WHERE p.equivalent_rating BETWEEN $target_min AND $target_max 
                   AND p.id NOT IN (SELECT problem_id FROM solved_problems WHERE user_id = $user_id)
                   ORDER BY RAND() LIMIT 5";
    $reco_result = mysqli_query($conn, $reco_query);
}

// --- 4. LOGIKA PENCARIAN & PAGINASI RIWAYAT AKTIVITAS ---
$search_solved = isset($_GET['search_solved']) ? mysqli_real_escape_string($conn, $_GET['search_solved']) : '';
$where_solved = "WHERE s.user_id = $user_id";

if (!empty($search_solved)) {
    $where_solved .= " AND p.title LIKE '%$search_solved%'";
}

$limit_solved = 10; // Jumlah baris per halaman
$page_solved = isset($_GET['page_solved']) ? (int)$_GET['page_solved'] : 1;
if ($page_solved < 1) $page_solved = 1;

// Hitung jumlah baris aktivitas pencarian
$count_solved_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM solved_problems s JOIN problems p ON s.problem_id = p.id $where_solved");
$total_solved_rows = mysqli_fetch_assoc($count_solved_result)['total'];
$total_solved_pages = ceil($total_solved_rows / $limit_solved);
$offset_solved = ($page_solved - 1) * $limit_solved;

// Ambil riwayat penyelesaian soal user terpaginasi
$solved_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name, s.solved_at, s.proof_image 
                 FROM solved_problems s
                 JOIN problems p ON s.problem_id = p.id
                 JOIN platforms pl ON p.platform_id = pl.id
                 $where_solved
                 ORDER BY s.solved_at DESC LIMIT $limit_solved OFFSET $offset_solved";
$solved_result = mysqli_query($conn, $solved_query);

// --- 5. EKSTRAKSI DATA UNTUK VISUALISASI GRAFIK (CHART.JS) ---

// Grafik 1: Riwayat Rating Kontes (Relatif terhadap waktu pencatatan)
$chart1_query = "SELECT rh.rating, DATE_FORMAT(rh.recorded_at, '%d %b') as date_val, pl.name as platform_name 
                 FROM rating_history rh
                 JOIN user_handles uh ON rh.user_handle_id = uh.id
                 JOIN platforms pl ON uh.platform_id = pl.id
                 WHERE uh.user_id = $user_id ORDER BY rh.recorded_at ASC";
$chart1_res = mysqli_query($conn, $chart1_query);
$c1_labels = []; $c1_data = [];
while ($row = mysqli_fetch_assoc($chart1_res)) {
    $c1_labels[] = $row['date_val'] . ' (' . $row['platform_name'] . ')';
    $c1_data[] = $row['rating'];
}

// Grafik 2: Tren Tingkat Kesulitan Soal Terselesaikan (Berdasarkan tanggal diselesaikan)
$chart2_query = "SELECT p.equivalent_rating, DATE_FORMAT(s.solved_at, '%d %b') as date_val 
                 FROM solved_problems s
                 JOIN problems p ON s.problem_id = p.id
                 WHERE s.user_id = $user_id ORDER BY s.solved_at ASC LIMIT 30";
$chart2_res = mysqli_query($conn, $chart2_query);
$c2_labels = []; $c2_data = [];
while ($row = mysqli_fetch_assoc($chart2_res)) {
    $c2_labels[] = $row['date_val'];
    $c2_data[] = $row['equivalent_rating'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <header class="dashboard-header">
        <div class="header-logo" style="display: flex; align-items: center;">
            <img src="assets/img/logo.png" alt="CProg Logo" class="custom-logo-img">
            <span>CProg <span class="text-accent-yellow">Tracker</span></span>
        </div>
        <div class="user-profile">
            <a href="manage_problems.php" style="color: #a1a1aa; text-decoration: none; margin-right: 20px; font-weight: bold;">&#128218; Kelola Soal</a>
            <a href="settings.php" style="color: #a1a1aa; text-decoration: none; margin-right: 20px; font-weight: bold;">&#9881; Settings</a>
            <a href="logout.php" class="btn-logout">Keluar</a>
        </div>
    </header>

    <main class="dashboard-container">
        <?php echo $message; ?>
        
        <section class="profile-banner">
            <div class="profile-content">
                <div class="profile-avatar">
                    <?php if (!empty($profile_pic)): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Avatar" style="width: 100%; height: 100%; border-radius: 16px; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_display_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user_display_name); ?></h2>
                    <p><?php echo htmlspecialchars($email); ?></p>
                </div>
                <div class="profile-stats">
                    <div class="stat-box">
                        <span class="stat-title">AVG SOLVED RATING</span>
                        <span class="stat-value text-accent-blue"><?php echo $avg_solved_rating; ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">TOTAL SOLVED</span>
                        <span class="stat-value text-accent-yellow"><?php echo $total_solved_problems; ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-grid" style="margin-bottom: 30px;">
            <div class="grid-card">
                <h3>Grafik Rating Kontes (Relatif)</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="contestChart"></canvas>
                </div>
            </div>
            <div class="grid-card">
                <h3>Tren Kesulitan Soal Terselesaikan</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="solvedChart"></canvas>
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            
            <div class="grid-card">
                <h3>Aktivitas Penyelesaian Utama</h3>
                <p class="card-desc">Riwayat soal terselesaikan dengan fitur pencarian dan paginasi.</p>
                
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <form action="dashboard.php" method="GET" style="display: flex; gap: 10px; flex-grow: 1; margin: 0;">
                        <input type="text" name="search_solved" class="form-control" placeholder="Cari aktivitas..." value="<?php echo htmlspecialchars($search_solved); ?>" style="flex-grow: 1;">
                        <button type="submit" class="btn-submit" style="width: auto; padding: 0 20px; margin-top: 0; background-color: #facc15; color: #121212;">Cari</button>
                        <?php if(!empty($search_solved)): ?>
                            <a href="dashboard.php" class="btn-submit" style="width: auto; padding: 12px 20px; margin-top: 0; background-color: #444; color: #fff; text-decoration: none; display: flex; align-items: center;">Reset</a>
                        <?php endif; ?>
                    </form>
                    <button type="button" class="btn-submit" id="openModalBtn" style="width: auto; padding: 12px 20px; margin-top: 0; background-color: #ff007f; color: #fff; border: none; font-weight: bold; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <span>+ Tambah Soal Custom</span>
                    </button>
                </div>

                <?php if(mysqli_num_rows($solved_result) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php while($solved = mysqli_fetch_assoc($solved_result)): ?>
                            <li style="padding: 12px 0; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <a href="<?php echo htmlspecialchars($solved['problem_url']); ?>" target="_blank" class="text-accent-blue" style="text-decoration: none; font-weight: bold; font-size: 1.05rem;">
                                        <?php echo htmlspecialchars($solved['title']); ?>
                                    </a>
                                    <span style="font-size: 0.75rem; color: #a1a1aa; margin-left: 8px; background-color: #2a2a2a; padding: 3px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($solved['platform_name']); ?>
                                    </span>
                                    <?php if(!empty($solved['proof_image'])): ?>
                                        <a href="<?php echo htmlspecialchars($solved['proof_image']); ?>" target="_blank" style="font-size: 0.75rem; color: #ec4899; margin-left: 8px; text-decoration: underline;">Lihat Bukti</a>
                                    <?php endif; ?>
                                </div>
                                <span style="color: #e0e0e0; font-size: 0.9rem; font-weight: bold;">
                                    Rating: <span class="text-accent-yellow"><?php echo $solved['equivalent_rating']; ?></span>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #a1a1aa; font-size: 0.9rem;">Belum ada aktivitas yang tercatat untuk pencarian tersebut.</p>
                <?php endif; ?>

                <?php if ($total_solved_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center;">
                    <?php for($i = 1; $i <= $total_solved_pages; $i++): ?>
                        <a href="?page_solved=<?php echo $i; ?>&search_solved=<?php echo urlencode($search_solved); ?>" 
                           style="padding: 8px 14px; text-decoration: none; border-radius: 4px; font-weight: bold; <?php echo ($i == $page_solved) ? 'background-color: #00f0ff; color: #121212;' : 'background-color: #2a2a2a; color: #e0e0e0; border: 1px solid #444;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="grid-card">
                <h3>Rekomendasi Soal Adaptif</h3>
                <?php if($avg_solved_rating > 0): ?>
                    <p class="card-desc">Berdasarkan kapabilitas rata-rata Anda (<strong><?php echo $avg_solved_rating; ?></strong>), berikut adalah target optimal Anda:</p>
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
                        <p>Sistem membutuhkan lebih banyak data riwayat penyelesaian soal untuk menghitung rata-rata kemampuan Anda secara akurat.</p>
                    </div>
                <?php endif; ?>
            </div>

        </section>

    </main>

    <!-- Modal untuk Tambah Soal Custom -->
    <div id="customProblemModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeModalBtn">&times;</span>
            <h3 style="margin-top: 0; margin-bottom: 10px; color: #00f0ff; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
                Registrasi Soal Custom
            </h3>
            <p style="color: #a1a1aa; font-size: 0.85rem; margin-bottom: 20px;">Tambahkan soal mandiri dari luar (misal: AtCoder, HackerRank, Virtual Judge, atau link kontes eksternal).</p>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input type="hidden" name="action" value="add_custom_problem">
                
                <div class="form-group" style="grid-column: span 2; margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Tautan Soal (URL) <span style="color: #ec4899;">*</span></label>
                    <input type="url" name="problem_url" id="modalUrlInput" class="form-control" placeholder="https://atcoder.jp/contests/... atau link lainnya" required style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px;">
                </div>
                
                <div class="form-group" style="grid-column: span 2; margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Judul Soal <span style="color: #ec4899;">*</span></label>
                    <input type="text" name="title" id="modalTitleInput" class="form-control" placeholder="Nama / Judul Soal" required style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Platform Asal <span style="color: #ec4899;">*</span></label>
                    <select name="platform_id" class="form-control" required style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px;">
                        <option value="">-- Pilih Platform --</option>
                        <?php while($plat = mysqli_fetch_assoc($modal_platforms_result)): ?>
                            <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Ekuivalensi Rating <span style="color: #ec4899;">*</span></label>
                    <input type="number" name="equivalent_rating" class="form-control" placeholder="Contoh: 1200" required style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Tanggal Diselesaikan <span style="color: #ec4899;">*</span></label>
                    <input type="date" name="solved_at" class="form-control" required value="<?php echo date('Y-m-d'); ?>" style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #a0a0a0; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block;">Unggah Bukti Gambar <span style="color: #71717a; font-weight: normal;">(Opsional)</span></label>
                    <input type="file" name="proof_image" class="form-control" accept="image/*" style="background-color: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 6px; padding: 8px;">
                </div>
                
                <button type="submit" class="btn-submit" style="grid-column: span 2; margin-top: 10px; background-color: #3b82f6; font-weight: bold; border-radius: 6px; border: none; height: 45px; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s, transform 0.2s;">Simpan Soal</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Chart.defaults.color = '#a1a1aa';
            Chart.defaults.scale.grid.color = '#333333';

            const ctxContest = document.getElementById('contestChart').getContext('2d');
            new Chart(ctxContest, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($c1_labels); ?>,
                    datasets: [{
                        label: 'Rating Kontes',
                        data: <?php echo json_encode($c1_data); ?>,
                        borderColor: '#00f0ff', backgroundColor: 'rgba(0, 240, 255, 0.1)',
                        borderWidth: 2, pointBackgroundColor: '#ff007f',
                        fill: true, tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            const ctxSolved = document.getElementById('solvedChart').getContext('2d');
            new Chart(ctxSolved, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($c2_labels); ?>,
                    datasets: [{
                        label: 'Tingkat Kesulitan (Rating)',
                        data: <?php echo json_encode($c2_data); ?>,
                        borderColor: '#facc15', backgroundColor: 'rgba(250, 204, 21, 0.1)',
                        borderWidth: 2, pointBackgroundColor: '#ffffff',
                        fill: true, tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            
            // Modal Logic
            const modal = document.getElementById('customProblemModal');
            const openBtn = document.getElementById('openModalBtn');
            const closeBtn = document.getElementById('closeModalBtn');
            
            if (openBtn && modal) {
                openBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden'; 
                });
            }
            
            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
            
            // Auto-fetch title for modal url input
            const modalUrlInput = document.getElementById('modalUrlInput');
            const modalTitleInput = document.getElementById('modalTitleInput');
            
            if (modalUrlInput && modalTitleInput) {
                modalUrlInput.addEventListener('blur', function() {
                    const url = this.value;
                    if (url && !modalTitleInput.value) {
                        modalTitleInput.placeholder = "Mengambil data otomatis...";
                        fetch('fetch_title.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ url: url })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                modalTitleInput.value = data.title;
                            } else {
                                modalTitleInput.placeholder = "Gagal mengambil judul, silakan isi manual.";
                            }
                        })
                        .catch(err => console.error('Fetch error:', err));
                    }
                });
            }
        });
    </script>
</body>
</html>