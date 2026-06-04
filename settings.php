<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Memproses POST (Upload Avatar atau Delete Handle)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Logika Upload Profile Picture
    if (isset($_POST['action']) && $_POST['action'] == 'upload_avatar') {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = "avatar_" . $user_id . "_" . time() . "." . $ext;
            $destination = "uploads/avatars/" . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                mysqli_query($conn, "UPDATE users SET profile_picture = '$destination' WHERE id = $user_id");
                $message = "<div class='alert-success'>Profile picture berhasil diperbarui.</div>";
            } else {
                $message = "<div class='alert-error'>Galat sistem saat memproses upload file gambar.</div>";
            }
        }
    }
    // Logika Reset Data Handle
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_handle') {
        $handle_id = (int)$_POST['handle_id'];
        $platform_id = (int)$_POST['platform_id'];
        
        // Menghapus histori rating
        mysqli_query($conn, "DELETE FROM rating_history WHERE user_handle_id = $handle_id");
        // Menghapus handle
        mysqli_query($conn, "DELETE FROM user_handles WHERE id = $handle_id");
        // Membersihkan riwayat solved problems yang bukan berasal dari input custom manual
        $purge_query = "DELETE FROM solved_problems WHERE user_id = $user_id AND problem_id IN (SELECT id FROM problems WHERE platform_id = $platform_id AND is_custom = FALSE)";
        mysqli_query($conn, $purge_query);
        
        $message = "<div class='alert-success'>Handle beserta riwayat datanya telah direset dari sistem.</div>";
    }
}

// Menarik daftar platform
$platforms_query = "SELECT * FROM platforms";
$platforms_result = mysqli_query($conn, $platforms_query);

// Menarik handle yang sudah terdaftar
$handles_query = "SELECT uh.id as handle_id, uh.platform_id, uh.username, pl.name as platform_name 
                  FROM user_handles uh 
                  JOIN platforms pl ON uh.platform_id = pl.id 
                  WHERE uh.user_id = $user_id";
$handles_result = mysqli_query($conn, $handles_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - CProg Viewer</title>
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
        <?php 
        if(isset($_SESSION['success_msg'])) {
            echo "<div class='alert-success'>" . $_SESSION['success_msg'] . "</div>";
            unset($_SESSION['success_msg']);
        }
        ?>

        <section class="dashboard-grid">
            <div class="grid-card">
                <h3>Kustomisasi Profil</h3>
                <p class="card-desc">Unggah <em>profile picture</em> Anda. Gambar ini akan merepresentasikan identitas Anda di halaman <em>dashboard</em>.</p>
                
                <form action="settings.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="form-group">
                        <label>Pilih Berkas Gambar</label>
                        <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn-submit" style="background-color: #facc15; color: #121212;">Simpan Profil</button>
                </form>
            </div>

            <div class="grid-card">
                <h3>Manajemen Handle Platform</h3>
                <p class="card-desc">Tambahkan <em>username</em> atau <em>handle</em> dari situs kompetisi pemrograman Anda.</p>
                
                <form action="process_handle.php" method="POST">
                    <div class="form-group">
                        <label>Pilih Platform</label>
                        <select name="platform_id" class="form-control" required>
                            <?php while($plat = mysqli_fetch_assoc($platforms_result)): ?>
                                <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Username / Handle</label>
                        <input type="text" name="cf_username" class="form-control" placeholder="Contoh: givengerald" required>
                    </div>
                    <button type="submit" class="btn-submit">Simpan & Sinkronisasi</button>
                </form>
            </div>

            <div class="grid-card" style="grid-column: span 2;">
                <h3>Handle Terdaftar & Reset Data</h3>
                <p class="card-desc">Daftar identitas yang telah terhubung ke sistem. Anda dapat mereset data jika terjadi kesalahan sinkronisasi.</p>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Handle</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($handles_result) > 0): ?>
                                <?php while($handle = mysqli_fetch_assoc($handles_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($handle['platform_name']); ?></td>
                                    <td class="text-accent-yellow"><?php echo htmlspecialchars($handle['username']); ?></td>
                                    <td>
                                        <form action="settings.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_handle">
                                            <input type="hidden" name="handle_id" value="<?php echo $handle['handle_id']; ?>">
                                            <input type="hidden" name="platform_id" value="<?php echo $handle['platform_id']; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Mereset handle ini akan menghapus semua riwayat penyelesaian yang terkait dengan akun ini. Lanjutkan?');">Hapus & Reset Data</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align:center; color:#a1a1aa;">Belum ada handle terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>