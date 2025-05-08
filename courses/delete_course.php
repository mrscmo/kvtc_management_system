<?php
// Set page title
$page_title = "Delete Course";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Course ID is required";
    header("Location: index.php");
    exit();
}

$course_id = $_GET['id'];

// Get course details before deletion
$query = "SELECT * FROM courses WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Course not found";
    header("Location: index.php");
    exit();
}

$course = $result->fetch_assoc();

// Check if the course has any associated batches
$checkBatchQuery = "SELECT COUNT(*) as count FROM batches WHERE course_id = ?";
$stmt = $conn->prepare($checkBatchQuery);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$batchResult = $stmt->get_result();
$batchCount = $batchResult->fetch_assoc()['count'];

// Check if there are any students enrolled in this course
$checkStudentQuery = "SELECT COUNT(*) as count FROM students WHERE course_id = ?";
$stmt = $conn->prepare($checkStudentQuery);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$studentResult = $stmt->get_result();
$studentCount = $studentResult->fetch_assoc()['count'];

// Calculate total dependencies
$totalDependencies = $batchCount + $studentCount;

// Handle deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    // Check if there are dependencies
    if ($totalDependencies > 0) {
        $_SESSION['error_message'] = "Cannot delete course. It has $batchCount associated batch(es) and $studentCount enrolled student(s). Please remove these dependencies first.";
        header("Location: index.php");
        exit();
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete the course
        $query = "DELETE FROM courses WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Course deleted successfully";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting course: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Delete Course Confirmation -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Delete Course Confirmation</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h5 class="alert-heading">Warning!</h5>
                    <p>You are about to delete the following course from the system. This action cannot be undone.</p>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                        <p class="card-text">
                            <strong>Duration:</strong> <?php echo htmlspecialchars($course['duration']); ?><br>
                            <strong>Course Fee:</strong> <?php echo formatCurrency($course['course_fee']); ?><br>
                            <strong>Date Added:</strong> <?php echo formatDate($course['created_at']); ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($totalDependencies > 0): ?>
                <div class="alert alert-danger">
                    <h5 class="alert-heading">Cannot Delete!</h5>
                    <p>This course has the following dependencies:</p>
                    <ul>
                        <?php if ($batchCount > 0): ?>
                            <li><?php echo $batchCount; ?> associated batch(es)</li>
                        <?php endif; ?>
                        
                        <?php if ($studentCount > 0): ?>
                            <li><?php echo $studentCount; ?> enrolled student(s)</li>
                        <?php endif; ?>
                    </ul>
                    <p class="mb-0">Please remove these dependencies before attempting to delete this course.</p>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Courses
                    </a>
                    <a href="view_course.php?id=<?php echo $course_id; ?>" class="btn btn-primary">
                        <i class="fas fa-eye me-1"></i> View Course Details
                    </a>
                </div>
                <?php else: ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $course_id); ?>">
                    <p class="text-danger fw-bold mb-4">Are you absolutely sure you want to delete this course?</p>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Yes, Delete Course
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>