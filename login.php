<?php
// Inisialisasi session untuk melacak status login user
session_start();

// Memuat parameter konfigurasi database
require 'config.php';

// Inisialisasi variabel untuk menampung pesan galat
$error_message = '';

// Memeriksa apakah form telah disubmit melalui metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Melakukan query untuk mencari user berdasarkan email
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $user_data = mysqli_fetch_assoc($result);
        
        // Memverifikasi kecocokan hash password
        if (password_verify($password, $user_data['password'])) {
            // Mengatur variabel session jika autentikasi berhasil
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['email'] = $user_data['email'];
            
            // Mengalihkan (redirect) user ke halaman dashboard utama
            header("Location: dashboard.php");
            exit;
        } else {
            $error_message = "Kredensial password yang Anda masukkan tidak valid.";
        }
    } else {
        $error_message = "Alamat email tidak terdaftar di dalam sistem.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <main class="auth-wrapper">
        <section class="auth-box">
            <h2>Masuk ke Akun</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn-submit">Masuk</button>
            </form>

            <div class="auth-footer">
                Belum memiliki akun? <a href="register.php">Daftar sekarang</a>
            </div>
        </section>
    </main>

</body>
</html>