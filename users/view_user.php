<?php
// Set page title
$page_title = "View User";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Require admin privileges
requireAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "User ID is required";
    header("Location: index.php");
    exit();
}

$user_id = $_GET['id'];

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "User not found";
    header("Location: index.php");
    exit();
}

$user = $result->fetch_assoc();

// Get user activity statistics
$activityStats = [];

// Count activities if you have activity logging
// This is a placeholder for future implementation
$activityStats['total_logins'] = 0;
$activityStats['last_activity'] = $user['last_login'];
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- User Details -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>User Details</h5>
                <div>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-sm btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 30%">Full Name</th>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $user['role'] == 'admin' ? 'danger' : 
                                    ($user['role'] == 'manager' ? 'warning' : 'info'); 
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Last Login</th>
                        <td>
                            <?php 
                            if ($user['last_login']) {
                                echo date('Y-m-d H:i:s', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Updated At</th>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>User Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-edit me-2"></i> Edit User
                        </a>
                        <?php if ($user['status'] == 'active'): ?>
                            <a href="index.php?action=deactivate&id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action list-group-item-warning">
                                <i class="fas fa-user-slash me-2"></i> Deactivate User
                            </a>
                        <?php else: ?>
                            <a href="index.php?action=activate&id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action list-group-item-success">
                                <i class="fas fa-user-check me-2"></i> Activate User
                            </a>
                        <?php endif; ?>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $user['id']; ?>)" class="list-group-item list-group-item-action list-group-item-danger">
                            <i class="fas fa-trash me-2"></i> Delete User
                        </a>
                    <?php else: ?>
                        <div class="list-group-item">
                            <em>This is your account. Visit your profile to make changes.</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Activity Statistics</h5>
            </div>
            <div class="card-body">
                <p><strong>Last Activity:</strong> 
                    <?php 
                    if ($activityStats['last_activity']) {
                        echo date('Y-m-d H:i:s', strtotime($activityStats['last_activity']));
                    } else {
                        echo 'Never';
                    }
                    ?>
                </p>
                <p><strong>Total Logins:</strong> <?php echo $activityStats['total_logins']; ?></p>
                <p><small class="text-muted">Note: Activity tracking will be implemented in future updates.</small></p>
            </div>
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
                Are you sure you want to delete this user? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').href = 'index.php?action=delete&id=' + userId;
    modal.show();
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>