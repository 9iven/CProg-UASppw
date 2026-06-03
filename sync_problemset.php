<?php
session_start();
require 'config.php';

// Validasi otorisasi
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Menarik seluruh data problemset dari Codeforces API
$api_url = "https://codeforces.com/api/problemset.problems";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Memberikan batas waktu (timeout) agar peladen lokal tidak hang
curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK') {
        $problems = $data['result']['problems'];
        $count_inserted = 0;

        /** @var mysqli $conn */
        // Menggunakan Prepared Statement untuk optimasi kecepatan insert massal
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom) VALUES (1, ?, ?, ?, FALSE)");

        foreach ($problems as $prob) {
            // Hanya mengambil soal yang memiliki atribut rating resmi
            if (isset($prob['rating'])) {
                $title = $prob['name'];
                $rating = $prob['rating'];
                $contestId = $prob['contestId'];
                $index = $prob['index'];
                $url = "https://codeforces.com/contest/$contestId/problem/$index";

                mysqli_stmt_bind_param($stmt, "ssi", $title, $url, $rating);
                mysqli_stmt_execute($stmt);

                // Menghitung jumlah baris yang berhasil disisipkan (bukan duplikat)
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $count_inserted++;
                }
            }
        }
        mysqli_stmt_close($stmt);
        
        $_SESSION['success_msg'] = "Berhasil menarik $count_inserted soal baru dari Codeforces ke database lokal Anda.";
    } else {
        $_SESSION['error_msg'] = "API Codeforces merespons dengan galat.";
    }
} else {
    $_SESSION['error_msg'] = "Gagal menghubungi server Codeforces. Pastikan koneksi internet Anda stabil.";
}

header("Location: dashboard.php");
exit;
?>