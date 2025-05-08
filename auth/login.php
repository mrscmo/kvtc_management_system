<?php
// Start session
session_start();

// Include database connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /vocational_training_center/index.php");
    exit();
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (loginUser($username, $password)) {
        // Redirect to the page they were trying to access or dashboard
        $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/vocational_training_center/index.php';
        unset($_SESSION['redirect_url']);
        header("Location: " . $redirect_url);
        exit();
    } else {
        $_SESSION['error_message'] = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vocational Training Center</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .login-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        
        .login-header h3 {
            margin-bottom: 0;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            font-weight: 500;
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <h3>Vocational Training Center</h3>
                <p class="mb-0">Student Management System</p>
            </div>
            
            <div class="login-body">
                <!-- Display success/error messages -->
                <?php 
                displaySuccessMessage();
                displayErrorMessage();
                ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <a href="forgot_password.php" class="text-decoration-none">Forgot your password?</a>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> Vocational Training Center. All rights reserved.</small>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>