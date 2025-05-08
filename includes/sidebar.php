<?php
// Check if all necessary variables are set before starting the HTML output
// This ensures no output happens before potential redirects

// The sidebar.php file was previously outputting HTML content immediately
// We need to place all HTML output inside a function to prevent it from executing immediately

/**
 * Renders the sidebar navigation menu
 * This function should be called after all potential header() redirects
 */
function renderSidebar() {
    // Get the current file for highlighting active menu items
    $current_file = basename($_SERVER['PHP_SELF']);
    $current_dir = dirname($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_file == 'index.php' && $current_dir == '/vocational_training_center' ? 'active' : ''; ?>" href="/vocational_training_center/index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/courses') !== false ? 'active' : ''; ?>" href="/vocational_training_center/courses/index.php">
                    <i class="fas fa-book me-2"></i>
                    Course Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/batches') !== false ? 'active' : ''; ?>" href="/vocational_training_center/batches/index.php">
                    <i class="fas fa-users me-2"></i>
                    Batch Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/students') !== false ? 'active' : ''; ?>" href="/vocational_training_center/students/index.php">
                    <i class="fas fa-user-graduate me-2"></i>
                    Student Management
                </a>
            </li>
            <!-- New Staff Management Section -->
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/staff') !== false ? 'active' : ''; ?>" href="/vocational_training_center/staff/index.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Staff Management
                </a>
            </li>
            <?php if (isAdmin()): ?>
                <li class="nav-item mt-4">
                    <hr class="mx-3">
                    <span class="nav-link text-muted small">
                        <i class="fas fa-cog me-2"></i>
                        Administration
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($current_dir, '/users') !== false ? 'active' : ''; ?>" href="/vocational_training_center/users/index.php">
                        <i class="fas fa-users-cog me-2"></i>
                        User Management
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/finance') !== false ? 'active' : ''; ?>" href="/vocational_training_center/finance/index.php">
                    <i class="fas fa-dollar-sign me-2"></i>
                    Finance Management
                </a>
            </li>
            <!-- New Notifications Section -->
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_dir, '/notifications') !== false ? 'active' : ''; ?>" href="/vocational_training_center/notifications/index.php">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                    <span id="notification-badge" class="badge bg-danger rounded-pill ms-2 d-none">0</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<script>
// Check for unread notifications
function checkNotifications() {
    fetch('/vocational_training_center/ajax/check_notifications.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notification-badge');
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Check notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    checkNotifications();
    // Check again every 5 minutes
    setInterval(checkNotifications, 5 * 60 * 1000);
});
</script>
<?php
}
// Don't render the sidebar yet - it will be rendered from the header.php after all potential redirects
?>