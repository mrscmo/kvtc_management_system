<?php
// Start session
session_start();

// Include database connection and authentication
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Require login for all pages except login page
requireLogin();

// Get current user information
$currentUser = getCurrentUser();

// Buffer the output so headers can be modified later
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vocational Training Center - Student Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/vocational_training_center/assets/css/style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/vocational_training_center/index.php">
                <i class="fas fa-graduation-cap me-2"></i>
                Vocational Training Center
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($currentUser['full_name']); ?>
                            <span class="badge bg-secondary ms-1"><?php echo ucfirst(htmlspecialchars($currentUser['role'])); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/vocational_training_center/users/profile.php">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a></li>
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="/vocational_training_center/users/index.php">
                                    <i class="fas fa-users-cog me-2"></i> User Management
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/vocational_training_center/auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php 
            // Include sidebar (which defines the renderSidebar function)
            include_once __DIR__ . '/sidebar.php'; 
            
            // Now render the sidebar - this ensures no HTML output happens in sidebar.php
            renderSidebar();
            ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                </div>