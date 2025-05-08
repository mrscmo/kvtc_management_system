<?php
// Set page title
$page_title = "Staff Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle staff deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $staff_id = $_GET['id'];
    
    // Delete the staff
    $deleteQuery = "DELETE FROM staff WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $staff_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Staff member deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting staff member: " . $conn->error;
    }
    
    // Redirect back to the staff page
    header("Location: index.php");
    exit();
}

// Prepare query based on filters and search
$query = "SELECT * FROM staff WHERE 1=1";

$params = [];
$types = "";

// Search by name or NIC
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $query .= " AND (name LIKE ? OR nic_number LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

// Filter by staff type
if (isset($_GET['staff_type']) && !empty($_GET['staff_type'])) {
    $query .= " AND staff_type = ?";
    $params[] = $_GET['staff_type'];
    $types .= "s";
}

// Filter by job status
if (isset($_GET['job_status']) && !empty($_GET['job_status'])) {
    $query .= " AND job_status = ?";
    $params[] = $_GET['job_status'];
    $types .= "s";
}

// Add ordering
$query .= " ORDER BY name ASC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Search and Filter Staff</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search by Name or NIC</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="staff_type" class="form-label">Staff Type</label>
                <select class="form-select" id="staff_type" name="staff_type">
                    <option value="">All Types</option>
                    <option value="academic" <?php echo (isset($_GET['staff_type']) && $_GET['staff_type'] == 'academic') ? 'selected' : ''; ?>>Academic</option>
                    <option value="non_academic" <?php echo (isset($_GET['staff_type']) && $_GET['staff_type'] == 'non_academic') ? 'selected' : ''; ?>>Non-Academic</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="job_status" class="form-label">Job Status</label>
                <select class="form-select" id="job_status" name="job_status">
                    <option value="">All Statuses</option>
                    <option value="training" <?php echo (isset($_GET['job_status']) && $_GET['job_status'] == 'training') ? 'selected' : ''; ?>>Training</option>
                    <option value="permanent" <?php echo (isset($_GET['job_status']) && $_GET['job_status'] == 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Staff List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Staff Members</h5>
        <a href="add_staff.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Add New Staff
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>NIC Number</th>
                        <th>Phone Number</th>
                        <th>Staff Type</th>
                        <th>Job Status</th>
                        <th>Monthly Salary</th>
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
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['nic_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td>
                                <span class="badge <?php echo ($row['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                    <?php echo ucfirst(str_replace('_', '-', $row['staff_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo ($row['job_status'] == 'permanent') ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($row['job_status']); ?>
                                </span>
                                <?php if ($row['job_status'] == 'training' && !empty($row['training_end_date'])): ?>
                                    <small class="text-muted">until <?php echo formatDate($row['training_end_date']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatCurrency($row['monthly_salary']); ?></td>
                            <td>
                                <a href="view_staff.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_staff.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($row['staff_type'] == 'academic'): ?>
                                <a href="assign_batch.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary btn-action" title="Assign Batch">
                                    <i class="fas fa-chalkboard"></i>
                                </a>
                                <?php endif; ?>
                                <a href="salary/pay_salary.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success btn-action" title="Pay Salary">
                                    <i class="fas fa-money-bill-wave"></i>
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
                            <td colspan="8" class="text-center">No staff members found</td>
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
                Are you sure you want to delete this staff member? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(staffId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').href = 'index.php?action=delete&id=' + staffId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>