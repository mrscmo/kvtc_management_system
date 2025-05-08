<?php
// Include database connection
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Batch ID is required";
    header("Location: index.php");
    exit();
}

$batch_id = $_GET['id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Get batch details first
    $batch = getBatchById($batch_id);
    if (!$batch) {
        throw new Exception("Batch not found");
    }
    
    // Check if batch has any students
    $checkStudentsQuery = "SELECT COUNT(*) as count FROM students WHERE batch_id = ?";
    $stmt = $conn->prepare($checkStudentsQuery);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentCount = $result->fetch_assoc()['count'];
    
    if ($studentCount > 0) {
        throw new Exception("Cannot delete batch with $studentCount students enrolled");
    }
    
    // Check if batch has any active staff assignments
    $checkStaffQuery = "SELECT COUNT(*) as count FROM staff_batch 
                       WHERE batch_id = ? AND status = 'active'";
    $stmt = $conn->prepare($checkStaffQuery);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staffCount = $result->fetch_assoc()['count'];
    
    if ($staffCount > 0) {
        throw new Exception("Cannot delete batch with active staff assignments");
    }
    
    // Delete batch record (cascading will handle related records)
    $deleteQuery = "DELETE FROM batches WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Batch deleted successfully";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error deleting batch: " . $e->getMessage();
}

// Redirect back to batch list
header("Location: index.php");
exit();
?>