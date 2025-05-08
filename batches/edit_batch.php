<?php
// Set page title
$page_title = "Edit Batch";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Batch ID is required";
    header("Location: index.php");
    exit();
}

$batch_id = $_GET['id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $course_id = $_POST['course_id'];
    $batch_number = trim($_POST['batch_number']);
    $batch_name = trim($_POST['batch_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $installments = intval($_POST['installments']);
    $pre_assignment_date = !empty($_POST['pre_assignment_date']) ? $_POST['pre_assignment_date'] : null;
    $final_assignment_date = !empty($_POST['final_assignment_date']) ? $_POST['final_assignment_date'] : null;
    $certificate_issue = $_POST['certificate_issue'];
    $status = $_POST['status'];
    
    $errors = [];
    
    if (empty($course_id)) {
        $errors[] = "Course is required";
    }
    
    if (empty($batch_number)) {
        $errors[] = "Batch number is required";
    }
    
    if (empty($batch_name)) {
        $errors[] = "Batch name is required";
    }
    
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    
    if (empty($end_date)) {
        $errors[] = "End date is required";
    } elseif ($end_date < $start_date) {
        $errors[] = "End date cannot be before start date";
    }
    
    if ($installments <= 0) {
        $errors[] = "Number of installments must be at least 1";
    }
    
    if (!empty($pre_assignment_date) && $pre_assignment_date < $start_date) {
        $errors[] = "Pre-assignment date cannot be before start date";
    }
    
    if (!empty($final_assignment_date) && $final_assignment_date > $end_date) {
        $errors[] = "Final assignment date cannot be after end date";
    }
    
    // If no errors, update the batch
    if (empty($errors)) {
        $query = "UPDATE batches SET 
                  course_id = ?, 
                  batch_number = ?, 
                  batch_name = ?, 
                  start_date = ?, 
                  end_date = ?, 
                  installments = ?, 
                  pre_assignment_date = ?, 
                  final_assignment_date = ?, 
                  certificate_issue = ?, 
                  status = ? 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssissssi", $course_id, $batch_number, $batch_name, $start_date, $end_date, 
                          $installments, $pre_assignment_date, $final_assignment_date, $certificate_issue, $status, $batch_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Batch updated successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error updating batch: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get batch details
$query = "SELECT * FROM batches WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Batch not found";
    header("Location: index.php");
    exit();
}

$batch = $result->fetch_assoc();

// Get all courses for dropdown
$coursesQuery = "SELECT * FROM courses ORDER BY course_name";
$coursesResult = $conn->query($coursesQuery);

// Check if the batch has any enrolled students
$checkStudentsQuery = "SELECT COUNT(*) AS count FROM students WHERE batch_id = ?";
$stmt = $conn->prepare($checkStudentsQuery);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$studentResult = $stmt->get_result();
$studentCount = $studentResult->fetch_assoc()['count'];

// Get current course details
$courseQuery = "SELECT * FROM courses WHERE id = ?";
$stmt = $conn->prepare($courseQuery);
$stmt->bind_param("i", $batch['course_id']);
$stmt->execute();
$courseResult = $stmt->get_result();
$currentCourse = $courseResult->fetch_assoc();
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Edit Batch Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Edit Batch</h5>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Batches
                </a>
            </div>
            <div class="card-body">
                <?php if ($studentCount > 0): ?>
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This batch has <?php echo $studentCount; ?> enrolled student(s). 
                    Some changes may affect student records.
                </div>
                <?php endif; ?>
                
                <form id="batchForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $batch_id); ?>">
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required <?php echo ($studentCount > 0) ? 'disabled' : ''; ?>>
                            <option value="">Select Course</option>
                            <?php while ($course = $coursesResult->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($batch['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['duration']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($studentCount > 0): ?>
                            <div class="form-text text-muted">
                                Course cannot be changed because students are enrolled in this batch.
                                <input type="hidden" name="course_id" value="<?php echo $batch['course_id']; ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch_number" class="form-label">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?php echo htmlspecialchars($batch['batch_number']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch_name" class="form-label">Batch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_name" name="batch_name" value="<?php echo htmlspecialchars($batch['batch_name']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $batch['start_date']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $batch['end_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="installments" class="form-label">Number of Installments <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="installments" name="installments" min="1" value="<?php echo $batch['installments']; ?>" required>
                        <?php if ($studentCount > 0): ?>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-circle"></i> 
                                Changing installments may affect existing payment records.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pre_assignment_date" class="form-label">Pre-Assignment Date</label>
                            <input type="date" class="form-control" id="pre_assignment_date" name="pre_assignment_date" value="<?php echo $batch['pre_assignment_date']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="final_assignment_date" class="form-label">Final Assignment Date</label>
                            <input type="date" class="form-control" id="final_assignment_date" name="final_assignment_date" value="<?php echo $batch['final_assignment_date']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="certificate_issue" class="form-label">Certificate Issue</label>
                        <select class="form-select" id="certificate_issue" name="certificate_issue">
                            <option value="no" <?php echo ($batch['certificate_issue'] == 'no') ? 'selected' : ''; ?>>No</option>
                            <option value="yes" <?php echo ($batch['certificate_issue'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($batch['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($batch['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <?php if ($studentCount > 0 && $batch['status'] == 'active'): ?>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-circle"></i> 
                                Setting this batch to inactive will affect <?php echo $studentCount; ?> enrolled student(s).
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle batch name generation
    const batchNumberInput = document.getElementById('batch_number');
    const courseSelect = document.getElementById('course_id');
    const batchNameInput = document.getElementById('batch_name');
    
    // Function to update batch name
    function updateBatchName() {
        if (batchNumberInput.value.trim() !== '' && courseSelect.value !== '') {
            const courseText = courseSelect.options[courseSelect.selectedIndex].text.split('(')[0].trim();
            batchNameInput.value = courseText + ' - Batch ' + batchNumberInput.value.trim();
        }
    }
    
    // Add event listeners if elements exist and students aren't enrolled
    if (batchNumberInput && courseSelect && batchNameInput && <?php echo $studentCount == 0 ? 'true' : 'false'; ?>) {
        batchNumberInput.addEventListener('input', updateBatchName);
        courseSelect.addEventListener('change', updateBatchName);
    }
    
    // Date validation
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const preAssignmentDateInput = document.getElementById('pre_assignment_date');
    const finalAssignmentDateInput = document.getElementById('final_assignment_date');
    
    if (endDateInput && startDateInput) {
        endDateInput.addEventListener('change', function() {
            if (startDateInput.value && this.value && new Date(startDateInput.value) > new Date(this.value)) {
                alert('End date cannot be before start date');
                this.value = '';
            }
        });
        
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && this.value && new Date(endDateInput.value) < new Date(this.value)) {
                alert('Start date cannot be after end date');
                endDateInput.value = '';
            }
        });
    }
    
    if (preAssignmentDateInput && startDateInput) {
        preAssignmentDateInput.addEventListener('change', function() {
            if (startDateInput.value && this.value && new Date(startDateInput.value) > new Date(this.value)) {
                alert('Pre-assignment date cannot be before start date');
                this.value = '';
            }
        });
    }
    
    if (finalAssignmentDateInput && endDateInput) {
        finalAssignmentDateInput.addEventListener('change', function() {
            if (endDateInput.value && this.value && new Date(endDateInput.value) < new Date(this.value)) {
                alert('Final assignment date cannot be after end date');
                this.value = '';
            }
        });
    }
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>