<?php
// Set page title
$page_title = "Edit Course";

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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $course_name = trim($_POST['course_name']);
    $duration = trim($_POST['duration']);
    $course_fee = floatval($_POST['course_fee']);
    
    $errors = [];
    
    if (empty($course_name)) {
        $errors[] = "Course name is required";
    }
    
    if (empty($duration)) {
        $errors[] = "Duration is required";
    }
    
    if (empty($course_fee) || $course_fee <= 0) {
        $errors[] = "Valid course fee is required";
    }
    
    // If no errors, update the course
    if (empty($errors)) {
        $query = "UPDATE courses SET course_name = ?, duration = ?, course_fee = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdi", $course_name, $duration, $course_fee, $course_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Course updated successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error updating course: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get course details
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
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Edit Course Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Edit Course</h5>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Courses
                </a>
            </div>
            <div class="card-body">
                <form id="courseForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $course_id); ?>">
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="duration" name="duration" value="<?php echo htmlspecialchars($course['duration']); ?>" placeholder="e.g., 6 months, 1 year" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_fee" class="form-label">Course Fee <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="course_fee" name="course_fee" value="<?php echo htmlspecialchars($course['course_fee']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Course</button>
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