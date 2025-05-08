<?php
// Set page title
$page_title = "User Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Require admin privileges
requireAdmin();

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Prevent deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        // Delete the user
        $deleteQuery = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
        }
    }
    
    // Redirect back to the users page
    header("Location: index.php");
    exit();
}

// Get all users
$query = "SELECT * FROM users ORDER BY full_name";
$result = $conn->query($query);
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Users List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>All Users</h5>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Add New User
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
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
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $row['role'] == 'admin' ? 'danger' : 
                                        ($row['role'] == 'manager' ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($row['last_login']) {
                                    echo date('Y-m-d H:i:s', strtotime($row['last_login']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="view_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-danger btn-action" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="8" class="text-center">No users found</td>
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