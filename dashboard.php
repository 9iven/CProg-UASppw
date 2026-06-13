<?php
// --- INITIALIZE SESSION & CONNECTION ---
session_start();
require 'config/db.php';

// Check if the user is logged in. If not, redirect back to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Note: Platform database seeding is centrally managed in config/db.php on load

$message = ''; // Success/error notification messages to be printed to screen

// Retrieve notification messages from session flash
if (isset($_SESSION['success_msg'])) {
    $message .= "<div class='alert alert-success'>" . $_SESSION['success_msg'] . "</div>";
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $message .= "<div class='alert alert-error'>" . $_SESSION['error_msg'] . "</div>";
    unset($_SESSION['error_msg']);
}

// Retrieve platform list for modal select options
$modal_platforms_result = mysqli_query($conn, "SELECT id, name FROM platforms ORDER BY id ASC");

// --- 1. RETRIEVE USER METADATA ---
$user_display_name = explode('@', $email)[0]; // Fallback user display name from email
$profile_pic = null;

// Query profile picture
$meta_res = mysqli_query($conn, "SELECT profile_picture FROM users WHERE id = $user_id");
if (mysqli_num_rows($meta_res) > 0) {
    $profile_pic = mysqli_fetch_assoc($meta_res)['profile_picture'];
}

// Use the first registered platform handle/username as the primary display name
$handles_res = mysqli_query($conn, "SELECT username FROM user_handles WHERE user_id = $user_id LIMIT 1");
if (mysqli_num_rows($handles_res) > 0) {
    $user_display_name = mysqli_fetch_assoc($handles_res)['username'];
}

