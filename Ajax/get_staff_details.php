<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if staff ID is provided
if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
    exit();
}

$staff_id = $_GET['staff_id'];

// Get staff details
$query = "SELECT * FROM staff WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Staff member not found']);
    exit();
}

$staffData = $result->fetch_assoc();

// Get batches if academic staff
$batches = [];
if ($staffData['staff_type'] == 'academic') {
    $batchQuery = "SELECT sb.*, b.batch_name, b.start_date, b.end_date, c.course_name 
                   FROM staff_batch sb
                   JOIN batches b ON sb.batch_id = b.id
                   JOIN courses c ON b.course_id = c.id
                   WHERE sb.staff_id = ?
                   ORDER BY sb.assigned_date DESC";
    $batchStmt = $conn->prepare($batchQuery);
    $batchStmt->bind_param("i", $staff_id);
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    
    while ($row = $batchResult->fetch_assoc()) {
        $batches[] = $row;
    }
}

// Return staff details as JSON
echo json_encode([
    'success' => true,
    'staff' => $staffData,
    'batches' => $batches
]);
?>