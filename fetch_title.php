<?php
// Menerima input URL berformat JSON dari JavaScript Fetch API
$data = json_decode(file_get_contents('php://input'), true);
$url = isset($data['url']) ? trim($data['url']) : '';

if (filter_var($url, FILTER_VALIDATE_URL)) {
    // Membaca konten HTML menggunakan cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'); // Menyamarkan request sebagai browser
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $html = curl_exec($ch);
    curl_close($ch);

    // Menggunakan regex untuk mengekstrak string di dalam tag <title>
    if ($html && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim($matches[1]);
        // Membersihkan atribut tambahan yang sering menempel (misal: " - LeetCode")
        $title = explode(' - ', $title)[0]; 
        echo json_encode(['success' => true, 'title' => htmlspecialchars_decode($title)]);
        exit;
    }
}
echo json_encode(['success' => false]);
?>