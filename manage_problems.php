<?php
session_start();
require 'config.php';

// Validasi otorisasi sesi
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Memproses instruksi POST (Create & Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // Operasi CREATE
        $platform_id = (int)$_POST['platform_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $problem_url = mysqli_real_escape_string($conn, $_POST['problem_url']);
        $equivalent_rating = (int)$_POST['equivalent_rating'];
        
        $insert_query = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom, created_by) 
                         VALUES ($platform_id, '$title', '$problem_url', $equivalent_rating, TRUE, $user_id)";
        
        if (mysqli_query($conn, $insert_query)) {
            $message = "<div class='alert-success'>Soal berhasil ditambahkan ke repositori Anda.</div>";
        } else {
            $message = "<div class='alert-error'>Galat sistem saat menyimpan data: " . mysqli_error($conn) . "</div>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        // Operasi DELETE
        $problem_id = (int)$_POST['problem_id'];
        
        // Validasi keamanan ekstra: memastikan soal yang dihapus benar-benar milik user tersebut
        $delete_query = "DELETE FROM problems WHERE id = $problem_id AND created_by = $user_id AND is_custom = TRUE";
        mysqli_query($conn, $delete_query);
        $message = "<div class='alert-success'>Soal berhasil dihapus.</div>";
    }
}

// Menarik daftar platform untuk dropdown (Read)
$platforms_query = "SELECT id, name FROM platforms";
$platforms_result = mysqli_query($conn, $platforms_query);

// Menarik daftar soal mandiri yang dibuat oleh user saat ini (Read)
$problems_query = "SELECT p.id, p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   WHERE p.created_by = $user_id AND p.is_custom = TRUE 
                   ORDER BY p.id DESC";
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
        <div class="header-logo">CProg <span class="text-accent-yellow">Viewer</span></div>
        <div class="user-profile">
            <a href="dashboard.php" class="btn-logout" style="background-color: #3b82f6; margin-right: 10px;">Kembali ke Dashboard</a>
        </div>
    </header>

    <main class="dashboard-container">
        <?php echo $message; ?>
        
        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            <div class="grid-card">
                <h3>Registrasi Soal Mandiri</h3>
                <p class="card-desc">Tambahkan soal dari platform lain dan tentukan ekuivalensi ratingnya.</p>
                
                <form action="manage_problems.php" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Platform Asal</label>
                        <select name="platform_id" class="form-control" required>
                            <option value="">-- Pilih Platform --</option>
                            <?php while($plat = mysqli_fetch_assoc($platforms_result)): ?>
                                <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ekuivalensi Rating (Skala Codeforces)</label>
                        <input type="number" name="equivalent_rating" class="form-control" placeholder="Contoh: 1400" required>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Judul Soal</label>
                        <input type="text" name="title" class="form-control" placeholder="Nama Soal" required>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label>Tautan Soal (URL)</label>
                        <input type="url" name="problem_url" class="form-control" placeholder="https://..." required>
                    </div>

                    <button type="submit" class="btn-submit" style="grid-column: span 2;">Simpan Soal</button>
                </form>
            </div>

            <div class="grid-card">
                <h3>Koleksi Soal Anda</h3>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Judul Soal</th>
                                <th>Platform</th>
                                <th>Ekuivalensi Rating</th>
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
                                            <button type="submit" class="btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus soal ini?');">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #a1a1aa;">Belum ada soal mandiri yang didaftarkan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

</body>
</html>