<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- PROSES SUBMIT FORM POST (TAMBAH / HAPUS SOAL MANDIRI) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // AKSI 1: Menambahkan Soal Custom Baru
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $platform_id = (int)$_POST['platform_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $problem_url = mysqli_real_escape_string($conn, $_POST['problem_url']);
        $equivalent_rating = (int)$_POST['equivalent_rating'];
        
        // Membaca dan memformat tanggal penyelesaian (solved_at)
        $solved_at = mysqli_real_escape_string($conn, $_POST['solved_at']);
        if (empty($solved_at)) {
            $solved_at = date('Y-m-d H:i:s'); // Fallback ke waktu server saat ini
        } else {
            // Jika input bertipe YYYY-MM-DD, tambahkan jam saat ini agar format TIMESTAMP MySQL lengkap
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $solved_at)) {
                $solved_at .= ' ' . date('H:i:s');
            }
        }
        
        // Logika Pengunggahan Gambar Bukti (Proof Image)
        $proof_path = 'NULL'; // Default jika tidak mengunggah file
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
            $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
            // Memberikan nama unik menggunakan timestamp dan id user
            $filename = "proof_" . time() . "_" . $user_id . "." . $ext;
            $destination = "uploads/proofs/" . $filename;
            
            // Pindahkan file temporary ke folder destinasi
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
                $proof_path = "'" . mysqli_real_escape_string($conn, $destination) . "'";
            }
        }
        
        // Cek duplikasi URL soal untuk menghindari double insert pada tabel problems
        $check_dup = mysqli_query($conn, "SELECT id FROM problems WHERE problem_url = '$problem_url'");
        if (mysqli_num_rows($check_dup) > 0) {
            // Soal sudah ada di db, cukup hubungkan ke riwayat user saja
            $dup_row = mysqli_fetch_assoc($check_dup);
            $new_problem_id = $dup_row['id'];
            $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                              VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                              ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
            
            if (mysqli_query($conn, $insert_solved)) {
                $message = "<div class='alert-success'>Soal berhasil ditambahkan ke riwayat penyelesaian Anda!</div>";
            } else {
                $message = "<div class='alert-error'>Gagal menghubungkan soal ke riwayat Anda.</div>";
            }
        } else {
            // Soal belum ada, daftarkan baru ke tabel problems terlebih dahulu
            $insert_query = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom, created_by) 
                             VALUES ($platform_id, '$title', '$problem_url', $equivalent_rating, TRUE, $user_id)";
            
            if (mysqli_query($conn, $insert_query)) {
                $new_problem_id = mysqli_insert_id($conn);
                // Hubungkan ke riwayat penyelesaian user
                $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                                  VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                                  ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
                mysqli_query($conn, $insert_solved);
                $message = "<div class='alert-success'>Soal custom dan bukti berhasil disimpan!</div>";
            } else {
                $message = "<div class='alert-error'>Gagal menyimpan soal ke database: " . mysqli_error($conn) . "</div>";
            }
        }
    } 
    
    // AKSI 2: Menghapus Soal Custom yang Pernah Ditambahkan
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $problem_id = (int)$_POST['problem_id'];
        
        // Hapus dari tabel problems (karena relasi cascading/manual, pastikan hanya menghapus milik sendiri)
        $delete_query = "DELETE FROM problems WHERE id = $problem_id AND created_by = $user_id AND is_custom = TRUE";
        mysqli_query($conn, $delete_query);
        $message = "<div class='alert-success'>Soal berhasil dihapus dari repositori Anda.</div>";
    }
}

// Menarik daftar platform untuk elemen select pilihan platform asal
$platforms_query = "SELECT id, name FROM platforms";
$platforms_result = mysqli_query($conn, $platforms_query);

// --- LOGIKA SEARCH (PENCARIAN) & PAGINATION ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = "WHERE p.created_by = $user_id AND p.is_custom = TRUE";

// Tambahkan pencarian kata kunci jika di-input
if (!empty($search)) {
    $where_clause .= " AND p.title LIKE '%$search%'";
}

// Konfigurasi limit baris per halaman
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Hitung total baris data yang cocok dengan kriteria pencarian
$count_query = "SELECT COUNT(*) as total FROM problems p $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);
$offset = ($page - 1) * $limit; // Hitung titik awal pemotongan data (offset)

