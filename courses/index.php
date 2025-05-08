<?php
// Set page title
$page_title = "Course Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle course deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $course_id = $_GET['id'];
    
    // Check if the course is associated with any batches
    $checkBatchQuery = "SELECT COUNT(*) as count FROM batches WHERE course_id = ?";
    $stmt = $conn->prepare($checkBatchQuery);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batchCount = $result->fetch_assoc()['count'];
    
    if ($batchCount > 0) {
        $_SESSION['error_message'] = "Cannot delete course. It is associated with $batchCount batch(es).";
    } else {
        // Delete the course
        $deleteQuery = "DELETE FROM courses WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $course_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Course deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting course: " . $conn->error;
        }
    }
    
    // Redirect back to the courses page
    header("Location: index.php");
    exit();
}

// Get all courses
$query = "SELECT * FROM courses ORDER BY course_name";
$result = $conn->query($query);
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Courses List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Courses</h5>
        <a href="add_course.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i> Add New Course
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Course Name</th>
                        <th>Duration</th>
                        <th>Course Fee</th>
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
                            <td><?php echo $row['course_name']; ?></td>
                            <td><?php echo $row['duration']; ?></td>
                            <td><?php echo formatCurrency($row['course_fee']); ?></td>
                            <td>
                                <a href="view_course.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_course.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
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
                            <td colspan="5" class="text-center">No courses found</td>
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
                Are you sure you want to delete this course? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(courseId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').href = 'index.php?action=delete&id=' + courseId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>