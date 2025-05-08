<?php
// Set page title
$page_title = "Batch Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle batch deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $batch_id = $_GET['id'];
    
    // Check if the batch is associated with any students
    $checkStudentQuery = "SELECT COUNT(*) as count FROM students WHERE batch_id = ?";
    $stmt = $conn->prepare($checkStudentQuery);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentCount = $result->fetch_assoc()['count'];
    
    if ($studentCount > 0) {
        $_SESSION['error_message'] = "Cannot delete batch. It is associated with $studentCount student(s).";
    } else {
        // Delete the batch
        $deleteQuery = "DELETE FROM batches WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $batch_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Batch deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting batch: " . $conn->error;
        }
    }
    
    // Redirect back to the batches page
    header("Location: index.php");
    exit();
}

// Prepare query based on filters
$query = "SELECT b.*, c.course_name 
          FROM batches b 
          JOIN courses c ON b.course_id = c.id";

$params = [];
$types = "";

// Filter by course
if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
    $query .= " WHERE b.course_id = ?";
    $params[] = $_GET['course_id'];
    $types .= "i";
}

// Add ordering
$query .= " ORDER BY b.start_date DESC";

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
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Filter Batches</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-4">
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
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter me-1"></i> Apply Filter
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i> Clear Filter
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Batches List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Batches</h5>
        <a href="add_batch.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i> Add New Batch
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Batch Number</th>
                        <th>Batch Name</th>
                        <th>Course</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
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
                            <td><?php echo htmlspecialchars($row['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                            <td><?php echo formatDate($row['start_date']); ?></td>
                            <td><?php echo formatDate($row['end_date']); ?></td>
                            <td>
                                <?php if ($row['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view_batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
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
                            <td colspan="8" class="text-center">No batches found</td>
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
                Are you sure you want to delete this batch? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(batchId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').href = 'index.php?action=delete&id=' + batchId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>