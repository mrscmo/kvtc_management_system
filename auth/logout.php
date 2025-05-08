<?php
// Start session
session_start();

// Include auth functions
require_once __DIR__ . '/../includes/auth.php';

// Log out the user
logoutUser();
?>