// Retrieve all synced handles for the user profile header links
$user_handles_list = mysqli_query($conn, "SELECT uh.platform_id, uh.username, pl.name as platform_name 
                                          FROM user_handles uh 
                                          JOIN platforms pl ON uh.platform_id = pl.id 
                                          WHERE uh.user_id = $user_id");

// --- 2. CALCULATE USER CAPABILITY RATING AVERAGE ---
$avg_rating_query = "SELECT ROUND(AVG(p.equivalent_rating)) as avg_rating, COUNT(s.id) as total_solved 
                     FROM solved_problems s 
                     JOIN problems p ON s.problem_id = p.id 
                     WHERE s.user_id = $user_id";
$avg_result = mysqli_query($conn, $avg_rating_query);
$avg_data = mysqli_fetch_assoc($avg_result);

$avg_solved_rating = $avg_data['avg_rating'] ? (int)$avg_data['avg_rating'] : 0;
$total_solved_problems = $avg_data['total_solved'] ? (int)$avg_data['total_solved'] : 0;

// --- 3. TARGET CAPABILITY RECOMMENDATION LOGIC ---
// Recommend random problems with ratings between current user average and user average + 300.
$reco_result = null;
if ($avg_solved_rating > 0) {
    $target_min = $avg_solved_rating;
    $target_max = $avg_solved_rating + 300;

    $reco_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   WHERE p.equivalent_rating BETWEEN $target_min AND $target_max 
                   AND p.id NOT IN (SELECT problem_id FROM solved_problems WHERE user_id = $user_id)
                   ORDER BY RAND() LIMIT 5";
    $reco_result = mysqli_query($conn, $reco_query);
}

// --- 4. SEARCH & PAGINATION LOGIC FOR SOLVED HISTORY ---
$search_solved = isset($_GET['search_solved']) ? mysqli_real_escape_string($conn, $_GET['search_solved']) : '';
$where_solved = "WHERE s.user_id = $user_id";

if (!empty($search_solved)) {
    $where_solved .= " AND p.title LIKE '%$search_solved%'";
}

$limit_solved = 10; // Number of rows per page
$page_solved = isset($_GET['page_solved']) ? (int)$_GET['page_solved'] : 1;
if ($page_solved < 1) $page_solved = 1;

// Count search activity rows
$count_solved_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM solved_problems s JOIN problems p ON s.problem_id = p.id $where_solved");
$total_solved_rows = mysqli_fetch_assoc($count_solved_result)['total'];
$total_solved_pages = ceil($total_solved_rows / $limit_solved);
$offset_solved = ($page_solved - 1) * $limit_solved;

// Retrieve paginated solved problems history
$solved_query = "SELECT p.title, p.problem_url, p.equivalent_rating, pl.name AS platform_name, s.solved_at, s.proof_image 
                 FROM solved_problems s
                 JOIN problems p ON s.problem_id = p.id
                 JOIN platforms pl ON p.platform_id = pl.id
                 $where_solved
                 ORDER BY s.solved_at DESC LIMIT $limit_solved OFFSET $offset_solved";
$solved_result = mysqli_query($conn, $solved_query);

// --- 5. EXTRACT DATA FOR VISUALIZATION GRAPH (CHART.JS) ---

// Chart 1: Contest Rating History (Relative to recording time)
$chart1_query = "SELECT rh.rating, DATE_FORMAT(rh.recorded_at, '%d %b %Y') as date_val, pl.name as platform_name 
                 FROM rating_history rh
                 JOIN user_handles uh ON rh.user_handle_id = uh.id
                 JOIN platforms pl ON uh.platform_id = pl.id
                 WHERE uh.user_id = $user_id ORDER BY rh.recorded_at DESC LIMIT 200";
$chart1_res = mysqli_query($conn, $chart1_query);
$c1_labels = []; $c1_data = [];
while ($row = mysqli_fetch_assoc($chart1_res)) {
    $c1_labels[] = $row['date_val'] . ' (' . $row['platform_name'] . ')';
    $c1_data[] = $row['rating'];
}
$c1_labels = array_reverse($c1_labels);
$c1_data = array_reverse($c1_data);

// Chart 2: Solved Difficulty Trend (Based on solved date)
$chart2_query = "SELECT p.equivalent_rating, DATE_FORMAT(s.solved_at, '%d %b %Y') as date_val 
                 FROM solved_problems s
                 JOIN problems p ON s.problem_id = p.id
                 WHERE s.user_id = $user_id ORDER BY s.solved_at DESC LIMIT 200";
$chart2_res = mysqli_query($conn, $chart2_query);
$c2_labels = []; $c2_data = [];
while ($row = mysqli_fetch_assoc($chart2_res)) {
    $c2_labels[] = $row['date_val'];
    $c2_data[] = $row['equivalent_rating'];
}
$c2_labels = array_reverse($c2_labels);
$c2_data = array_reverse($c2_data);
?>

<?php
$page_title = 'Dashboard - CProg Tracker';
$needs_chartjs = true;
require_once 'includes/head.php';
require_once 'includes/nav_dashboard.php';
?>

    <main class="dashboard-container">
        <?php echo $message; ?>
        
        <section class="profile-banner">
            <div class="profile-content d-flex w-full align-center gap-lg">
                <div class="profile-avatar" style="display: flex; align-items: center; justify-content: center;">
                    <?php echo !empty($profile_pic) ? '<img src="' . htmlspecialchars($profile_pic) . 
                    '" alt="Profile Avatar">' : strtoupper(substr($user_display_name, 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user_display_name); ?></h2>
                    <p><?php echo htmlspecialchars($email); ?></p>
                    <div class="user-handles-row handles-row d-flex flex-wrap gap-sm">
                        <?php if (mysqli_num_rows($user_handles_list) > 0): ?>
                            <?php while ($h = mysqli_fetch_assoc($user_handles_list)): 
                                $profile_url = '';
                                if ($h['platform_id'] == 1) {
                                    $profile_url = "https://codeforces.com/profile/" . htmlspecialchars($h['username']);
                                } else if ($h['platform_id'] == 2) {
                                    $profile_url = "https://leetcode.com/u/" . htmlspecialchars($h['username']) . "/";
                                } else if (stripos($h['platform_name'], 'AtCoder') !== false) {
                                    $profile_url = "https://atcoder.jp/users/" . htmlspecialchars($h['username']);
                                } else if (stripos($h['platform_name'], 'CodeChef') !== false) {
                                    $profile_url = "https://www.codechef.com/users/" . htmlspecialchars($h['username']);
                                } else if (stripos($h['platform_name'], 'SPOJ') !== false) {
                                    $profile_url = "https://www.spoj.com/users/" . htmlspecialchars($h['username']) . "/";
                                } else if (stripos($h['platform_name'], 'HackerRank') !== false) {
                                    $profile_url = "https://www.hackerrank.com/profile/" . htmlspecialchars($h['username']);
                                } else if (stripos($h['platform_name'], 'Topcoder') !== false) {
                                    $profile_url = "https://www.topcoder.com/members/" . htmlspecialchars($h['username']);
                                } else if (stripos($h['platform_name'], 'CSES') !== false) {
                                    $profile_url = "https://cses.fi/user/" . htmlspecialchars($h['username']);
                                }
                            ?>
                                <?php if (!empty($profile_url)): ?>
                                    <a href="<?php echo $profile_url; ?>" target="_blank" class="handle-link-badge d-flex align-center gap-xs">
                                        <span><?php echo htmlspecialchars($h['platform_name']); ?>:</span> 
                                        <?php echo htmlspecialchars($h['username']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="handle-link-badge static d-flex align-center gap-xs">
                                        <span><?php echo htmlspecialchars($h['platform_name']); ?>:</span> 
                                        <?php echo htmlspecialchars($h['username']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-stats d-flex gap-sm">
                    <div class="stat-box">
                        <span class="stat-title">AVG SOLVED RATING</span>
                        <span class="stat-value text-accent-cyan"><?php echo $avg_solved_rating; ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">TOTAL SOLVED</span>
                        <span class="stat-value text-accent-yellow"><?php echo $total_solved_problems; ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-grid mb-lg">
            <div class="card card-hover">
                <div class="d-flex justify-between align-center mb-sm">
                    <div>
                        <h3>Contest Rating Chart (Relative)</h3>
                        <span class="text-xs text-muted block mt-xs">Sync fetches up to 2,000 recent records. Click graph to expand.</span>
                    </div>
                    <div class="chart-nav">
                        <button class="btn btn-sm btn-secondary" id="c1-prev" title="Older">&larr;</button>
                        <button class="btn btn-sm btn-secondary" id="c1-next" title="Newer" disabled>&rarr;</button>
                    </div>
                </div>
                <div class="chart-container" style="height: 300px; position: relative; cursor: pointer;" onclick="openChartModal('contestChart', 'Contest Rating Chart', '#00f0ff', 'rgba(0, 240, 255, 0.1)', '#ff007f')">
                    <canvas id="contestChart" data-labels="<?php echo htmlspecialchars(json_encode($c1_labels), ENT_QUOTES, 'UTF-8'); ?>" data-values="<?php echo htmlspecialchars(json_encode($c1_data), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                </div>
            </div>
            <div class="card card-hover">
                <div class="d-flex justify-between align-center mb-sm">
                    <div>
                        <h3>Solved Difficulty Trend</h3>
                        <span class="text-xs text-muted block mt-xs">Sync fetches up to 2,000 recent records. Click graph to expand.</span>
                    </div>
                    <div class="chart-nav">
                        <button class="btn btn-sm btn-secondary" id="c2-prev" title="Older">&larr;</button>
                        <button class="btn btn-sm btn-secondary" id="c2-next" title="Newer" disabled>&rarr;</button>
                    </div>
                </div>
                <div class="chart-container" style="height: 300px; position: relative; cursor: pointer;" onclick="openChartModal('solvedChart', 'Solved Difficulty Trend', '#facc15', 'rgba(250, 204, 21, 0.1)', '#ffffff')">
                    <canvas id="solvedChart" data-labels="<?php echo htmlspecialchars(json_encode($c2_labels), ENT_QUOTES, 'UTF-8'); ?>" data-values="<?php echo htmlspecialchars(json_encode($c2_data), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            
            <div class="card card-hover" id="solve-activity">
                <h3>Main Solve Activity</h3>
                <p class="text-muted text-sm mb-md block">Solve history with search and pagination features.</p>
                
                <div class="search-container d-flex justify-between align-center gap-sm flex-wrap">
                    <form action="dashboard.php#solve-activity" method="GET" class="search-bar-form">
                        <input type="text" name="search_solved" class="form-control flex-grow" placeholder="Search activity..." value="<?php echo htmlspecialchars($search_solved); ?>">
                        <button type="submit" class="btn btn-accent btn-md">Search</button>
                        <?php if(!empty($search_solved)): ?>
                            <a href="dashboard.php#solve-activity" class="btn btn-secondary btn-md">Reset</a>
                        <?php endif; ?>
                    </form>
                    <button type="button" class="btn btn-pink btn-md" id="openModalBtn">
                        <span>+ Add Custom Problem</span>
                    </button>
                </div>

                <?php if(mysqli_num_rows($solved_result) > 0): ?>
                    <ul class="activity-list">
                        <?php while($solved = mysqli_fetch_assoc($solved_result)): ?>
                            <li class="activity-item d-flex justify-between align-center">
                                <div>
                                    <a href="<?php echo htmlspecialchars($solved['problem_url']); ?>" target="_blank" class="text-accent-cyan">
                                        <?php echo htmlspecialchars($solved['title']); ?>
                                    </a>
                                    <span class="platform-badge">
                                        <?php echo htmlspecialchars($solved['platform_name']); ?>
                                    </span>
                                    <?php if(!empty($solved['proof_image'])): ?>
                                        <a href="<?php echo htmlspecialchars($solved['proof_image']); ?>" target="_blank" class="proof-link">View Proof</a>
                                    <?php endif; ?>
                                </div>
                                <span class="activity-rating">
                                    Rating: <span class="text-accent-yellow"><?php echo $solved['equivalent_rating']; ?></span>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-dim text-md text-center py-md block">No activity recorded for this search.</p>
                <?php endif; ?>
 
                <?php if ($total_solved_pages > 1): ?>
                <div class="pagination-container d-flex justify-center align-center gap-sm flex-wrap">
                    <?php if ($page_solved > 1): ?>
                        <a href="?page_solved=1&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link" title="First Page">&laquo; First</a>
                        <a id="pagination-prev" href="?page_solved=<?php echo $page_solved - 1; ?>&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link" title="Previous Page">&lsaquo; Prev</a>
                    <?php endif; ?>

                    <?php
                    $range = 2;
                    $start_page = max(1, $page_solved - $range);
                    $end_page = min($total_solved_pages, $page_solved + $range);

                    if ($start_page > 1): ?>
                        <a href="?page_solved=1&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="pagination-ellipsis text-muted">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page_solved=<?php echo $i; ?>&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" 
                           class="pagination-link <?php echo ($i == $page_solved) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_solved_pages): ?>
                        <?php if ($end_page < $total_solved_pages - 1): ?>
                            <span class="pagination-ellipsis text-muted">...</span>
                        <?php endif; ?>
                        <a href="?page_solved=<?php echo $total_solved_pages; ?>&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link"><?php echo $total_solved_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($page_solved < $total_solved_pages): ?>
                        <a id="pagination-next" href="?page_solved=<?php echo $page_solved + 1; ?>&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link" title="Next Page">Next &rsaquo;</a>
                        <a href="?page_solved=<?php echo $total_solved_pages; ?>&search_solved=<?php echo urlencode($search_solved); ?>#solve-activity" class="pagination-link" title="Last Page">Last &raquo;</a>
                    <?php endif; ?>

                    <form action="dashboard.php" method="GET" class="pagination-jump-form d-flex align-center gap-xs">
                        <input type="hidden" name="search_solved" value="<?php echo htmlspecialchars($search_solved); ?>">
                        <label for="page_solved_input" class="text-sm text-muted">Go to:</label>
                        <input type="number" id="page_solved_input" name="page_solved" class="form-control pagination-input" min="1" max="<?php echo $total_solved_pages; ?>" value="<?php echo $page_solved; ?>" required>
                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 10px;">Go</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
 
            <div class="card card-hover">
                <h3>Adaptive Problem Recommendations</h3>
                <?php if($avg_solved_rating > 0): ?>
                    <p class="text-muted text-sm mb-md block">Based on your average capability (<strong><?php echo $avg_solved_rating; ?></strong>), here are your optimal targets:</p>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Problem</th>
                                    <th>Platform</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($reco = mysqli_fetch_assoc($reco_result)): ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($reco['problem_url']); ?>" target="_blank" class="text-accent-yellow"><?php echo htmlspecialchars($reco['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($reco['platform_name']); ?></td>
                                    <td><?php echo $reco['equivalent_rating']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="placeholder-list">
                        <p>The system requires more solve history data to calculate your average capability accurately.</p>
                    </div>
                <?php endif; ?>
            </div>

        </section>

    </main>

    <!-- Modal for Custom Problem Registration -->
    <div id="customProblemModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeModalBtn">&times;</span>
            <h3 class="modal-header-3">
                Custom Problem Registration
            </h3>
            <p class="text-muted text-sm mb-lg">Add external problems manually (e.g., AtCoder, HackerRank, Virtual Judge, or external contest links).</p>
            
            <form id="modalAddProblemForm" action="manage_problems.php" method="POST" enctype="multipart/form-data" class="form-grid-2">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="source" value="dashboard">
                
                <div class="form-group form-span-2">
                    <label>Problem Link (URL) <span class="required-field">*</span></label>
                    <input type="url" name="problem_url" id="modalUrlInput" class="form-control" placeholder="https://atcoder.jp/contests/... or other link" required>
                    <small class="field-helper-text">Platform is automatically detected from URL.</small>
                </div>
                
                <div class="form-group form-span-2">
                    <label>Problem Title <span class="required-field">*</span></label>
                    <input type="text" name="title" id="modalTitleInput" class="form-control" placeholder="Problem Name / Title" required>
                    <small id="modalTitleFeedback" class="field-helper-text"></small>
                </div>
                
                <div class="form-group">
                    <label>Source Platform <span class="required-field">*</span></label>
                    <select name="platform_id" id="modalPlatformSelect" class="form-control" required>
                        <option value="">-- Select Platform --</option>
                        <?php while($plat = mysqli_fetch_assoc($modal_platforms_result)): ?>
                            <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Equivalent Rating <span class="required-field">*</span></label>
                    <input type="number" name="equivalent_rating" id="modalRatingInput" class="form-control" placeholder="e.g., 1200" required>
                    <small id="modalRatingGuide" class="field-helper-text">Select a platform to view rating guidelines.</small>
                </div>
 
                <div class="form-group">
                    <label>Date Solved <span class="required-field">*</span></label>
                    <input type="date" name="solved_at" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Upload Proof Image <span class="optional-field">(Optional)</span></label>
                    <input type="file" name="proof_image" class="form-control" accept="image/*">
                </div>
                
                <button type="submit" id="modalSubmitBtn" class="btn btn-primary btn-md form-span-2">Save Problem</button>
            </form>
        </div>
    </div>


    <!-- Chart Modal -->
    <div id="chartModal" class="modal">
        <div class="modal-content" style="max-width: 90vw; width: 1000px;">
            <span class="close-modal" id="closeChartModal">&times;</span>
            <div class="d-flex justify-between align-center mb-md">
                <h2 id="chartModalTitle">Chart View</h2>
                <div class="chart-nav">
                    <button class="btn btn-sm btn-secondary" id="modal-prev" title="Older">&larr;</button>
                    <button class="btn btn-sm btn-secondary" id="modal-next" title="Newer" disabled>&rarr;</button>
                </div>
            </div>
            <div class="chart-container" style="height: 60vh; position: relative;">
                <canvas id="modalChartCanvas"></canvas>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>