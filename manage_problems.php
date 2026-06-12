<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- PROCESS POST FORM SUBMISSION (ADD / DELETE MANUAL PROBLEM) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ACTION 1: Add New Custom Problem
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $platform_id = (int)$_POST['platform_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $problem_url = mysqli_real_escape_string($conn, $_POST['problem_url']);
        $equivalent_rating = (int)$_POST['equivalent_rating'];
        
        // Read and format the solved date (solved_at)
        $solved_at = mysqli_real_escape_string($conn, $_POST['solved_at']);
        if (empty($solved_at)) {
            $solved_at = date('Y-m-d H:i:s'); // Fallback to current server time
        } else {
            // If input format is YYYY-MM-DD, append current time for full MySQL TIMESTAMP
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $solved_at)) {
                $solved_at .= ' ' . date('H:i:s');
            }
        }
        
        // Proof Image Upload Logic
        $proof_path = 'NULL'; // Default if no file is uploaded
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
            $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
            // Give unique name using timestamp and user ID
            $filename = "proof_" . time() . "_" . $user_id . "." . $ext;
            $destination = "uploads/proofs/" . $filename;
            
            // Move temporary file to destination folder
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
                $proof_path = "'" . mysqli_real_escape_string($conn, $destination) . "'";
            }
        }
        
        // Check duplicate problem URL to avoid double insert in the problems table
        $check_dup = mysqli_query($conn, "SELECT id FROM problems WHERE problem_url = '$problem_url'");
        if (mysqli_num_rows($check_dup) > 0) {
            // Problem already exists in DB, just link it to user's history
            $dup_row = mysqli_fetch_assoc($check_dup);
            $new_problem_id = $dup_row['id'];
            $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                              VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                              ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
            
            if (mysqli_query($conn, $insert_solved)) {
                $message = "<div class='alert alert-success'>Problem successfully added to your solved history!</div>";
            } else {
                $message = "<div class='alert alert-error'>Failed to link problem to your history.</div>";
            }
        } else {
            // Problem does not exist yet, register new problem to the problems table first
            $insert_query = "INSERT INTO problems (platform_id, title, problem_url, equivalent_rating, is_custom, created_by) 
                             VALUES ($platform_id, '$title', '$problem_url', $equivalent_rating, TRUE, $user_id)";
            
            if (mysqli_query($conn, $insert_query)) {
                $new_problem_id = mysqli_insert_id($conn);
                // Link to user's solved history
                $insert_solved = "INSERT INTO solved_problems (user_id, problem_id, solved_at, proof_image) 
                                  VALUES ($user_id, $new_problem_id, '$solved_at', $proof_path) 
                                  ON DUPLICATE KEY UPDATE solved_at = VALUES(solved_at), proof_image = IF($proof_path IS NULL, proof_image, VALUES(proof_image))";
                mysqli_query($conn, $insert_solved);
                $message = "<div class='alert alert-success'>Custom problem and proof successfully saved!</div>";
            } else {
                $message = "<div class='alert alert-error'>Failed to save problem to database: " . mysqli_error($conn) . "</div>";
            }
        }
    } 
    
    // ACTION 2: Delete Custom Problem
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $problem_id = (int)$_POST['problem_id'];
        
        // Delete from problems table (cascade/manual relation, ensure deleting own record only)
        $delete_query = "DELETE FROM problems WHERE id = $problem_id AND created_by = $user_id AND is_custom = TRUE";
        mysqli_query($conn, $delete_query);
        $message = "<div class='alert alert-success'>Problem successfully deleted from your repository.</div>";
    }

    // ACTION 3: Update Custom Problem
    elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
        $problem_id = (int)$_POST['problem_id'];
        $platform_id = (int)$_POST['platform_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $problem_url = mysqli_real_escape_string($conn, $_POST['problem_url']);
        $equivalent_rating = (int)$_POST['equivalent_rating'];
        
        $solved_at = mysqli_real_escape_string($conn, $_POST['solved_at']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $solved_at)) {
            $solved_at .= ' ' . date('H:i:s');
        }

        // Proof Image Upload Logic
        $proof_update_sql = "";
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
            $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
            $filename = "proof_" . time() . "_" . $user_id . "." . $ext;
            $destination = "uploads/proofs/" . $filename;
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
                $proof_path = "'" . mysqli_real_escape_string($conn, $destination) . "'";
                $proof_update_sql = ", proof_image = $proof_path";
            }
        }
        
        // Verify ownership and update problems table
        $update_prob = "UPDATE problems SET platform_id = $platform_id, title = '$title', problem_url = '$problem_url', equivalent_rating = $equivalent_rating WHERE id = $problem_id AND created_by = $user_id AND is_custom = TRUE";
        if (mysqli_query($conn, $update_prob) && mysqli_affected_rows($conn) > 0) {
            // Update solved_problems
            $update_solved = "UPDATE solved_problems SET solved_at = '$solved_at' $proof_update_sql WHERE problem_id = $problem_id AND user_id = $user_id";
            mysqli_query($conn, $update_solved);
            $message = "<div class='alert alert-success'>Problem successfully updated!</div>";
        } else {
            $message = "<div class='alert alert-error'>Failed to update problem or no changes made.</div>";
        }
    }
}