// Ambil data soal yang spesifik sesuai offset halaman saat ini
$problems_query = "SELECT p.id, p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   $where_clause 
                   ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$problems_result = mysqli_query($conn, $problems_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Soal - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="dashboard-header">
        <div class="header-logo" style="display: flex; align-items: center;">
            <img src="assets/img/logo.png" alt="CProg Logo" class="custom-logo-img">
            <span>CProg <span class="text-accent-yellow">Viewer</span></span>
        </div>
        <div class="user-profile">
            <a href="dashboard.php" class="btn-logout" style="background-color: #3b82f6; margin-right: 10px;">Kembali ke Dashboard</a>
        </div>
    </header>

    <main class="dashboard-container">
        <?php echo $message; ?>
        
        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            
            <div class="grid-card">
                <h3>Registrasi Soal Mandiri</h3>
                <p class="card-desc">Tambahkan soal <em>custom</em> dan sertakan tangkapan layar sebagai bukti validasi.</p>
                
                <form action="manage_problems.php" method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Platform Asal</label>
                        <select name="platform_id" class="form-control" required>
                            <option value="">-- Pilih Platform --</option>
                            <?php mysqli_data_seek($platforms_result, 0); ?>
                            <?php while($plat = mysqli_fetch_assoc($platforms_result)): ?>
                                <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ekuivalensi Rating</label>
                        <input type="number" name="equivalent_rating" class="form-control" placeholder="Contoh: 1400" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Judul Soal</label>
                        <input type="text" name="title" id="titleInput" class="form-control" placeholder="Nama Soal" required>
                    </div>
                    <div class="form-group">
                        <label>Tautan Soal (URL)</label>
                        <input type="url" name="problem_url" id="urlInput" class="form-control" placeholder="https://..." required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Diselesaikan</label>
                        <input type="date" name="solved_at" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Unggah Bukti Gambar (Opsional)</label>
                        <input type="file" name="proof_image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn-submit" style="grid-column: span 2;">Simpan Soal</button>
                </form>
            </div>

            <div class="grid-card">
                <h3>Koleksi Soal Anda</h3>
                
                <form action="manage_problems.php" method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan judul soal..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-submit" style="width: auto; padding: 0 20px; margin-top: 0; background-color: #facc15; color: #121212;">Cari</button>
                    <?php if(!empty($search)): ?>
                        <a href="manage_problems.php" class="btn-submit" style="width: auto; padding: 12px 20px; margin-top: 0; background-color: #444; color: #fff; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </form>

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Judul Soal</th>
                                <th>Platform</th>
                                <th>Rating</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($problems_result) > 0): ?>
                                <?php while($prob = mysqli_fetch_assoc($problems_result)): ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($prob['problem_url']); ?>" target="_blank" class="text-accent-yellow"><?php echo htmlspecialchars($prob['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($prob['platform_name']); ?></td>
                                    <td><?php echo $prob['equivalent_rating']; ?></td>
                                    <td>
                                        <form action="manage_problems.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="problem_id" value="<?php echo $prob['id']; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Menghapus soal akan menghilangkannya dari riwayat penyelesaian Anda. Lanjutkan?');">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #a1a1aa; padding: 20px;">Data soal tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center;">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                           style="padding: 8px 14px; text-decoration: none; border-radius: 4px; font-weight: bold; <?php echo ($i == $page) ? 'background-color: #00f0ff; color: #121212;' : 'background-color: #2a2a2a; color: #e0e0e0; border: 1px solid #444;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <script>
        const urlInput = document.getElementById('urlInput');
        const titleInput = document.getElementById('titleInput');

        urlInput.addEventListener('blur', function() {
            const url = this.value;
            if (url && !titleInput.value) { 
                titleInput.placeholder = "Mengambil data otomatis...";
                fetch('fetch_title.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        titleInput.value = data.title;
                    } else {
                        titleInput.placeholder = "Gagal mengambil judul, silakan isi manual.";
                    }
                })
                .catch(err => console.error('Fetch error:', err));
            }
        });
    </script>
</body>
</html>