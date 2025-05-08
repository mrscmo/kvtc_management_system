<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Notification ID is required";
    header("Location: index.php");
    exit();
}

$notification_id = $_GET['id'];

// Delete notification
$query = "DELETE FROM notifications WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Notification deleted successfully.";
} else {
    $_SESSION['error_message'] = "Error deleting notification: " . $conn->error;
}

// Redirect back to the notifications list
header("Location: index.php");
exit();
?>