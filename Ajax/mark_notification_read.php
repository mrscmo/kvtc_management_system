<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if notification ID is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit();
}

$notification_id = $_POST['notification_id'];

// Mark notification as read
$query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error marking notification as read']);
}
?>