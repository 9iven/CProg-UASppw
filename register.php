<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi Akun - CP Recommender</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="hero-section">
        <div class="hero-content">
            <h2>Buat Akun Baru</h2>
            <form action="proses_register.php" method="POST" class="auth-form">
                <div class="input-group">
                    <label for="email">Alamat Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Kata Sandi:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="cta-button">Daftar</button>
            </form>
        </div>
    </div>
</body>
</html>