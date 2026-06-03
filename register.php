<?php
// Memulai sesi untuk manajemen state
session_start();

// Memuat parameter konfigurasi database
require 'config.php';

// Inisialisasi variabel pesan notifikasi
$error_message = '';
$success_message = '';

// Memeriksa apakah form telah disubmit melalui metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Membersihkan input untuk mencegah SQL Injection
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi apakah password dan konfirmasi password cocok
    if ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak cocok dengan password yang dimasukkan.";
    } else {
        // Memeriksa apakah email sudah terdaftar di database
        $check_email_query = "SELECT id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_email_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error_message = "Alamat email tersebut sudah terdaftar. Silakan gunakan email lain atau lakukan login.";
        } else {
            // Melakukan hashing pada password sebelum disimpan (Standar Keamanan)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Melakukan instruksi query INSERT ke tabel users
            $insert_query = "INSERT INTO users (email, password) VALUES ('$email', '$hashed_password')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success_message = "Registrasi berhasil! Silakan menuju halaman login.";
            } else {
                $error_message = "Terjadi kesalahan pada sistem: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <main class="auth-wrapper">
        <section class="auth-box">
            <h2>Buat Akun Baru</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert-error" style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; border-color: #2ecc71;">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Kata Sandi</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="btn-submit">Daftar</button>
            </form>

            <div class="auth-footer">
                Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
            </div>
        </section>
    </main>

</body>
</html>