<?php
// Set page title
$page_title = "Delete Student";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Student ID is required";
    header("Location: index.php");
    exit();
}

$student_id = $_GET['id'];

// Get student details before deletion
$query = "SELECT s.*, c.course_name, b.batch_name 
          FROM students s
          JOIN courses c ON s.course_id = c.id
          JOIN batches b ON s.batch_id = b.id
          WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Student not found";
    header("Location: index.php");
    exit();
}

$student = $result->fetch_assoc();

// Check if the student has any payments or financial records
$checkFinanceQuery = "SELECT COUNT(*) as count FROM fees WHERE student_id = ?";
$stmt = $conn->prepare($checkFinanceQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$financeResult = $stmt->get_result();
$financeCount = $financeResult->fetch_assoc()['count'];

// Check for income records
$checkIncomeQuery = "SELECT COUNT(*) as count FROM income WHERE student_id = ?";
$stmt = $conn->prepare($checkIncomeQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$incomeResult = $stmt->get_result();
$incomeCount = $incomeResult->fetch_assoc()['count'];

// Check for expense records
$checkExpenseQuery = "SELECT COUNT(*) as count FROM expenses WHERE student_id = ?";
$stmt = $conn->prepare($checkExpenseQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$expenseResult = $stmt->get_result();
$expenseCount = $expenseResult->fetch_assoc()['count'];

// Check for employment records
$checkEmploymentQuery = "SELECT COUNT(*) as count FROM employment WHERE student_id = ?";
$stmt = $conn->prepare($checkEmploymentQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$employmentResult = $stmt->get_result();
$employmentCount = $employmentResult->fetch_assoc()['count'];

// Check for certification records
$checkCertificationQuery = "SELECT COUNT(*) as count FROM certifications WHERE student_id = ?";
$stmt = $conn->prepare($checkCertificationQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$certificationResult = $stmt->get_result();
$certificationCount = $certificationResult->fetch_assoc()['count'];

// Calculate total associated records
$totalAssociatedRecords = $financeCount + $incomeCount + $expenseCount + $employmentCount + $certificationCount;

// Handle deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    $deleteOption = $_POST['delete_option'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        if ($deleteOption == 'full') {
            // Delete all associated records first
            
            // Delete fees
            $query = "DELETE FROM fees WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            
            // Delete income records
            $query = "DELETE FROM income WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            
            // Delete expense records
            $query = "DELETE FROM expenses WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            
            // Delete employment records
            $query = "DELETE FROM employment WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            
            // Delete certification records
            $query = "DELETE FROM certifications WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
        }
        
        // Finally delete the student
        $query = "DELETE FROM students WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Student deleted successfully";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting student: " . $e->getMessage();
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Delete Student Confirmation -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Delete Student Confirmation</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h5 class="alert-heading">Warning!</h5>
                    <p>You are about to delete the following student from the system. This action cannot be undone.</p>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($student['student_id']); ?></h6>
                        <p class="card-text">
                            <strong>Course:</strong> <?php echo htmlspecialchars($student['course_name']); ?><br>
                            <strong>Batch:</strong> <?php echo htmlspecialchars($student['batch_name']); ?><br>
                            <strong>Registration Date:</strong> <?php echo formatDate($student['registration_date']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($totalAssociatedRecords > 0): ?>
                <div class="alert alert-danger">
                    <h5 class="alert-heading">Associated Records Found!</h5>
                    <p>This student has the following associated records in the system:</p>
                    <ul>
                        <?php if ($financeCount > 0): ?>
                            <li><?php echo $financeCount; ?> payment record(s)</li>
                        <?php endif; ?>
                        
                        <?php if ($incomeCount > 0): ?>
                            <li><?php echo $incomeCount; ?> income record(s)</li>
                        <?php endif; ?>
                        
                        <?php if ($expenseCount > 0): ?>
                            <li><?php echo $expenseCount; ?> expense record(s)</li>
                        <?php endif; ?>
                        
                        <?php if ($employmentCount > 0): ?>
                            <li><?php echo $employmentCount; ?> employment record(s)</li>
                        <?php endif; ?>
                        
                        <?php if ($certificationCount > 0): ?>
                            <li><?php echo $certificationCount; ?> certification record(s)</li>
                        <?php endif; ?>
                    </ul>
                    <p class="mb-0">Please choose how you want to handle these records:</p>
                </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $student_id); ?>">
                    <?php if ($totalAssociatedRecords > 0): ?>
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delete_option" id="delete_student_only" value="student_only" checked>
                            <label class="form-check-label" for="delete_student_only">
                                <strong>Delete Student Only</strong> - The student record will be deleted, but all associated records will remain in the system. This may lead to orphaned records.
                            </label>
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="radio" name="delete_option" id="delete_all" value="full">
                            <label class="form-check-label" for="delete_all">
                                <strong>Delete Student and All Associated Records</strong> - The student record and all associated records (payments, income, expenses, employment, certifications) will be permanently deleted.
                            </label>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="delete_option" value="student_only">
                    <?php endif; ?>
                    
                    <p class="text-danger fw-bold mb-4">Are you absolutely sure you want to delete this student?</p>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Yes, Delete Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>