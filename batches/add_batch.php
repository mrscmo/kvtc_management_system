<?php
// Set page title
$page_title = "Add Batch";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

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
    
    // If no errors, insert the batch
    if (empty($errors)) {
        $query = "INSERT INTO batches (course_id, batch_number, batch_name, start_date, end_date, 
                  installments, pre_assignment_date, final_assignment_date, certificate_issue, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssissss", $course_id, $batch_number, $batch_name, $start_date, $end_date, 
                          $installments, $pre_assignment_date, $final_assignment_date, $certificate_issue, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Batch added successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding batch: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get all courses for dropdown
$coursesQuery = "SELECT * FROM courses ORDER BY course_name";
$coursesResult = $conn->query($coursesQuery);

// Check if course_id is passed from course view
$selected_course_id = isset($_GET['course_id']) ? $_GET['course_id'] : '';
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Add Batch Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h5>Add New Batch</h5>
            </div>
            <div class="card-body">
                <form id="batchForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php while ($course = $coursesResult->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['duration']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch_number" class="form-label">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?php echo isset($_POST['batch_number']) ? htmlspecialchars($_POST['batch_number']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch_name" class="form-label">Batch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_name" name="batch_name" value="<?php echo isset($_POST['batch_name']) ? htmlspecialchars($_POST['batch_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d', strtotime('+6 months')); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="installments" class="form-label">Number of Installments <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="installments" name="installments" min="1" value="<?php echo isset($_POST['installments']) ? htmlspecialchars($_POST['installments']) : '1'; ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pre_assignment_date" class="form-label">Pre-Assignment Date</label>
                            <input type="date" class="form-control" id="pre_assignment_date" name="pre_assignment_date" value="<?php echo isset($_POST['pre_assignment_date']) ? htmlspecialchars($_POST['pre_assignment_date']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="final_assignment_date" class="form-label">Final Assignment Date</label>
                            <input type="date" class="form-control" id="final_assignment_date" name="final_assignment_date" value="<?php echo isset($_POST['final_assignment_date']) ? htmlspecialchars($_POST['final_assignment_date']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="certificate_issue" class="form-label">Certificate Issue</label>
                        <select class="form-select" id="certificate_issue" name="certificate_issue">
                            <option value="no" <?php echo (isset($_POST['certificate_issue']) && $_POST['certificate_issue'] == 'no') ? 'selected' : ''; ?>>No</option>
                            <option value="yes" <?php echo (isset($_POST['certificate_issue']) && $_POST['certificate_issue'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Batch</button>
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