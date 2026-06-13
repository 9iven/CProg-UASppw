<?php
require_once __DIR__ . '/helpers.php';

function insert_or_update_solved_problem($conn, $user_id, $platform_id, $prob_name, $prob_url, $prob_rating, $solved_date) {
    $check_prob = "SELECT id FROM problems WHERE platform_id = $platform_id AND title = '$prob_name'";
    $res_prob = mysqli_query($conn, $check_prob);
    
    if (mysqli_num_rows($res_prob) > 0) {
        $prob_row = mysqli_fetch_assoc($res_prob);
        $db_problem_id = $prob_row['id'];
    } else {
        $rating_val = is_callable($prob_rating) ? call_user_func($prob_rating) : (int)$prob_rating;
        $ins_prob = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom) VALUES ($platform_id, '$prob_name', '$prob_url', $rating_val, FALSE)";
        mysqli_query($conn, $ins_prob);
        $db_problem_id = mysqli_insert_id($conn);
    }
    
    $ins_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at) VALUES ($user_id, $db_problem_id, '$solved_date') ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at)";
    mysqli_query($conn, $ins_solved);
}

function sync_platform($user_id, $platform_id, $handle_username, $conn) {
    $safe_username = mysqli_real_escape_string($conn, $handle_username);
    
    // CODEFORCES
    if ($platform_id == 1) {
        $api_url = "https://codeforces.com/api/user.info?handles=" . urlencode($handle_username);
        $res = http_get_request($api_url);

        if ($res['code'] == 200 && $res['body']) {
            $data = json_decode($res['body'], true);
            if ($data['status'] === 'OK') {
                $user_info = $data['result'][0];
                $current_rating = isset($user_info['rating']) ? (int)$user_info['rating'] : 0;

                $check_query = "SELECT id, username FROM user_handles WHERE user_id = $user_id AND platform_id = 1";
                $check_result = mysqli_query($conn, $check_query);

                if (mysqli_num_rows($check_result) > 0) {
                    $row = mysqli_fetch_assoc($check_result);
                    $handle_id = $row['id'];
                    $old_username = $row['username'];
                    
                    if ($old_username !== $safe_username) {
                        $purge_query = "DELETE FROM solved_problems WHERE user_id = $user_id AND problem_id IN (SELECT id FROM problems WHERE platform_id = 1 AND is_custom = FALSE)";
                        mysqli_query($conn, $purge_query);
                    }

                    $update_query = "UPDATE user_handles SET username = '$safe_username', current_rating = $current_rating WHERE id = $handle_id";
                    mysqli_query($conn, $update_query);
                } else {
                    $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, 1, '$safe_username', $current_rating)";
                    mysqli_query($conn, $insert_query);
                    $handle_id = mysqli_insert_id($conn);
                }

                $rating_url = "https://codeforces.com/api/user.rating?handle=" . urlencode($handle_username);
                $res_rating = http_get_request($rating_url);
                if ($res_rating['code'] == 200 && $res_rating['body']) {
                    $rating_data = json_decode($res_rating['body'], true);
                    if (isset($rating_data['status']) && $rating_data['status'] === 'OK') {
                        mysqli_query($conn, "DELETE FROM rating_history WHERE user_handle_id = $handle_id");
                        foreach ($rating_data['result'] as $rc) {
                            $r_val = (int)$rc['newRating'];
                            $r_date = date('Y-m-d H:i:s', $rc['ratingUpdateTimeSeconds']);
                            mysqli_query($conn, "INSERT INTO rating_history (user_handle_id, rating, recorded_at) VALUES ($handle_id, $r_val, '$r_date')");
                        }
                    }
                }
                
                $status_url = "https://codeforces.com/api/user.status?handle=" . urlencode($handle_username) . "&from=1&count=300";
                $res_status = http_get_request($status_url);

                if ($res_status['code'] == 200 && $res_status['body']) {
                    $status_data = json_decode($res_status['body'], true);
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
                                
                                
                                insert_or_update_solved_problem($conn, $user_id, 1, $prob_name, $prob_url, $prob_rating, $solved_date);
                            }
                        }
                    }
                }
                return ['success' => true, 'message' => "Codeforces synced."];
            } else {
                return ['success' => false, 'message' => "Codeforces handle not found."];
            }
        } else {
            return ['success' => false, 'message' => "Codeforces API error."];
        }
    } 
    // LEETCODE
    else if ($platform_id == 2) {
        $fetch_leetcode = function($endpoint) {
            $urls = [
                "https://alfa-leetcode-api.vercel.app/" . $endpoint,
                "https://alfa-leetcode-api.onrender.com/" . $endpoint
            ];
            foreach ($urls as $url) {
                $res = http_get_request($url, 8);
                if ($res['code'] == 200 && $res['body']) {
                    $data = json_decode($res['body'], true);
                    if ($data && !isset($data['errors']) && !isset($data['error']) && !isset($data['message'])) {
                        return $res['body'];
                    }
                }
            }
            return false;
        };

        $subs_response = $fetch_leetcode(urlencode($handle_username) . "/acSubmission?limit=300");

        if ($subs_response) {
            $subs_data = json_decode($subs_response, true);
            if (isset($subs_data['submission'])) {
                $contest_rating = 0;
                $contest_response = $fetch_leetcode(urlencode($handle_username) . "/contest");
                if ($contest_response) {
                    $contest_data = json_decode($contest_response, true);
                    if (isset($contest_data['contestRating'])) {
                        $contest_rating = (int)$contest_data['contestRating'];
                    }
                }

                $check_query = "SELECT id, username FROM user_handles WHERE user_id = $user_id AND platform_id = 2";
                $check_result = mysqli_query($conn, $check_query);

                if (mysqli_num_rows($check_result) > 0) {
                    $row = mysqli_fetch_assoc($check_result);
                    $handle_id = $row['id'];
                    $old_username = $row['username'];
                    
                    if ($old_username !== $safe_username) {
                        $purge_query = "DELETE FROM solved_problems WHERE user_id = $user_id AND problem_id IN (SELECT id FROM problems WHERE platform_id = 2 AND is_custom = FALSE)";
                        mysqli_query($conn, $purge_query);
                    }

                    $update_query = "UPDATE user_handles SET username = '$safe_username', current_rating = $contest_rating WHERE id = $handle_id";
                    mysqli_query($conn, $update_query);
                } else {
                    $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, 2, '$safe_username', $contest_rating)";
                    mysqli_query($conn, $insert_query);
                    $handle_id = mysqli_insert_id($conn);
                }

                if (isset($contest_data['contestParticipation']) && is_array($contest_data['contestParticipation']) && count($contest_data['contestParticipation']) > 0) {
                    mysqli_query($conn, "DELETE FROM rating_history WHERE user_handle_id = $handle_id");
                    foreach ($contest_data['contestParticipation'] as $participation) {
                        if (isset($participation['rating'])) {
                            $r_val = (int)$participation['rating'];
                            $timestamp = isset($participation['contest']['startTime']) ? $participation['contest']['startTime'] : time();
                            $r_date = date('Y-m-d H:i:s', $timestamp);
                            mysqli_query($conn, "INSERT INTO rating_history (user_handle_id, rating, recorded_at) VALUES ($handle_id, $r_val, '$r_date')");
                        }
                    }
                } else if ($contest_rating > 0) {
                    $last_rating_query = "SELECT rating FROM rating_history WHERE user_handle_id = $handle_id ORDER BY recorded_at DESC LIMIT 1";
                    $last_rating_res = mysqli_query($conn, $last_rating_query);
                    $should_insert_rating = true;
                    if (mysqli_num_rows($last_rating_res) > 0) {
                        $last_rating_row = mysqli_fetch_assoc($last_rating_res);
                        if ($last_rating_row['rating'] == $contest_rating) {
                            $should_insert_rating = false;
                        }
                    }
                    
                    if ($should_insert_rating) {
                        $history_query = "INSERT INTO rating_history (user_handle_id, rating) VALUES ($handle_id, $contest_rating)";
                        mysqli_query($conn, $history_query);
                    }
                }

                foreach ($subs_data['submission'] as $submission) {
                    $prob_name = mysqli_real_escape_string($conn, $submission['title']);
                    $title_slug = $submission['titleSlug'];
                    $prob_url = "https://leetcode.com/problems/" . $title_slug;
                    
                    $solved_timestamp = isset($submission['timestamp']) ? (int)$submission['timestamp'] : time();
                    $solved_date = date('Y-m-d H:i:s', $solved_timestamp);

                    $rating_fetcher = function() use ($fetch_leetcode, $title_slug) {
                        $prob_rating = 1000;
                        $select_response = $fetch_leetcode("select?titleSlug=" . urlencode($title_slug));
                        if ($select_response) {
                            $select_data = json_decode($select_response, true);
                            if (isset($select_data['difficulty'])) {
                                $difficulty = $select_data['difficulty'];
                                if ($difficulty === 'Easy') return 800;
                                if ($difficulty === 'Medium') return 1200;
                                if ($difficulty === 'Hard') return 1600;
                            }
                        }
                        return $prob_rating;
                    };

                    insert_or_update_solved_problem($conn, $user_id, 2, $prob_name, $prob_url, $rating_fetcher, $solved_date);
                }
                return ['success' => true, 'message' => "LeetCode synced."];
            } else {
                return ['success' => false, 'message' => "LeetCode handle not found."];
            }
        } else {
            return ['success' => false, 'message' => "LeetCode API error."];
        }
    }
    // OTHER PLATFORMS (Manual entry update)
    else {
        $check_query = "SELECT id, username FROM user_handles WHERE user_id = $user_id AND platform_id = $platform_id";
        $check_result = mysqli_query($conn, $check_query);
    
        if (mysqli_num_rows($check_result) > 0) {
            $row = mysqli_fetch_assoc($check_result);
            $handle_id = $row['id'];
            $old_username = $row['username'];
            
            if ($old_username !== $safe_username) {
                $purge_query = "DELETE FROM solved_problems WHERE user_id = $user_id AND problem_id IN (SELECT id FROM problems WHERE platform_id = $platform_id AND is_custom = FALSE)";
                mysqli_query($conn, $purge_query);
            }

            $update_query = "UPDATE user_handles SET username = '$safe_username' WHERE id = $handle_id";
            mysqli_query($conn, $update_query);
        } else {
            $insert_query = "INSERT INTO user_handles (user_id, platform_id, username, current_rating) VALUES ($user_id, $platform_id, '$safe_username', 0)";
            mysqli_query($conn, $insert_query);
        }
        return ['success' => true, 'message' => "Platform saved."];
    }
}
?>