// Retrieve list of platforms for selection
$platforms_query = "SELECT id, name FROM platforms";
$platforms_result = mysqli_query($conn, $platforms_query);

// --- SEARCH & PAGINATION LOGIC ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = "WHERE p.created_by = $user_id AND p.is_custom = TRUE";

// Add keyword search filter if input is provided
if (!empty($search)) {
    $where_clause .= " AND p.title LIKE '%$search%'";
}

// Configuration of row limit per page
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Count total matching data rows
$count_query = "SELECT COUNT(*) as total FROM problems p $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);
$offset = ($page - 1) * $limit; // Calculate the query offset

// Retrieve specific problems based on current offset
$problems_query = "SELECT p.id, p.title, p.problem_url, p.equivalent_rating, p.platform_id, pl.name AS platform_name, s.solved_at 
                   FROM problems p 
                   JOIN platforms pl ON p.platform_id = pl.id 
                   LEFT JOIN solved_problems s ON s.problem_id = p.id AND s.user_id = $user_id
                   $where_clause 
                   ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$problems_result = mysqli_query($conn, $problems_query);
?>

<?php
$page_title = 'Manage Problems - CProg Tracker';
require_once 'includes/head.php';
require_once 'includes/nav_dashboard.php';
?>

    <main class="dashboard-container">
        <h1 class="page-title blue-accent">Manage <span class="text-accent-yellow">Problems</span></h1>
        <?php echo $message; ?>
        
        <section class="dashboard-grid grid-1col">
            
            <div class="card card-hover">
                <h3 id="formTitle">Manual Problem Registration</h3>
                <p class="text-muted text-sm mb-md block">Add or edit a <em>custom</em> problem and include a screenshot as validation proof.</p>
                
                <form id="addProblemForm" action="manage_problems.php" method="POST" enctype="multipart/form-data" class="form-grid-2">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="problem_id" id="formProblemId" value="">
                    <div class="form-group">
                        <label>Platform</label>
                        <select name="platform_id" id="platformSelect" class="form-control" required>
                            <option value="">-- Select Platform --</option>
                            <?php mysqli_data_seek($platforms_result, 0); ?>
                            <?php while($plat = mysqli_fetch_assoc($platforms_result)): ?>
                                <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Equivalent Rating</label>
                        <input type="number" name="equivalent_rating" id="ratingInput" class="form-control" placeholder="Example: 1400" required>
                        <small id="ratingGuide" class="field-helper-text">Select a platform to view rating guidelines.</small>
                    </div>
                    <div class="form-group form-span-2">
                        <label>Problem Title</label>
                        <input type="text" name="title" id="titleInput" class="form-control" placeholder="Problem Title" required>
                        <small id="titleFeedback" class="field-helper-text"></small>
                    </div>
                    <div class="form-group">
                        <label>Problem URL</label>
                        <input type="url" name="problem_url" id="urlInput" class="form-control" placeholder="https://..." required>
                        <small class="field-helper-text">Platform is automatically detected from URL.</small>
                    </div>
                    <div class="form-group">
                        <label>Date Solved</label>
                        <input type="date" name="solved_at" id="solvedAtInput" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group form-span-2">
                        <label>Upload Proof Image (Optional)</label>
                        <input type="file" name="proof_image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-md form-span-2">Save Problem</button>
                </form>
            </div>

            <div class="card card-hover" id="problem-collection">
                <h3>Your Problem Collection</h3>
                
                <div class="search-container d-flex justify-between align-center gap-sm flex-wrap">
                    <form action="manage_problems.php#problem-collection" method="GET" class="search-bar-form">
                        <input type="text" name="search" class="form-control flex-grow" placeholder="Search by problem title..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-accent btn-md">Search</button>
                        <?php if(!empty($search)): ?>
                            <a href="manage_problems.php#problem-collection" class="btn btn-secondary btn-md">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Problem Title</th>
                                <th>Platform</th>
                                <th>Rating</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($problems_result) > 0): ?>
                                <?php while($prob = mysqli_fetch_assoc($problems_result)): ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($prob['problem_url']); ?>" target="_blank" class="text-accent-yellow"><?php echo htmlspecialchars($prob['title']); ?></a></td>
                                    <td><?php echo htmlspecialchars($prob['platform_name']); ?></td>
                                    <td><?php echo $prob['equivalent_rating']; ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-xs">
                                            <button type="button" class="btn btn-secondary btn-xs" onclick="editProblem(<?php echo $prob['id']; ?>, '<?php echo htmlspecialchars(addslashes($prob['title'])); ?>', '<?php echo htmlspecialchars(addslashes($prob['problem_url'])); ?>', <?php echo $prob['equivalent_rating']; ?>, <?php echo $prob['platform_id']; ?>, '<?php echo substr($prob['solved_at'], 0, 10); ?>')">Edit</button>
                                            <form action="manage_problems.php" method="POST" class="inline-form mb-0">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="problem_id" value="<?php echo $prob['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Deleting this problem will remove it from your solved history. Continue?');">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-table-cell">No problems found.</td>
                                </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination-container d-flex justify-center gap-sm">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>#problem-collection" 
                           class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

            </div>
        </section>
    </main>


