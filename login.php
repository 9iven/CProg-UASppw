<?php
// Initialize session to track user login status
session_start();

// Load database configuration parameters
require 'config/db.php';

// Initialize variable to hold error messages
$error_message = '';

// Check if form has been submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim input
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Server-side verification for valid email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Query to find user using Prepared Statements (Protects against SQL Injection)
        $query = "SELECT id, email, password FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) === 1) {
                $user_data = mysqli_fetch_assoc($result);
                
                // Verify password hash match
                if (password_verify($password, $user_data['password'])) {
                    // Prevent Session Fixation attack by regenerating session ID on successful login
                    session_regenerate_id(true);
                    
                    // Set session variables if authentication succeeds
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['email'] = $user_data['email'];
                    
                    mysqli_stmt_close($stmt);
                    // Redirect user to the main dashboard page
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error_message = "The password you entered is invalid.";
                }
            } else {
                $error_message = "The email address is not registered in the system.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "A system error occurred. Please try again.";
        }
    }
}

$page_title = 'Login - CProg Tracker';
require_once 'includes/head.php';
?>

    <main class="auth-wrapper d-flex justify-center align-center">
        <section class="auth-box">
            <!-- Brand Logo -->
            <a href="index.php" class="auth-logo-container">
                <img src="assets/img/logo.png?v=<?php echo time(); ?>" alt="CProg Tracker Logo" class="custom-logo-img auth-logo-img">
                <span class="auth-brand-text">CProg <span class="text-accent-yellow">Tracker</span></span>
            </a>
            
            <h2>Sign In to Your Account</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-md btn-full mt-sm">Sign In</button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="register.php">Register now</a>
            </div>
        </section>
    </main>
<?php 
$no_footer = true;
require_once 'includes/footer.php'; 
?>