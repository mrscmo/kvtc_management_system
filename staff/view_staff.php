<?php
// Set page title
$page_title = "View Staff";

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

// Get batches assigned to academic staff
$assignedBatches = [];
if ($staff['staff_type'] == 'academic') {
    $assignedBatches = getStaffBatches($staff_id);
}

// Get salary history
$salaryHistory = getStaffSalaryHistory($staff_id);

// Calculate total salary paid
$totalSalaryPaid = 0;
foreach ($salaryHistory as $salary) {
    if ($salary['payment_status'] == 'paid') {
        $totalSalaryPaid += $salary['amount'];
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Staff Information -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Staff Information</h5>
                <div>
                    <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <?php if ($staff['staff_type'] == 'academic'): ?>
                    <a href="assign_batch.php?id=<?php echo $staff['id']; ?>" class="btn btn-primary ms-1">
                        <i class="fas fa-chalkboard me-1"></i> Assign Batch
                    </a>
                    <?php endif; ?>
                    <a href="salary/pay_salary.php?id=<?php echo $staff['id']; ?>" class="btn btn-success ms-1">
                        <i class="fas fa-money-bill-wave me-1"></i> Pay Salary
                    </a>
                    <a href="index.php" class="btn btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['name']); ?></dd>
                            
                            <dt class="col-sm-4">NIC Number</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['nic_number']); ?></dd>
                            
                            <dt class="col-sm-4">Phone Number</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['phone_number']); ?></dd>
                            
                            <dt class="col-sm-4">Address</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['address']); ?></dd>
                        </dl>
                    </div>
                    
                    <!-- Employment Details -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Employment Details</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Staff Type</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($staff['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                    <?php echo ucfirst(str_replace('_', '-', $staff['staff_type'])); ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Job Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($staff['job_status'] == 'permanent') ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($staff['job_status']); ?>
                                </span>
                            </dd>
                            
                            <?php if ($staff['job_status'] == 'training' && !empty($staff['training_end_date'])): ?>
                            <dt class="col-sm-4">Training Ends</dt>
                            <dd class="col-sm-8">
                                <?php echo formatDate($staff['training_end_date']); ?>
                                <?php 
                                $daysLeft = (strtotime($staff['training_end_date']) - time()) / (60 * 60 * 24);
                                if ($daysLeft > 0 && $daysLeft <= 30) {
                                    echo '<span class="badge bg-danger ms-2">'.round($daysLeft).' days left</span>';
                                }
                                ?>
                            </dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-4">Monthly Salary</dt>
                            <dd class="col-sm-8"><?php echo formatCurrency($staff['monthly_salary']); ?></dd>
                            
                            <dt class="col-sm-4">Date Added</dt>
                            <dd class="col-sm-8"><?php echo formatDate($staff['created_at']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($staff['staff_type'] == 'academic' && count($assignedBatches) > 0): ?>
<!-- Assigned Batches -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Assigned Batches</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch Name</th>
                                <th>Course</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
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
                                    <td>
                                        <span class="badge <?php echo ($batch['status'] == 'active') ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($batch['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../batches/view_batch.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View Batch
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Salary Information -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Salary Information</h5>
                <a href="salary/salary_history.php?id=<?php echo $staff['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-history me-1"></i> View Full History
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card bg-primary bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-primary mb-2">Monthly Salary</h6>
                                <h4><?php echo formatCurrency($staff['monthly_salary']); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-success mb-2">Total Paid</h6>
                                <h4><?php echo formatCurrency($totalSalaryPaid); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-2">Payment Status</h6>
                                <?php 
                                $currentMonth = date('Y-m');
                                $isPaid = false;
                                
                                foreach ($salaryHistory as $salary) {
                                    if ($salary['payment_month'] == $currentMonth) {
                                        $isPaid = ($salary['payment_status'] == 'paid');
                                        break;
                                    }
                                }
                                
                                if ($isPaid): 
                                ?>
                                    <h4 class="text-success">
                                        <i class="fas fa-check-circle me-1"></i> Paid for <?php echo date('F Y'); ?>
                                    </h4>
                                <?php else: ?>
                                    <h4 class="text-warning">
                                        <i class="fas fa-clock me-1"></i> Pending for <?php echo date('F Y'); ?>
                                    </h4>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Salary History -->
                <h6 class="border-bottom pb-2 mb-3">Recent Salary Payments</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentSalaries = array_slice($salaryHistory, 0, 5); // Get 5 most recent
                            if (count($recentSalaries) > 0): 
                                foreach ($recentSalaries as $salary): 
                                    $monthYear = date('F Y', strtotime($salary['payment_month'] . '-01'));
                            ?>
                                <tr>
                                    <td><?php echo $monthYear; ?></td>
                                    <td><?php echo formatCurrency($salary['amount']); ?></td>
                                    <td>
                                        <?php 
                                        if ($salary['payment_status'] == 'paid') {
                                            echo formatDate($salary['payment_date']);
                                        } else {
                                            echo 'Pending';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($salary['payment_status'] == 'paid') ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($salary['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($salary['payment_status'] == 'pending'): ?>
                                            <a href="salary/pay_salary.php?id=<?php echo $staff['id']; ?>&month=<?php echo $salary['payment_month']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill-wave me-1"></i> Process Payment
                                            </a>
                                        <?php else: ?>
                                            <a href="salary/view_payment.php?id=<?php echo $salary['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-receipt me-1"></i> View Details
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center">No salary records found</td>
                                </tr>
                            <?php 
                            endif; 
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