<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Check if user has admin privileges
function isAdmin() {
    return hasRole('admin');
}

// Check if user has manager privileges
function isManager() {
    return hasRole('manager') || isAdmin();
}

// Redirect user to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: /vocational_training_center/auth/login.php");
        exit();
    }
}

// Redirect user if they don't have required role
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role) && !isAdmin()) {
        $_SESSION['error_message'] = "You don't have permission to access this page.";
        header("Location: /vocational_training_center/index.php");
        exit();
    }
}

// Require admin role
function requireAdmin() {
    requireRole('admin');
}

// Require manager role (admin can also access)
function requireManager() {
    requireLogin();
    
    if (!isManager() && !isAdmin()) {
        $_SESSION['error_message'] = "You don't have permission to access this page.";
        header("Location: /vocational_training_center/index.php");
        exit();
    }
}

// Get logged in user's information
function getCurrentUser() {
    if (isLoggedIn()) {
        global $conn;
        $user_id = $_SESSION['user_id'];
        
        $sql = "SELECT * FROM users WHERE id = ? AND status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

// Log in user
function loginUser($username, $password) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            return true;
        }
    }
    
    return false;
}

// Log out user
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: /vocational_training_center/auth/login.php");
    exit();
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}
?>