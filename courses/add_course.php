<?php
// Start session and turn on error reporting for debugging
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Optional: Log errors to a file for review
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../config/database.php'; // Replace with your DB connection file

// Debug log to file (custom helper)
function debug_log($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, 3, __DIR__ . '/../logs/debug.log');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $course_name = trim($_POST['course_name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $course_fee = floatval($_POST['course_fee'] ?? 0);

    $errors = [];

    if (empty($course_name)) $errors[] = "Course name is required";
    if (empty($duration)) $errors[] = "Duration is required";
    if (empty($course_fee) || $course_fee <= 0) $errors[] = "Valid course fee is required";

    debug_log("Form Submitted. Data: Name = $course_name, Duration = $duration, Fee = $course_fee");

    if (empty($errors)) {
        $query = "INSERT INTO courses (course_name, duration, course_fee) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            $errMsg = "Prepare failed: " . $conn->error;
            debug_log($errMsg);
            $_SESSION['error_message'] = $errMsg;
        } else {
            $stmt->bind_param("ssd", $course_name, $duration, $course_fee);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Course added successfully";
                debug_log("Insert successful, redirecting...");
                header("Location: index.php");
                exit();
            } else {
                $errMsg = "Execute failed: " . $stmt->error;
                debug_log($errMsg);
                $_SESSION['error_message'] = "Error adding course: " . $stmt->error;
            }
        }
    } else {
        debug_log("Validation errors: " . implode(", ", $errors));
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Only after processing form and setting session messages:
$page_title = "Add Course";
include_once __DIR__ . '/../includes/header.php';
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Add Course Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h5>Add New Course</h5>
            </div>
            <div class="card-body">
                <form id="courseForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo isset($_POST['course_name']) ? htmlspecialchars($_POST['course_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="duration" name="duration" value="<?php echo isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''; ?>" placeholder="e.g., 6 months, 1 year" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_fee" class="form-label">Course Fee <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="course_fee" name="course_fee" value="<?php echo isset($_POST['course_fee']) ? htmlspecialchars($_POST['course_fee']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Course</button>
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