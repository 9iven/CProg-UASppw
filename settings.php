<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- PROCESS POST ACTIONS (Upload Avatar & Delete Handle) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ACTION 1: Upload Profile Picture (Avatar)
    if (isset($_POST['action']) && $_POST['action'] == 'upload_avatar') {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            // Get file extension (e.g. jpg, png)
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            // Create a unique filename using user ID and current timestamp
            $filename = "avatar_" . $user_id . "_" . time() . "." . $ext;
            $destination = "uploads/avatars/" . $filename;
            
            // Move file from temporary folder to uploads folder
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                // Save picture file path to database
                mysqli_query($conn, "UPDATE users SET profile_picture = '$destination' WHERE id = $user_id");
                $message = "<div class='alert alert-success'>Profile picture successfully updated!</div>";
            } else {
                $message = "<div class='alert alert-error'>Failed to move file to server. Please try again.</div>";
            }
        }
    }
    
    // ACTION 2: Reset / Delete Platform Handle
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_handle') {
        $handle_id = (int)$_POST['handle_id'];
        $platform_id = (int)$_POST['platform_id'];
        
        // Step A: Delete contest rating history (rating_history) linked to this handle
        mysqli_query($conn, "DELETE FROM rating_history WHERE user_handle_id = $handle_id");
        // Step B: Delete username/handle data (user_handles)
        mysqli_query($conn, "DELETE FROM user_handles WHERE id = $handle_id");
        // Step C: Delete solved problems history from this platform (excluding manually added ones)
        $purge_query = "DELETE FROM solved_problems WHERE user_id = $user_id AND problem_id IN (SELECT id FROM problems WHERE platform_id = $platform_id AND is_custom = FALSE)";
        mysqli_query($conn, $purge_query);
        
        $message = "<div class='alert alert-success'>Handle and its historical data have been successfully deleted from the system.</div>";
    }
}

// Retrieve platforms list
$platforms_query = "SELECT * FROM platforms";
$platforms_result = mysqli_query($conn, $platforms_query);

// Retrieve registered handles
$handles_query = "SELECT uh.id as handle_id, uh.platform_id, uh.username, pl.name as platform_name 
                  FROM user_handles uh 
                  JOIN platforms pl ON uh.platform_id = pl.id 
                  WHERE uh.user_id = $user_id";
$handles_result = mysqli_query($conn, $handles_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - CProg Viewer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="dashboard-header d-flex justify-between align-center">
        <a href="dashboard.php" class="header-logo">
            <img src="assets/img/logo.png" alt="CProg Logo" class="custom-logo-img">
            <span>CProg <span class="text-accent-yellow">Viewer</span></span>
        </a>
        <div class="user-profile d-flex align-center gap-md">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>
    </header>

    <main class="dashboard-container">
        <h1 class="page-title yellow-accent">Account <span class="text-accent-pink">Settings</span></h1>
        <?php echo $message; ?>
        <?php 
        if(isset($_SESSION['success_msg'])) {
            echo "<div class='alert alert-success'>" . $_SESSION['success_msg'] . "</div>";
            unset($_SESSION['success_msg']);
        }
        if(isset($_SESSION['error_msg'])) {
            echo "<div class='alert alert-error'>" . $_SESSION['error_msg'] . "</div>";
            unset($_SESSION['error_msg']);
        }
        ?>
 
        <section class="dashboard-grid">
            <div class="card card-hover">
                <h3>Profile Customization</h3>
                <p class="text-muted text-sm mb-md block">Upload your <em>profile picture</em>. This image will represent your identity on the <em>dashboard</em> page.</p>
                
                <form action="settings.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="form-group">
                        <label>Choose Image File</label>
                        <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-accent btn-md">Save Profile</button>
                </form>
            </div>
 
            <div class="card card-hover">
                <h3>Platform Handle Management</h3>
                <p class="text-muted text-sm mb-md block">Add your <em>username</em> or <em>handle</em> from competitive programming platforms.</p>
                
                <form action="includes/process_handle.php" method="POST">
                    <div class="form-group">
                        <label>Select Platform</label>
                        <select name="platform_id" class="form-control" required>
                            <?php while($plat = mysqli_fetch_assoc($platforms_result)): ?>
                                <option value="<?php echo $plat['id']; ?>"><?php echo htmlspecialchars($plat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Username / Handle</label>
                        <input type="text" name="cf_username" class="form-control" placeholder="Example: givengerald" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-md">Save & Sync</button>
                </form>
            </div>
 
            <div class="card card-hover form-span-2">
                <h3>Registered Handles & Reset Data</h3>
                <p class="text-muted text-sm mb-md block">List of identities connected to the system. You can reset data if synchronization errors occur.</p>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Handle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($handles_result) > 0): ?>
                                <?php while($handle = mysqli_fetch_assoc($handles_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($handle['platform_name']); ?></td>
                                    <td class="text-accent-yellow"><?php echo htmlspecialchars($handle['username']); ?></td>
                                    <td>
                                        <form action="settings.php" method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="delete_handle">
                                            <input type="hidden" name="handle_id" value="<?php echo $handle['handle_id']; ?>">
                                            <input type="hidden" name="platform_id" value="<?php echo $handle['platform_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Resetting this handle will delete all solved history associated with this account. Continue?');">Delete & Reset Data</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-table-cell">No registered handles found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
    <footer class="app-footer">
        <div class="footer-links d-flex justify-center gap-md flex-wrap">
            <a href="#" class="footer-modal-trigger" data-type="pivot">Rating Pivot</a>
            <span class="footer-divider">|</span>
            <a href="#" class="footer-modal-trigger" data-type="guide">How to Use</a>
            <span class="footer-divider">|</span>
            <a href="https://github.com/9iven/CProg-UASppw" target="_blank">GitHub Repository</a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> CProg Tracker. All rights reserved.
        </div>
    </footer>

    <!-- Universal Info Modal -->
    <div id="infoModal" class="modal">
        <div class="modal-content modal-info">
            <span class="close-modal" id="closeInfoModalBtn">&times;</span>
            <h3 id="infoModalTitle" class="modal-header-3">Information</h3>
            <div id="infoModalBody"></div>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>