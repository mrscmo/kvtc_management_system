<?php
// Set page title
$page_title = "Student Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle student deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    
    // Delete the student
    $deleteQuery = "DELETE FROM students WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Student deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting student: " . $conn->error;
    }
    
    // Redirect back to the students page
    header("Location: index.php");
    exit();
}

// Prepare query based on filters and search
$query = "SELECT s.*, c.course_name, b.batch_name 
          FROM students s 
          JOIN courses c ON s.course_id = c.id
          JOIN batches b ON s.batch_id = b.id
          WHERE 1=1";

$params = [];
$types = "";

// Search by student ID or name
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $query .= " AND (s.student_id LIKE ? OR s.full_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

// Filter by course
if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
    $query .= " AND s.course_id = ?";
    $params[] = $_GET['course_id'];
    $types .= "i";
}

// Filter by batch
if (isset($_GET['batch_id']) && !empty($_GET['batch_id'])) {
    $query .= " AND s.batch_id = ?";
    $params[] = $_GET['batch_id'];
    $types .= "i";
}

// Add ordering
$query .= " ORDER BY s.registration_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all courses for filter dropdown
$coursesQuery = "SELECT * FROM courses ORDER BY course_name";
$coursesResult = $conn->query($coursesQuery);

// Get all batches for filter dropdown
$batchesQuery = "SELECT b.id, b.batch_name, c.course_name 
                FROM batches b 
                JOIN courses c ON b.course_id = c.id 
                WHERE b.status = 'active' 
                ORDER BY b.start_date DESC";
$batchesResult = $conn->query($batchesQuery);
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Search and Filter Students</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search by ID or Name</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="course_id" class="form-label">Course</label>
                <select class="form-select" id="course_id" name="course_id">
                    <option value="">All Courses</option>
                    <?php while ($course = $coursesResult->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="batch_id" class="form-label">Batch</label>
                <select class="form-select" id="batch_id" name="batch_id">
                    <option value="">All Batches</option>
                    <?php while ($batch = $batchesResult->fetch_assoc()): ?>
                        <option value="<?php echo $batch['id']; ?>" <?php echo (isset($_GET['batch_id']) && $_GET['batch_id'] == $batch['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Search
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Students List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Students</h5>
        <a href="add_student.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Add New Student
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Address</th>
                        <th>Course</th>
                        <th>Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0) {
                        $counter = 1;
                        while ($row = $result->fetch_assoc()) { 
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['address'], 0, 30)) . (strlen($row['address']) > 30 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td>
                                <a href="view_student.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_student.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-danger btn-action" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="8" class="text-center">No students found</td>
                        </tr>
                    <?php 
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this student? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').href = 'index.php?action=delete&id=' + studentId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>