<?php
// Set page title
$page_title = "My Profile";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Get current user data
$user = getCurrentUser();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = !empty($_POST['new_password']) ? trim($_POST['new_password']) : null;
    $confirm_password = !empty($_POST['confirm_password']) ? trim($_POST['confirm_password']) : null;
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if password is being changed
    if (!empty($new_password)) {
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }
    
    // Check if email already exists for another user
    if (empty($errors)) {
        $checkQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("si", $email, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists for another user";
        }
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $full_name, $email, $hashed_password, $user['id']);
        } else {
            // Update without changing password
            $query = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $full_name, $email, $user['id']);
        }
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $user = getCurrentUser(); // Refresh user data
            $_SESSION['success_message'] = "Profile updated successfully";
        } else {
            $_SESSION['error_message'] = "Error updating profile: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Profile Information -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h5>My Profile</h5>
            </div>
            <div class="card-body">
                <!-- Profile Details -->
                <div class="mb-4">
                    <h6 class="border-bottom pb-2">Profile Information</h6>
                    <div class="row">
                        <div class="col-sm-3">
                            <strong>Username:</strong>
                        </div>
                        <div class="col-sm-9">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-3">
                            <strong>Role:</strong>
                        </div>
                        <div class="col-sm-9">
                            <span class="badge bg-<?php 
                                echo $user['role'] == 'admin' ? 'danger' : 
                                    ($user['role'] == 'manager' ? 'warning' : 'info'); 
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-3">
                            <strong>Last Login:</strong>
                        </div>
                        <div class="col-sm-9">
                            <?php 
                            if ($user['last_login']) {
                                echo date('Y-m-d H:i:s', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-3">
                            <strong>Member Since:</strong>
                        </div>
                        <div class="col-sm-9">
                            <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Form -->
                <h6 class="border-bottom pb-2 mb-3">Edit Profile</h6>
                <form id="profileForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3">Change Password</h6>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <small class="text-muted">Required only if changing password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <small class="text-muted">Leave blank to keep current password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    // Password validation
    profileForm.addEventListener('submit', function(e) {
        // If changing password, current password is required
        if (newPassword.value && !currentPassword.value) {
            e.preventDefault();
            currentPassword.setCustomValidity("Current password is required to change password");
            currentPassword.reportValidity();
            return;
        }
        
        // If changing password, confirmation must match
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            confirmPassword.setCustomValidity("Passwords don't match");
            confirmPassword.reportValidity();
            return;
        }
        
        currentPassword.setCustomValidity('');
        confirmPassword.setCustomValidity('');
    });
    
    // Real-time password matching check
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    // Clear current password requirement if not changing password
    newPassword.addEventListener('input', function() {
        if (!this.value) {
            currentPassword.setCustomValidity('');
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>