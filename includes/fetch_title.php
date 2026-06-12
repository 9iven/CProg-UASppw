<?php
// Receive URL input in JSON format from JavaScript Fetch API
$data = json_decode(file_get_contents('php://input'), true);
$url = isset($data['url']) ? trim($data['url']) : '';

require_once __DIR__ . '/helpers.php';

if (filter_var($url, FILTER_VALIDATE_URL)) {
    $parsed_url = parse_url($url);
    $host = isset($parsed_url['host']) ? strtolower($parsed_url['host']) : '';

    // --- CASE 1: LEETCODE (Using alfa-leetcode-api to bypass Cloudflare) ---
    if (strpos($host, 'leetcode.com') !== false) {
        if (preg_match('/\/problems\/([^\/]+)/', $url, $slug_matches)) {
            $title_slug = $slug_matches[1];
            $lc_api_urls = [
                "https://alfa-leetcode-api.vercel.app/select?titleSlug=" . urlencode($title_slug),
                "https://alfa-leetcode-api.onrender.com/select?titleSlug=" . urlencode($title_slug)
            ];
            foreach ($lc_api_urls as $api_url) {
                $res_lc = http_get_request($api_url, 6);
                if ($res_lc['code'] == 200 && $res_lc['body']) {
                    $lc_data = json_decode($res_lc['body'], true);
                    if (isset($lc_data['questionTitle'])) {
                        $difficulty = isset($lc_data['difficulty']) ? $lc_data['difficulty'] : '';
                        $rating = 1000;
                        if ($difficulty === 'Easy') {
                            $rating = 800;
                        } elseif ($difficulty === 'Medium') {
                            $rating = 1200;
                        } elseif ($difficulty === 'Hard') {
                            $rating = 1600;
                        }
                        echo json_encode([
                            'success' => true, 
                            'title' => $lc_data['questionTitle'],
                            'rating' => $rating
                        ]);
                        exit;
                    }
                }
            }
        }
    }

    // --- CASE 2: CODEFORCES (Using official API to bypass Cloudflare) ---
    if (strpos($host, 'codeforces.com') !== false) {
        $contest_id = '';
        $problem_index = '';
        
        // Pattern A: /contest/{contestId}/problem/{index}
        if (preg_match('/\/contest\/(\d+)\/problem\/([A-Za-z\d]+)/', $url, $matches)) {
            $contest_id = $matches[1];
            $problem_index = strtoupper($matches[2]);
        }
        // Pattern B: /problemset/problem/{contestId}/{index}
        else if (preg_match('/\/problemset\/problem\/(\d+)\/([A-Za-z\d]+)/', $url, $matches)) {
            $contest_id = $matches[1];
            $problem_index = strtoupper($matches[2]);
        }
        
        if (!empty($contest_id) && !empty($problem_index)) {
            $cf_api_url = "https://codeforces.com/api/contest.standings?contestId=" . $contest_id;
            $res_cf = http_get_request($cf_api_url, 6);
            
            if ($res_cf['code'] == 200 && $res_cf['body']) {
                $cf_data = json_decode($res_cf['body'], true);
                if (isset($cf_data['status']) && $cf_data['status'] === 'OK' && isset($cf_data['result']['problems'])) {
                    foreach ($cf_data['result']['problems'] as $prob) {
                        if (strcasecmp($prob['index'], $problem_index) === 0) {
                            $rating = isset($prob['rating']) ? (int)$prob['rating'] : null;
                            echo json_encode([
                                'success' => true, 
                                'title' => $prob['name'],
                                'rating' => $rating
                            ]);
                            exit;
                        }
                    }
                }
            }
        }
    }

    // --- GENERAL CASE: SCRAPING HTML & DOMAIN-SPECIFIC CLEANUP ---
    $res_scrape = http_get_request($url, 8);
    $html = $res_scrape['body'];
    $http_code = $res_scrape['code'];

    if ($http_code == 200 && $html && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim($matches[1]);
        $title = htmlspecialchars_decode($title);
        
        // Filter out Cloudflare challenges or browser checking pages
        $lower_title = strtolower($title);
        if (strpos($lower_title, 'just a moment') !== false || 
            strpos($lower_title, 'cloudflare') !== false || 
            strpos($lower_title, 'attention required') !== false ||
            strpos($lower_title, 'security check') !== false ||
            strpos($lower_title, 'ddos') !== false ||
            strpos($lower_title, 'checking your browser') !== false) {
            echo json_encode(['success' => false]);
            exit;
        }

        // Domain-specific cleanup rules to extract clean problem names
        if (strpos($host, 'atcoder.jp') !== false) {
            // AtCoder format: "A - Five Variables - AtCoder Beginner Contest 123"
            $parts = explode(' - ', $title);
            if (count($parts) >= 2) {
                if (strlen(trim($parts[0])) <= 3) {
                    $title = trim($parts[0]) . ' - ' . trim($parts[1]);
                } else {
                    $title = trim($parts[0]);
                }
            }
        } else if (strpos($host, 'spoj.com') !== false) {
            // SPOJ format: "SPOJ.com - Problem TEST"
            $parts = explode(' - ', $title);
            if (count($parts) >= 2) {
                $title = trim($parts[1]);
            }
        } else if (strpos($host, 'hackerrank.com') !== false) {
            // HackerRank format: "Solve Me First | HackerRank"
            $parts = explode(' | ', $title);
            $title = trim($parts[0]);
        } else if (strpos($host, 'cses.fi') !== false) {
            // CSES format: "CSES - Weird Algorithm"
            $parts = explode(' - ', $title);
            if (count($parts) >= 2) {
                if (strcasecmp(trim($parts[0]), 'cses') === 0) {
                    $title = trim($parts[1]);
                } else {
                    $title = trim($parts[0]);
                }
            }
        } else if (strpos($host, 'codechef.com') !== false) {
            // CodeChef format: "FLOW001 Problem - CodeChef"
            $parts = explode(' | ', $title);
            $title = trim($parts[0]);
            $parts2 = explode(' - ', $title);
            $title = trim($parts2[0]);
            $title = preg_replace('/\s+Problem$/i', '', $title);
        } else if (strpos($host, 'topcoder.com') !== false) {
            // Topcoder format: "Topcoder Problem Statement"
            $title = str_ireplace(['Problem Statement', 'Topcoder', ' | '], '', $title);
            $title = trim($title, " \t\n\r\0\x0B-");
        } else {
            // Default cleanup (splits by " - " or " | ")
            if (strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title);
                $title = trim($parts[0]);
            } else if (strpos($title, ' | ') !== false) {
                $parts = explode(' | ', $title);
                $title = trim($parts[0]);
            }
        }
        
        echo json_encode(['success' => true, 'title' => $title]);
        exit;
    }
}
echo json_encode(['success' => false]);
?>