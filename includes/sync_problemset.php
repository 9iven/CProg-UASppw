<?php
session_start();
require '../config/db.php';

// Authorization validation
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/helpers.php';

// Fetch all problemset data from Codeforces API
$api_url = "https://codeforces.com/api/problemset.problems";
$res = http_get_request($api_url, 30);
$response = $res['body'];
$http_code = $res['code'];

if ($http_code == 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK') {
        $problems = $data['result']['problems'];
        $count_inserted = 0;

        /** @var mysqli $conn */
        // Use Prepared Statements to optimize bulk insertion speed
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom) VALUES (1, ?, ?, ?, FALSE)");

        foreach ($problems as $prob) {
            // Only retrieve problems that have an official rating attribute
            if (isset($prob['rating'])) {
                $title = $prob['name'];
                $rating = $prob['rating'];
                $contestId = $prob['contestId'];
                $index = $prob['index'];
                $url = "https://codeforces.com/contest/$contestId/problem/$index";

                mysqli_stmt_bind_param($stmt, "ssi", $title, $url, $rating);
                mysqli_stmt_execute($stmt);

                // Count the number of rows successfully inserted (not duplicates)
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $count_inserted++;
                }
            }
        }
        mysqli_stmt_close($stmt);
        
        $_SESSION['success_msg'] = "Successfully fetched $count_inserted new problems from Codeforces to your local database.";
    } else {
        $_SESSION['error_msg'] = "Codeforces API responded with an error.";
    }
} else {
    $_SESSION['error_msg'] = "Failed to connect to Codeforces server. Please make sure your internet connection is stable.";
}

header("Location: ../dashboard.php");
exit;
?>