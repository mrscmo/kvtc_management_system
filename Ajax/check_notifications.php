<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Get unread notifications count
$count = getUnreadNotificationsCount();

// Return count as JSON
header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>