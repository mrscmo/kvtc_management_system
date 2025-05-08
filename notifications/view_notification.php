<?php
// Set page title
$page_title = "View Notification";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Notification ID is required";
    header("Location: index.php");
    exit();
}

$notification_id = $_GET['id'];

// Get notification details
$query = "SELECT * FROM notifications WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $notification_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Notification not found";
    header("Location: index.php");
    exit();
}

$notification = $result->fetch_assoc();

// Mark notification as read if not already
if (!$notification['is_read']) {
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
}

// Get related entity details
$relatedEntity = null;
if ($notification['related_to'] == 'staff_training' && $notification['related_id']) {
    $query = "SELECT * FROM staff WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $notification['related_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $relatedEntity = $result->fetch_assoc();
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>
                    <?php if (!$notification['is_read']): ?>
                    <span class="badge bg-primary me-2">New</span>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($notification['title']); ?>
                </h5>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Notifications
                </a>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">
                            <?php
                            if ($notification['related_to'] == 'staff_training') {
                                echo '<i class="fas fa-user-tie me-1"></i> Staff Training Notification';
                            } elseif ($notification['related_to'] == 'batch') {
                                echo '<i class="fas fa-users me-1"></i> Batch Notification';
                            } elseif ($notification['related_to'] == 'student') {
                                echo '<i class="fas fa-user-graduate me-1"></i> Student Notification';
                            } else {
                                echo '<i class="fas fa-bell me-1"></i> System Notification';
                            }
                            ?>
                        </span>
                        <span class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d-m-Y', strtotime($notification['created_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="border-top border-bottom py-3 my-3">
                        <p><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                    </div>
                    
                    <?php if ($notification['related_to'] == 'staff_training' && $relatedEntity): ?>
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Related Staff Information</h6>
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Name</dt>
                                <dd class="col-sm-9"><?php echo htmlspecialchars($relatedEntity['name']); ?></dd>
                                
                                <dt class="col-sm-3">NIC Number</dt>
                                <dd class="col-sm-9"><?php echo htmlspecialchars($relatedEntity['nic_number']); ?></dd>
                                
                                <dt class="col-sm-3">Training Ends</dt>
                                <dd class="col-sm-9">
                                    <?php
                                    if (!empty($relatedEntity['training_end_date'])) {
                                        echo formatDate($relatedEntity['training_end_date']);
                                        $daysLeft = (strtotime($relatedEntity['training_end_date']) - time()) / (60 * 60 * 24);
                                        if ($daysLeft > 0) {
                                            echo ' <span class="badge bg-warning">' . round($daysLeft) . ' days left</span>';
                                        } elseif ($daysLeft <= 0) {
                                            echo ' <span class="badge bg-danger">Training period ended</span>';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </dd>
                                
                                <dt class="col-sm-3">Actions</dt>
                                <dd class="col-sm-9">
                                    <a href="../staff/view_staff.php?id=<?php echo $relatedEntity['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i> View Staff
                                    </a>
                                    <a href="../staff/edit_staff.php?id=<?php echo $relatedEntity['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit me-1"></i> Edit Staff
                                    </a>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <?php if ($notification['is_read']): ?>
                    <a href="mark_read.php?id=<?php echo $notification['id']; ?>&read=0" class="btn btn-outline-primary">
                        <i class="fas fa-eye-slash me-1"></i> Mark as Unread
                    </a>
                    <?php else: ?>
                    <a href="mark_read.php?id=<?php echo $notification['id']; ?>&read=1" class="btn btn-outline-primary">
                        <i class="fas fa-check me-1"></i> Mark as Read
                    </a>
                    <?php endif; ?>
                    <a href="delete_notification.php?id=<?php echo $notification['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?')">
                        <i class="fas fa-trash me-1"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>