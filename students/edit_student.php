<?php
// Set page title
$page_title = "Edit Student";

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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $full_name = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $batch_id = $_POST['batch_id'];
    $course_id = $_POST['course_id_hidden']; // Using hidden field for course_id
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($contact_number)) {
        $errors[] = "Contact number is required";
    }
    
    if (empty($batch_id)) {
        $errors[] = "Batch is required";
    }
    
    if (empty($course_id)) {
        $errors[] = "Course is required";
    }
    
    // If no errors, update the student
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update student information
            $query = "UPDATE students SET 
                        full_name = ?, 
                        address = ?, 
                        contact_number = ?, 
                        batch_id = ?, 
                        course_id = ?
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssiii", $full_name, $address, $contact_number, $batch_id, $course_id, $student_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Student information updated successfully";
            header("Location: view_student.php?id=$student_id");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Error updating student: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get student details
$query = "SELECT s.*, b.batch_name, c.course_name, c.duration, c.course_fee 
          FROM students s
          JOIN batches b ON s.batch_id = b.id
          JOIN courses c ON s.course_id = c.id
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

// Get all active batches for dropdown
$batchesQuery = "SELECT b.id, b.batch_name, c.course_name, c.id AS course_id, c.duration, c.course_fee
                FROM batches b 
                JOIN courses c ON b.course_id = c.id 
                ORDER BY b.start_date DESC";
$batchesResult = $conn->query($batchesQuery);

// Prepare batches data for JavaScript
$batchesArray = array();
while ($batch = $batchesResult->fetch_assoc()) {
    $batchesArray[$batch['id']] = array(
        'course_id' => $batch['course_id'],
        'duration' => $batch['duration'],
        'course_fee' => $batch['course_fee']
    );
}
$batchesResult->data_seek(0); // Reset the result pointer
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Edit Student Form -->
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Edit Student</h5>
                <div>
                    <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Student
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form id="studentForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $student_id); ?>">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">Section 1 - Personal Information</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="registration_date" class="form-label">Date of Registration</label>
                                <input type="date" class="form-control" id="registration_date" value="<?php echo $student['registration_date']; ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="student_id_preview" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="student_id_preview" value="<?php echo htmlspecialchars($student['student_id']); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($student['address']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($student['contact_number']); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Course Details Section -->
                    <div class="form-section mt-4">
                        <div class="form-section-title">Section 2 - Course Details</div>
                        
                        <div class="mb-3">
                            <label for="batch_id" class="form-label">Batch <span class="text-danger">*</span></label>
                            <select class="form-select" id="batch_id" name="batch_id" required>
                                <option value="">Select Batch</option>
                                <?php while ($batch = $batchesResult->fetch_assoc()): ?>
                                    <option value="<?php echo $batch['id']; ?>" <?php echo ($student['batch_id'] == $batch['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['batch_name'] . ' (' . $batch['course_name'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course</label>
                            <select class="form-select" id="course_id" disabled>
                                <option value="<?php echo $student['course_id']; ?>"><?php echo htmlspecialchars($student['course_name']); ?></option>
                            </select>
                            <!-- Hidden input to store the actual course_id value -->
                            <input type="hidden" name="course_id_hidden" id="course_id_hidden" value="<?php echo $student['course_id']; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="duration" class="form-label">Duration</label>
                                <input type="text" class="form-control" id="duration" value="<?php echo htmlspecialchars($student['duration']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_fee" class="form-label">Course Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="text" class="form-control" id="course_fee" value="<?php echo htmlspecialchars($student['course_fee']); ?>" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Store batch data
const batchData = <?php echo json_encode($batchesArray); ?>;

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    const batchSelect = document.getElementById('batch_id');
    const courseSelect = document.getElementById('course_id');
    const courseIdHidden = document.getElementById('course_id_hidden');
    const durationInput = document.getElementById('duration');
    const courseFeeInput = document.getElementById('course_fee');
    
    batchSelect.addEventListener('change', function() {
        const batchId = this.value;
        
        if (batchId && batchData[batchId]) {
            const courseId = batchData[batchId].course_id;
            
            // Update course dropdown
            courseSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = courseId;
            option.text = document.querySelector(`#batch_id option[value="${batchId}"]`).textContent.split('(')[1].replace(')', '').trim();
            courseSelect.appendChild(option);
            
            courseIdHidden.value = courseId;
            
            // Update duration and fee
            durationInput.value = batchData[batchId].duration;
            courseFeeInput.value = batchData[batchId].course_fee;
        } else {
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            courseIdHidden.value = '';
            durationInput.value = '';
            courseFeeInput.value = '';
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>