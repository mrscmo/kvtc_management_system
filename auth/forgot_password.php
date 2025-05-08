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

// Handle password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $_SESSION['error_message'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format";
    } else {
        // Check if email exists
        $query = "SELECT id, full_name FROM users WHERE email = ? AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = generateToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $insertQuery = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iss", $user['id'], $token, $expires);
            
            if ($insertStmt->execute()) {
                // In a real application, you would send an email here
                // For now, we'll just show a success message with the token
                $_SESSION['success_message'] = "Password reset instructions have been sent to your email address.<br>
                                                For demonstration purposes, your reset token is: <strong>$token</strong><br>
                                                This token expires in 1 hour.";
            } else {
                $_SESSION['error_message'] = "Error generating reset token. Please try again.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $_SESSION['success_message'] = "If the email address exists in our system, you will receive password reset instructions.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Vocational Training Center</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .forgot-container {
            max-width: 400px;
            margin: 100px auto;
        }
        
        .forgot-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .forgot-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        
        .forgot-header h3 {
            margin-bottom: 0;
        }
        
        .forgot-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-reset {
            width: 100%;
            padding: 0.75rem;
            font-weight: 500;
        }
        
        .forgot-footer {
            text-align: center;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="forgot-container">
        <div class="card forgot-card">
            <div class="forgot-header">
                <h3>Forgot Password</h3>
                <p class="mb-0">Reset your password</p>
            </div>
            
            <div class="forgot-body">
                <!-- Display success/error messages -->
                <?php 
                displaySuccessMessage();
                displayErrorMessage();
                ?>
                
                <p class="text-muted text-center mb-4">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-reset">
                        <i class="fas fa-paper-plane me-2"></i> Send Reset Instructions
                    </button>
                </form>
            </div>
            
            <div class="forgot-footer">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
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