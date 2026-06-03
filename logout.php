<?php
session_start();

// Menghapus seluruh data session aktif
session_unset();
session_destroy();

// Mengalihkan pengguna kembali ke halaman login
header("Location: login.php");
exit;
?>