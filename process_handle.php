<?php
session_start();
require 'config.php';

// Validasi akses otorisasi dan metode HTTP
if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cf_username = trim($_POST['cf_username']);

// 1. Eksekusi cURL untuk melakukan request ke API Codeforces
$api_url = "https://codeforces.com/api/user.info?handles=" . urlencode($cf_username);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Menonaktifkan verifikasi SSL lokal untuk mencegah galat XAMPP
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 2. Validasi Respons HTTP
if ($http_code == 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK') {
        $user_info = $data['result'][0];
        // Mengamankan nilai rating jika user berstatus unrated
        $current_rating = isset($user_info['rating']) ? (int)$user_info['rating'] : 0;
        
        // Asumsi ID Codeforces di tabel platforms adalah 1
        $platform_id = 1; 

        // 3. Operasi UPSERT (Update jika ada, Insert jika baru) pada tabel user_handles
        // Mengecek apakah record sudah ada
        $check_query = "SELECT id FROM user_handles WHERE user_id = $user_id AND platform_id = $platform_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Lakukan operasi UPDATE
            $row = mysqli_fetch_assoc($check_result);
            $handle_id = $row['id'];
            $safe_username = mysqli_real_escape_string($conn, $cf_username);
            
            $update_query = "UPDATE user_handles SET username = '$safe_username', current_rating = $current_rating WHERE id = $handle_id";
            mysqli_query($conn, $update_query);
        } else {
            // Lakukan operasi INSERT
            $safe_username = mysqli_real_escape_string($conn, $cf_username);
            $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, $platform_id, '$safe_username', $current_rating)";
            mysqli_query($conn, $insert_query);
            
            // Mendapatkan ID yang baru disisipkan untuk history
            $handle_id = mysqli_insert_id($conn);
        }

        // 4. Merekam entri riwayat metrik pada tabel rating_history
        $history_query = "INSERT INTO rating_history (user_handle_id, rating) VALUES ($handle_id, $current_rating)";
        mysqli_query($conn, $history_query);
        
    } else {
        $_SESSION['error_msg'] = "Username Codeforces tidak ditemukan pada API.";
    }
} else {
    $_SESSION['error_msg'] = "Gagal terhubung ke server Codeforces (HTTP $http_code).";
}

// Mengalihkan kembali ke dashboard setelah pemrosesan selesai
header("Location: dashboard.php");
exit;
?>