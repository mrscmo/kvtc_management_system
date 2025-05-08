<?php
// Set page title
$page_title = "Assign Batch";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required";
    header("Location: index.php");
    exit();
}

$staff_id = $_GET['id'];

// Get staff details
$staff = getStaffById($staff_id);
if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found";
    header("Location: index.php");
    exit();
}

// Check if staff is academic
if ($staff['staff_type'] != 'academic') {
    $_SESSION['error_message'] = "Only academic staff can be assigned to batches";
    header("Location: view_staff.php?id=$staff_id");
    exit();
}

// Handle unassign action
if (isset($_GET['action']) && $_GET['action'] == 'unassign' && isset($_GET['batch_id'])) {
    $batch_id = $_GET['batch_id'];
    
    // Delete the assignment
    $deleteQuery = "DELETE FROM staff_batch WHERE staff_id = ? AND batch_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("ii", $staff_id, $batch_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Batch unassigned successfully.";
    } else {
        $_SESSION['error_message'] = "Error unassigning batch: " . $conn->error;
    }
    
    // Redirect back to the page
    header("Location: assign_batch.php?id=$staff_id");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $batch_id = $_POST['batch_id'];
    $assigned_date = $_POST['assigned_date'];
    
    $errors = [];
    
    if (empty($batch_id)) {
        $errors[] = "Batch is required";
    }
    
    if (empty($assigned_date)) {
        $errors[] = "Assignment date is required";
    }
    
    // Check if batch is already assigned to this staff
    $checkQuery = "SELECT COUNT(*) as count FROM staff_batch WHERE staff_id = ? AND batch_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $staff_id, $batch_id);
    $stmt->execute();
    $checkResult = $stmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['count'] > 0) {
        $errors[] = "This batch is already assigned to this staff member";
    }
    
    // If no errors, insert the assignment
    if (empty($errors)) {
        $query = "INSERT INTO staff_batch (staff_id, batch_id, assigned_date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $staff_id, $batch_id, $assigned_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Batch assigned successfully";
            header("Location: assign_batch.php?id=$staff_id");
            exit();
        } else {
            $_SESSION['error_message'] = "Error assigning batch: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get batches that aren't already assigned to this staff
$batchesQuery = "SELECT b.id, b.batch_name, c.course_name, b.start_date, b.end_date
                FROM batches b
                JOIN courses c ON b.course_id = c.id
                WHERE b.status = 'active'
                AND b.id NOT IN (
                    SELECT batch_id FROM staff_batch WHERE staff_id = ?
                )
                ORDER BY b.start_date DESC";
$stmt = $conn->prepare($batchesQuery);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$availableBatchesResult = $stmt->get_result();

// Get currently assigned batches
$assignedBatches = getStaffBatches($staff_id);
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Assign Batch to Staff</h5>
                <a href="view_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Staff
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="border-bottom pb-2 mb-3">Staff Information</div>
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['name']); ?></dd>
                            
                            <dt class="col-sm-4">Staff Type</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-primary">Academic</span>
                            </dd>
                            
                            <dt class="col-sm-4">Job Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($staff['job_status'] == 'permanent') ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($staff['job_status']); ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Only show form if there are available batches -->
                        <?php if ($availableBatchesResult->num_rows > 0): ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $staff_id); ?>">
                            <div class="border-bottom pb-2 mb-3">Assign New Batch</div>
                            
                            <div class="mb-3">
                                <label for="batch_id" class="form-label">Select Batch <span class="text-danger">*</span></label>
                                <select class="form-select" id="batch_id" name="batch_id" required>
                                    <option value="">-- Select Batch --</option>
                                    <?php while ($batch = $availableBatchesResult->fetch_assoc()): ?>
                                        <option value="<?php echo $batch['id']; ?>">
                                            <?php echo htmlspecialchars($batch['batch_name'] . ' (' . $batch['course_name'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="assigned_date" class="form-label">Assignment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="assigned_date" name="assigned_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Assign Batch
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i> No available batches to assign. All active batches are already assigned to this staff member.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Currently Assigned Batches -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Currently Assigned Batches</h5>
            </div>
            <div class="card-body">
                <?php if (count($assignedBatches) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch Name</th>
                                <th>Course</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Assigned On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($assignedBatches as $batch): 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
                                    <td><?php echo formatDate($batch['start_date']); ?></td>
                                    <td><?php echo formatDate($batch['end_date']); ?></td>
                                    <td><?php echo formatDate($batch['assigned_date']); ?></td>
                                    <td>
                                        <a href="../batches/view_batch.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="javascript:void(0);" onclick="confirmUnassign(<?php echo $batch['batch_id']; ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Unassign
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> No batches are currently assigned to this staff member.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Unassign Confirmation Modal -->
<div class="modal fade" id="unassignModal" tabindex="-1" aria-labelledby="unassignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unassignModalLabel">Confirm Unassign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to unassign this batch from the staff member? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmUnassignBtn" class="btn btn-danger">Unassign</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmUnassign(batchId) {
    const modal = new bootstrap.Modal(document.getElementById('unassignModal'));
    document.getElementById('confirmUnassignBtn').href = 'assign_batch.php?id=<?php echo $staff_id; ?>&action=unassign&batch_id=' + batchId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>