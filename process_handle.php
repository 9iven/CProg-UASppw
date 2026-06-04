<?php
session_start();
require 'config.php';

// Validasi akses otorisasi dan metode HTTP POST
if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: settings.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$platform_id = (int)$_POST['platform_id'];
$handle_username = trim($_POST['cf_username']); // Input name dari form settings
$safe_username = mysqli_real_escape_string($conn, $handle_username);

// LOGIKA KHUSUS CODEFORCES (Asumsi Platform ID = 1)
if ($platform_id === 1) {
    $api_url = "https://codeforces.com/api/user.info?handles=" . urlencode($handle_username);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK') {
            $user_info = $data['result'][0];
            $current_rating = isset($user_info['rating']) ? (int)$user_info['rating'] : 0;

            // Operasi UPSERT ke tabel user_handles
            $check_query = "SELECT id FROM user_handles WHERE user_id = $user_id AND platform_id = 1";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                $row = mysqli_fetch_assoc($check_result);
                $handle_id = $row['id'];
                $update_query = "UPDATE user_handles SET username = '$safe_username', current_rating = $current_rating WHERE id = $handle_id";
                mysqli_query($conn, $update_query);
            } else {
                $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, 1, '$safe_username', $current_rating)";
                mysqli_query($conn, $insert_query);
                $handle_id = mysqli_insert_id($conn);
            }

            // Merekam entri riwayat metrik
            $history_query = "INSERT INTO rating_history (user_handle_id, rating) VALUES ($handle_id, $current_rating)";
            mysqli_query($conn, $history_query);
            
            // Menarik riwayat Solved Problems Codeforces
            $status_url = "https://codeforces.com/api/user.status?handle=" . urlencode($handle_username) . "&from=1&count=50";
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $status_url);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $status_response = curl_exec($ch2);
            curl_close($ch2);

            if ($status_response) {
                $status_data = json_decode($status_response, true);
                if (isset($status_data['status']) && $status_data['status'] === 'OK') {
                    foreach ($status_data['result'] as $submission) {
                        if ($submission['verdict'] === 'OK') {
                            $prob = $submission['problem'];
                            $prob_name = mysqli_real_escape_string($conn, $prob['name']);
                            $contest_id = isset($prob['contestId']) ? $prob['contestId'] : 0;
                            $prob_index = isset($prob['index']) ? $prob['index'] : '';
                            $prob_url = "https://codeforces.com/contest/$contest_id/problem/$prob_index";
                            $prob_rating = isset($prob['rating']) ? (int)$prob['rating'] : 800; 
                            
                            $solved_timestamp = isset($submission['creationTimeSeconds']) ? (int)$submission['creationTimeSeconds'] : time();
                            $solved_date = date('Y-m-d H:i:s', $solved_timestamp);
                            
                            $check_prob = "SELECT id FROM problems WHERE platform_id = 1 AND title = '$prob_name'";
                            $res_prob = mysqli_query($conn, $check_prob);
                            
                            if (mysqli_num_rows($res_prob) > 0) {
                                $prob_row = mysqli_fetch_assoc($res_prob);
                                $db_problem_id = $prob_row['id'];
                            } else {
                                $ins_prob = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom) VALUES (1, '$prob_name', '$prob_url', $prob_rating, FALSE)";
                                mysqli_query($conn, $ins_prob);
                                $db_problem_id = mysqli_insert_id($conn);
                            }
                            
                            $ins_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at) VALUES ($user_id, $db_problem_id, '$solved_date') ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at)";
                            mysqli_query($conn, $ins_solved);
                        }
                    }
                }
            }
            $_SESSION['success_msg'] = "Handle Codeforces dan riwayat soal berhasil disinkronisasi.";
        } else {
            $_SESSION['error_msg'] = "Username Codeforces tidak ditemukan pada sistem.";
        }
    } else {
        $_SESSION['error_msg'] = "Gagal terhubung ke peladen Codeforces.";
    }
} 
// LOGIKA UNTUK PLATFORM LAIN (Tanpa API Sementara)
else {
    $check_query = "SELECT id FROM user_handles WHERE user_id = $user_id AND platform_id = $platform_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $row = mysqli_fetch_assoc($check_result);
        $handle_id = $row['id'];
        $update_query = "UPDATE user_handles SET username = '$safe_username' WHERE id = $handle_id";
        mysqli_query($conn, $update_query);
    } else {
        $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, $platform_id, '$safe_username', 0)";
        mysqli_query($conn, $insert_query);
    }
    $_SESSION['success_msg'] = "Handle platform berhasil didaftarkan secara manual.";
}

// Mengalihkan kembali ke halaman Pengaturan
header("Location: settings.php");
exit;
?>