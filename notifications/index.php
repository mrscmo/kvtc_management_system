<?php
// Start session and include functions BEFORE any output
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle deletion BEFORE including header
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $notification_id = $_GET['id'];
    
    // Delete the notification
    $deleteQuery = "DELETE FROM notifications WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Notification deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting notification: " . $conn->error;
    }
    
    // Redirect back to the notifications page
    header("Location: index.php");
    exit();
}

// Handle mark all read
if (isset($_GET['action']) && $_GET['action'] == 'mark_all_read') {
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
    if ($conn->query($updateQuery)) {
        $_SESSION['success_message'] = "All notifications marked as read.";
    } else {
        $_SESSION['error_message'] = "Error marking notifications as read: " . $conn->error;
    }
    
    // Redirect back to the notifications page
    header("Location: index.php");
    exit();
}

// Set page title
$page_title = "Notifications";

// Include header NOW, after potential redirects
include_once __DIR__ . '/../includes/header.php';

// Get all notifications
$query = "SELECT * FROM notifications ORDER BY created_at DESC, is_read ASC";
$result = $conn->query($query);

// Get unread count
$unreadCount = getUnreadNotificationsCount();
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Notifications Page -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>All Notifications</h5>
                <?php if ($unreadCount > 0): ?>
                <a href="index.php?action=mark_all_read" class="btn btn-primary">
                    <i class="fas fa-check-double me-1"></i> Mark All as Read
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($notification = $result->fetch_assoc()): ?>
                            <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary me-2">New</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h6>
                                    <small class="text-muted"><?php echo formatDate($notification['created_at']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Related: <?php echo ucfirst(str_replace('_', ' ', $notification['related_to'])); ?>
                                    </small>
                                    <div>
                                        <?php if (!$notification['is_read']): ?>
                                            <a href="mark_read.php?id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?action=delete&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No notifications found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>