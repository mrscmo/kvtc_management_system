<?php
// Start session and include database BEFORE any output
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Notification ID is required";
    header("Location: index.php");
    exit();
}

$notification_id = $_GET['id'];

// Mark notification as read
$query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Notification marked as read.";
} else {
    $_SESSION['error_message'] = "Error marking notification as read: " . $conn->error;
}

// Redirect back to notifications page
header("Location: index.php");
exit();
?>