<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if batch_id is provided
if (!isset($_GET['batch_id']) || empty($_GET['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

$batch_id = $_GET['batch_id'];

// Get batch and course details
$query = "SELECT b.*, c.id as course_id, c.course_name, c.duration, c.course_fee 
          FROM batches b 
          JOIN courses c ON b.course_id = c.id 
          WHERE b.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    exit();
}

$batchData = $result->fetch_assoc();

// Return batch and course details as JSON
echo json_encode([
    'success' => true,
    'batch_id' => $batchData['id'],
    'batch_name' => $batchData['batch_name'],
    'course_id' => $batchData['course_id'],
    'course_name' => $batchData['course_name'],
    'duration' => $batchData['duration'],
    'course_fee' => $batchData['course_fee'],
    'installments' => $batchData['installments']
]);
?>