<?php 
ob_start(); 
?>
    <script>
        function editProblem(id, title, url, rating, platformId, solvedAt) {
            // Update form title and button text
            document.getElementById('formTitle').textContent = 'Edit Custom Problem';
            document.getElementById('submitBtn').textContent = 'Update Problem';
            
            // Set hidden inputs
            document.getElementById('formAction').value = 'update';
            document.getElementById('formProblemId').value = id;
            
            // Fill visible inputs
            document.getElementById('titleInput').value = title;
            document.getElementById('urlInput').value = url;
            document.getElementById('ratingInput').value = rating;
            document.getElementById('platformSelect').value = platformId;
            
            if (solvedAt) {
                document.getElementById('solvedAtInput').value = solvedAt;
            }
            
            // Smooth scroll to the form
            document.getElementById('addProblemForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Highlight the form briefly to show it's ready for editing
            const formCard = document.getElementById('addProblemForm').closest('.card');
            formCard.style.transition = 'box-shadow 0.3s ease';
            formCard.style.boxShadow = 'var(--glow-cyan)';
            setTimeout(() => { formCard.style.boxShadow = 'var(--shadow-lg)'; }, 1000);
        }
    </script>
<?php 
$extra_scripts = ob_get_clean();
require_once 'includes/footer.php'; 
?>