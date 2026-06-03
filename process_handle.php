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
        
        // ====================================================================
        // BLOK KODE 5 (API STATUS/SOLVED PROBLEMS) DIMASUKKAN DI SINI
        // ====================================================================
        
        // 5. Menarik riwayat Solved Problems dari Codeforces
        // Membatasi 50 submission terakhir agar peladen lokal tidak kehabisan memori
        $status_url = "https://codeforces.com/api/user.status?handle=" . urlencode($cf_username) . "&from=1&count=50";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $status_url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        $status_response = curl_exec($ch2);
        $status_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($status_code == 200 && $status_response) {
            $status_data = json_decode($status_response, true);
            
            if ($status_data['status'] === 'OK') {
                foreach ($status_data['result'] as $submission) {
                    // Hanya memproses submission yang berhasil (Accepted)
                    if ($submission['verdict'] === 'OK') {
                        $prob = $submission['problem'];
                        $prob_name = mysqli_real_escape_string($conn, $prob['name']);
                        // URL format Codeforces: https://codeforces.com/contest/{contestId}/problem/{index}
                        $contest_id = isset($prob['contestId']) ? $prob['contestId'] : 0;
                        $prob_index = isset($prob['index']) ? $prob['index'] : '';
                        $prob_url = "https://codeforces.com/contest/$contest_id/problem/$prob_index";
                        $prob_rating = isset($prob['rating']) ? (int)$prob['rating'] : 800; // Default 800 jika unrated problem
                        
                        // Cek apakah soal ini sudah ada di bank soal (tabel problems)
                        $check_prob = "SELECT id FROM problems WHERE platform_id = 1 AND title = '$prob_name'";
                        $res_prob = mysqli_query($conn, $check_prob);
                        
                        if (mysqli_num_rows($res_prob) > 0) {
                            $prob_row = mysqli_fetch_assoc($res_prob);
                            $db_problem_id = $prob_row['id'];
                        } else {
                            // Insert soal baru ke tabel problems (created_by = NULL karena dari API)
                            $ins_prob = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom) 
                                         VALUES (1, '$prob_name', '$prob_url', $prob_rating, FALSE)";
                            mysqli_query($conn, $ins_prob);
                            $db_problem_id = mysqli_insert_id($conn);
                        }
                        
                        // Masukkan relasi ke solved_problems menggunakan INSERT IGNORE untuk mencegah duplikasi
                        $ins_solved = "INSERT IGNORE INTO solved_problems (user_id, problem_id) VALUES ($user_id, $db_problem_id)";
                        mysqli_query($conn, $ins_solved);
                    }
                }
            }
        }
        // ====================================================================

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