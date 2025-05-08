<?php
// Include database connection
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required";
    header("Location: index.php");
    exit();
}

$staff_id = $_GET['id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Get staff details first
    $staff = getStaffById($staff_id);
    if (!$staff) {
        throw new Exception("Staff member not found");
    }
    
    // Check if staff has any pending salary payments
    $checkPendingQuery = "SELECT COUNT(*) as count FROM staff_salary 
                         WHERE staff_id = ? AND payment_status = 'pending'";
    $stmt = $conn->prepare($checkPendingQuery);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingCount = $result->fetch_assoc()['count'];
    
    if ($pendingCount > 0) {
        throw new Exception("Cannot delete staff with pending salary payments");
    }
    
    // Check if academic staff has active batch assignments
    if ($staff['staff_type'] == 'academic') {
        $checkBatchQuery = "SELECT COUNT(*) as count FROM staff_batch 
                           WHERE staff_id = ? AND status = 'active'";
        $stmt = $conn->prepare($checkBatchQuery);
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $batchCount = $result->fetch_assoc()['count'];
        
        if ($batchCount > 0) {
            throw new Exception("Cannot delete staff with active batch assignments");
        }
    }
    
    // Delete staff record (cascading will handle related records)
    $deleteQuery = "DELETE FROM staff WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    // Update expenses related to this staff
    $updateExpensesQuery = "UPDATE expenses SET staff_id = NULL 
                           WHERE staff_id = ?";
    $stmt = $conn->prepare($updateExpensesQuery);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    // Update income records if any
    $updateIncomeQuery = "UPDATE income SET staff_id = NULL 
                         WHERE staff_id = ?";
    $stmt = $conn->prepare($updateIncomeQuery);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Staff member deleted successfully";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error deleting staff member: " . $e->getMessage();
}

// Redirect back to staff list
header("Location: index.php");
exit();
?>