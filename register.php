<?php
// Start session for state management
session_start();

// Load database configuration parameters
require 'config/db.php';

// Initialize notification message variables
$error_message = '';
$success_message = '';

// Check if form has been submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim and sanitize inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Server-side validation for email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } 
    // 2. Validate if password and confirm password match
    elseif ($password !== $confirm_password) {
        $error_message = "Password confirmation does not match the entered password.";
    } 
    // 3. Validate password length (basic check)
    elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check if email is already registered in database using Prepared Statements (Protects against SQL Injection)
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt_check = mysqli_prepare($conn, $check_query);
        
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $email);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $error_message = "This email address is already registered. Please use another email or log in.";
                mysqli_stmt_close($stmt_check);
            } else {
                mysqli_stmt_close($stmt_check);
                
                // Hash password before saving (Security Standard)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Execute INSERT query into users table using Prepared Statement
                $insert_query = "INSERT INTO users (email, password) VALUES (?, ?)";
                $stmt_insert = mysqli_prepare($conn, $insert_query);
                
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ss", $email, $hashed_password);
                    if (mysqli_stmt_execute($stmt_insert)) {
                        $success_message = "Registration successful! Please go to the login page.";
                    } else {
                        $error_message = "A system database error occurred. Please try again.";
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    $error_message = "A system error occurred. Please try again.";
                }
            }
        } else {
            $error_message = "A database connection error occurred. Please try again.";
        }
    }
}

$page_title = 'Register - CProg Tracker';
require_once 'includes/head.php';
?>

    <main class="auth-wrapper d-flex justify-center align-center">
        <section class="auth-box">
            <!-- Brand Logo -->
            <a href="index.php" class="auth-logo-container">
                <img src="assets/img/logo.png?v=<?php echo time(); ?>" alt="CProg Tracker Logo" class="custom-logo-img auth-logo-img">
                <span class="auth-brand-text">CProg <span class="text-accent-yellow">Tracker</span></span>
            </a>

            <h2>Create a New Account</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-md btn-full mt-sm">Register</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </section>
    </main>
<?php 
$no_footer = true;
require_once 'includes/footer.php'; 
?>