<?php
// Set page title
$page_title = "View Salary Payment";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Payment ID is required";
    header("Location: index.php");
    exit();
}

$payment_id = $_GET['id'];

// Get payment details
$query = "SELECT ss.*, s.name as staff_name, s.nic_number, s.staff_type, s.job_status, s.monthly_salary 
          FROM staff_salary ss
          JOIN staff s ON ss.staff_id = s.id
          WHERE ss.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Payment record not found";
    header("Location: index.php");
    exit();
}

$payment = $result->fetch_assoc();
$staff_id = $payment['staff_id'];

// Format month for display
$monthYear = date('F Y', strtotime($payment['payment_month'] . '-01'));

// Get corresponding expense record if payment is complete
$expenseQuery = "SELECT * FROM expenses 
                 WHERE expense_type = 'staff_salary' 
                 AND expense_name LIKE ? 
                 AND expense_date BETWEEN ? AND ?";
$expenseName = "%" . $payment['staff_name'] . "%" . $payment['payment_month'] . "%";
$monthStart = $payment['payment_month'] . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$expenseStmt = $conn->prepare($expenseQuery);
$expenseStmt->bind_param("sss", $expenseName, $monthStart, $monthEnd);
$expenseStmt->execute();
$expenseResult = $expenseStmt->get_result();
$expenseRecord = $expenseResult->fetch_assoc();
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Salary Payment Details -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Salary Payment Details</h5>
                <div>
                    <?php if ($payment['payment_status'] == 'pending'): ?>
                    <a href="pay_salary.php?id=<?php echo $staff_id; ?>&payment_id=<?php echo $payment_id; ?>" class="btn btn-success">
                        <i class="fas fa-money-bill-wave me-1"></i> Process Payment
                    </a>
                    <?php endif; ?>
                    <a href="salary_history.php?id=<?php echo $staff_id; ?>" class="btn btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back to Salary History
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Payment Information -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Payment Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Payment ID</dt>
                            <dd class="col-sm-8">#<?php echo str_pad($payment['id'], 5, '0', STR_PAD_LEFT); ?></dd>
                            
                            <dt class="col-sm-4">Payment Month</dt>
                            <dd class="col-sm-8"><?php echo $monthYear; ?></dd>
                            
                            <dt class="col-sm-4">Amount</dt>
                            <dd class="col-sm-8"><?php echo formatCurrency($payment['amount']); ?></dd>
                            
                            <dt class="col-sm-4">Payment Date</dt>
                            <dd class="col-sm-8">
                                <?php 
                                if ($payment['payment_status'] == 'paid') {
                                    echo formatDate($payment['payment_date']);
                                } else {
                                    echo '<span class="text-muted">Not paid yet</span>';
                                }
                                ?>
                            </dd>
                            
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($payment['payment_status'] == 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Created On</dt>
                            <dd class="col-sm-8"><?php echo formatDate($payment['created_at']); ?></dd>
                        </dl>
                    </div>
                    
                    <!-- Staff Information -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Staff Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Staff Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($payment['staff_name']); ?></dd>
                            
                            <dt class="col-sm-4">NIC Number</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($payment['nic_number']); ?></dd>
                            
                            <dt class="col-sm-4">Staff Type</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($payment['staff_type'] == 'academic') ? 'bg-primary' : 'bg-secondary'; ?>">
                                    <?php echo ucfirst(str_replace('_', '-', $payment['staff_type'])); ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Job Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($payment['job_status'] == 'permanent') ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($payment['job_status']); ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Monthly Salary</dt>
                            <dd class="col-sm-8"><?php echo formatCurrency($payment['monthly_salary']); ?></dd>
                        </dl>
                    </div>
                </div>
                
                <?php if (!empty($payment['remarks'])): ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2 mb-3">Remarks</h6>
                        <p><?php echo htmlspecialchars($payment['remarks']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($payment['payment_status'] == 'paid' && $expenseRecord): ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="border-bottom pb-2 mb-3">Finance Record</h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This payment has been recorded in the <a href="../../finance/expenses/index.php" class="alert-link">expenses section</a> as:
                            <br>
                            <strong><?php echo htmlspecialchars($expenseRecord['expense_name']); ?></strong>
                            <br>
                            Amount: <strong><?php echo formatCurrency($expenseRecord['amount']); ?></strong>
                            <br>
                            Date: <strong><?php echo formatDate($expenseRecord['expense_date']); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>