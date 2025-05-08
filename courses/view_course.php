<?php
// Set page title
$page_title = "View Course";

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

// Get batches for this course
$batchQuery = "SELECT * FROM batches WHERE course_id = ? ORDER BY start_date DESC";
$stmt = $conn->prepare($batchQuery);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$batchResult = $stmt->get_result();

// Get student count for this course
$studentCountQuery = "SELECT COUNT(*) as count FROM students WHERE course_id = ?";
$stmt = $conn->prepare($studentCountQuery);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$studentCountResult = $stmt->get_result();
$studentCount = $studentCountResult->fetch_assoc()['count'];
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Course Details -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Course Details</h5>
                <div>
                    <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-sm btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 30%">Course Name</th>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Duration</th>
                        <td><?php echo htmlspecialchars($course['duration']); ?></td>
                    </tr>
                    <tr>
                        <th>Course Fee</th>
                        <td><?php echo formatCurrency($course['course_fee']); ?></td>
                    </tr>
                    <tr>
                        <th>Total Batches</th>
                        <td><?php echo $batchResult->num_rows; ?></td>
                    </tr>
                    <tr>
                        <th>Total Students</th>
                        <td><?php echo $studentCount; ?></td>
                    </tr>
                    <tr>
                        <th>Date Added</th>
                        <td><?php echo formatDate($course['created_at']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Course Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="../batches/add_batch.php?course_id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus-circle me-2"></i> Add New Batch for this Course
                    </a>
                    <a href="../batches/index.php?course_id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> View All Batches for this Course
                    </a>
                    <a href="../students/index.php?course_id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-graduate me-2"></i> View All Students for this Course
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Batches List -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Batches for <?php echo htmlspecialchars($course['course_name']); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch Number</th>
                                <th>Batch Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($batchResult->num_rows > 0) {
                                $counter = 1;
                                while ($batch = $batchResult->fetch_assoc()) { 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                                    <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                    <td><?php echo formatDate($batch['start_date']); ?></td>
                                    <td><?php echo formatDate($batch['end_date']); ?></td>
                                    <td>
                                        <?php if ($batch['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../batches/view_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center">No batches found for this course</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>