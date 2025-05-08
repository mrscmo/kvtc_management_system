<?php
// Set page title
$page_title = "Add Student";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $student_id = generateStudentID(); // Generate a unique student ID
    $registration_date = $_POST['registration_date'];
    $full_name = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $batch_id = $_POST['batch_id'];
    $course_id = $_POST['course_id'];
    
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
    
    // If no errors, insert the student
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert student
            $query = "INSERT INTO students (student_id, registration_date, full_name, address, contact_number, batch_id, course_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssii", $student_id, $registration_date, $full_name, $address, $contact_number, $batch_id, $course_id);
            $stmt->execute();
            
            // Get the new student ID
            $new_student_id = $conn->insert_id;
            
            // Create certification record
            $query = "INSERT INTO certifications (student_id, completion_status) VALUES (?, 'no')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $new_student_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Student added successfully with ID: $student_id";
            header("Location: view_student.php?id=$new_student_id");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Error adding student: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get all courses for dropdown
$coursesQuery = "SELECT * FROM courses ORDER BY course_name";
$coursesResult = $conn->query($coursesQuery);

// Get all active batches for dropdown
$batchesQuery = "SELECT b.id, b.batch_name, c.course_name, c.id AS course_id, c.duration, c.course_fee
                FROM batches b 
                JOIN courses c ON b.course_id = c.id 
                WHERE b.status = 'active' 
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

<!-- Add Student Form -->
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h5>Add New Student</h5>
            </div>
            <div class="card-body">
                <form id="studentForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">Section 1 - Personal Information</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="registration_date" class="form-label">Date of Registration <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="registration_date" name="registration_date" value="<?php echo isset($_POST['registration_date']) ? htmlspecialchars($_POST['registration_date']) : date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="student_id_preview" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="student_id_preview" value="Automatically generated upon saving" disabled>
                                <small class="text-muted">Student ID will be generated automatically</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" required>
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
                                    <option value="<?php echo $batch['id']; ?>" <?php echo (isset($_POST['batch_id']) && $_POST['batch_id'] == $batch['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['batch_name'] . ' (' . $batch['course_name'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="course_id" name="course_id" required disabled>
                                <option value="">Select Course</option>
                                <?php 
                                $coursesResult->data_seek(0); // Reset the result pointer
                                while ($course = $coursesResult->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <!-- Hidden input to store the actual course_id value -->
                            <input type="hidden" name="course_id" id="course_id_hidden" value="<?php echo isset($_POST['course_id']) ? htmlspecialchars($_POST['course_id']) : ''; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="duration" class="form-label">Duration</label>
                                <input type="text" class="form-control" id="duration" name="duration" value="<?php echo isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''; ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_fee" class="form-label">Course Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="text" class="form-control" id="course_fee" name="course_fee" value="<?php echo isset($_POST['course_fee']) ? htmlspecialchars($_POST['course_fee']) : ''; ?>" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Student</button>
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
            courseSelect.value = courseId;
            courseIdHidden.value = courseId;
            
            // Update duration and fee
            durationInput.value = batchData[batchId].duration;
            courseFeeInput.value = batchData[batchId].course_fee;
        } else {
            courseSelect.value = '';
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