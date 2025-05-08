<?php
// Set page title
$page_title = "Training Period Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Get training staff data
$query = "SELECT s.*, 
          DATEDIFF(s.training_end_date, CURDATE()) as days_remaining,
          TIMESTAMPDIFF(MONTH, s.created_at, s.training_end_date) as training_duration_months
          FROM staff s
          WHERE s.job_status = 'training'
          ORDER BY s.training_end_date ASC";
$result = $conn->query($query);

// Categorize training staff
$endingSoon = []; // Within 30 days
$endingLater = []; // More than 30 days
$overdueTraining = []; // Training period ended but status not updated

while ($row = $result->fetch_assoc()) {
    if ($row['days_remaining'] < 0) {
        $overdueTraining[] = $row;
    } elseif ($row['days_remaining'] <= 30) {
        $endingSoon[] = $row;
    } else {
        $endingLater[] = $row;
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="training_period_report_' . date('Y-m-d') . '.pdf"');
    // In a real implementation, you would generate PDF here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Training Period Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Training Period Report</h5>
                <div>
                    <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=pdf'; ?>" class="btn btn-outline-secondary export-pdf">
                        <i class="fas fa-file-pdf me-1"></i> Export as PDF
                    </a>
                    <a href="../index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Staff
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-danger bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-danger mb-2">Overdue Training</h6>
                                <h4><?php echo count($overdueTraining); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-warning mb-2">Ending Within 30 Days</h6>
                                <h4><?php echo count($endingSoon); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-2">Ending Later</h6>
                                <h4><?php echo count($endingLater); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overdue Training -->
                <?php if (count($overdueTraining) > 0): ?>
                <h6 class="border-bottom pb-2 mb-3 text-danger">Overdue Training (Requires Immediate Action)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>NIC Number</th>
                                <th>Staff Type</th>
                                <th>Training End Date</th>
                                <th>Days Overdue</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueTraining as $staff): ?>
                                <tr class="table-danger">
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['nic_number']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($staff['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', '-', $staff['staff_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($staff['training_end_date']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $staff['days_remaining']; ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../view_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (count($overdueTraining) == 0 && count($endingSoon) == 0 && count($endingLater) == 0): ?>
                    <div class="alert alert-info">No staff members are currently in training.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', '-', $staff['staff_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($staff['training_end_date']); ?></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?php echo abs($staff['days_remaining']); ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-warning" title="Update Status">
                                            <i class="fas fa-edit"></i> Update
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Ending Soon -->
                <?php if (count($endingSoon) > 0): ?>
                <h6 class="border-bottom pb-2 mb-3 text-warning">Training Ending Within 30 Days</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>NIC Number</th>
                                <th>Staff Type</th>
                                <th>Training End Date</th>
                                <th>Days Remaining</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($endingSoon as $staff): ?>
                                <tr class="table-warning">
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['nic_number']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($staff['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', '-', $staff['staff_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($staff['training_end_date']); ?></td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?php echo $staff['days_remaining']; ?> days
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../view_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Ending Later -->
                <?php if (count($endingLater) > 0): ?>
                <h6 class="border-bottom pb-2 mb-3">Training Ending Later</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>NIC Number</th>
                                <th>Staff Type</th>
                                <th>Training End Date</th>
                                <th>Days Remaining</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($endingLater as $staff): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['nic_number']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($staff