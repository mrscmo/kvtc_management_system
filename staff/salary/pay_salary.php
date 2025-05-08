<?php
// Set page title
$page_title = "Pay Salary";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required";
    header("Location: ../index.php");
    exit();
}

$staff_id = $_GET['id'];

// Get staff details
$staff = getStaffById($staff_id);
if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found";
    header("Location: ../index.php");
    exit();
}

// Determine which month to process
$paymentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Check if salary record exists
$salaryQuery = "SELECT * FROM staff_salary WHERE staff_id = ? AND payment_month = ?";
$stmt = $conn->prepare($salaryQuery);
$stmt->bind_param("is", $staff_id, $paymentMonth);
$stmt->execute();
$salaryResult = $stmt->get_result();
$salaryRecord = $salaryResult->fetch_assoc();

// If no record, create one
if (!$salaryRecord) {
    $insertQuery = "INSERT INTO staff_salary (staff_id, amount, payment_date, payment_month, payment_status) 
                    VALUES (?, ?, CURDATE(), ?, 'pending')";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ids", $staff_id, $staff['monthly_salary'], $paymentMonth);
    $stmt->execute();
    
    // Fetch the newly created record
    $stmt = $conn->prepare($salaryQuery);
    $stmt->bind_param("is", $staff_id, $paymentMonth);
    $stmt->execute();
    $salaryResult = $stmt->get_result();
    $salaryRecord = $salaryResult->fetch_assoc();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only process if salary is still pending
    if ($salaryRecord['payment_status'] == 'pending') {
        $amount = floatval($_POST['amount']);
        $payment_date = $_POST['payment_date'];
        $remarks = trim($_POST['remarks']);
        
        $errors = [];
        
        if (empty($amount) || $amount <= 0) {
            $errors[] = "Valid amount is required";
        }
        
        if (empty($payment_date)) {
            $errors[] = "Payment date is required";
        }
        
        // If no errors, update the salary record and create expense record
        if (empty($errors)) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update the salary record
                $updateQuery = "UPDATE staff_salary SET amount = ?, payment_date = ?, remarks = ?, payment_status = 'paid' 
                                WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("dssi", $amount, $payment_date, $remarks, $salaryRecord['id']);
                $stmt->execute();
                
                // Create expense record if not already exist
                $expenseName = "Salary for " . $staff['name'] . " - " . date('F Y', strtotime($paymentMonth . '-01'));
                
                $checkExpenseQuery = "SELECT id FROM expenses 
                                     WHERE expense_type = 'staff_salary' 
                                     AND expense_name = ?
                                     AND expense_date = ?";
                $stmt = $conn->prepare($checkExpenseQuery);
                $stmt->bind_param("ss", $expenseName, $payment_date);
                $stmt->execute();
                $checkResult = $stmt->get_result();
                
                if ($checkResult->num_rows == 0) {
                    // Create new expense record
                    $expenseQuery = "INSERT INTO expenses (expense_type, expense_name, amount, expense_date) 
                                    VALUES ('staff_salary', ?, ?, ?)";
                    $stmt = $conn->prepare($expenseQuery);
                    $stmt->bind_param("sds", $expenseName, $amount, $payment_date);
                    $stmt->execute();
                } else {
                    // Update existing expense record
                    $expenseId = $checkResult->fetch_assoc()['id'];
                    $updateExpenseQuery = "UPDATE expenses SET amount = ? WHERE id = ?";
                    $stmt = $conn->prepare($updateExpenseQuery);
                    $stmt->bind_param("di", $amount, $expenseId);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success_message'] = "Salary payment processed successfully";
                header("Location: ../view_staff.php?id=$staff_id");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
    } else {
        $_SESSION['error_message'] = "This salary has already been paid";
    }
}

// Format the month for display
$displayMonth = date('F Y', strtotime($paymentMonth . '-01'));
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
                <h5>Process Salary Payment</h5>
                <a href="../view_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Staff
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Staff Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($staff['name']); ?></dd>
                            
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
                        </dl>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Payment Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Monthly Salary</dt>
                            <dd class="col-sm-8"><?php echo formatCurrency($staff['monthly_salary']); ?></dd>
                            
                            <dt class="col-sm-4">Payment Month</dt>
                            <dd class="col-sm-8"><?php echo $displayMonth; ?></dd>
                            
                            <dt class="col-sm-4">Payment Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo ($salaryRecord['payment_status'] == 'paid') ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($salaryRecord['payment_status']); ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
                
                <?php if ($salaryRecord['payment_status'] == 'pending'): ?>
                <!-- Payment Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $staff_id . "&month=" . $paymentMonth); ?>">
                    <div class="border-bottom pb-2 mb-3">Salary Payment Details</div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" value="<?php echo $salaryRecord['amount']; ?>" required>
                        </div>
                        <div class="form-text">This amount will be recorded as an expense.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo isset($salaryRecord['remarks']) ? htmlspecialchars($salaryRecord['remarks']) : ''; ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> This payment will be recorded as an expense under "Staff Salary" category.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-money-bill-wave me-1"></i> Process Payment
                        </button>
                    </div>
                </form>
                
                <?php else: ?>
                <!-- Payment Already Processed -->
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-1"></i> Salary for <?php echo $displayMonth; ?> has already been paid on <?php echo formatDate($salaryRecord['payment_date']); ?>.
                </div>
                
                <dl class="row">
                    <dt class="col-sm-3">Amount Paid</dt>
                    <dd class="col-sm-9"><?php echo formatCurrency($salaryRecord['amount']); ?></dd>
                    
                    <dt class="col-sm-3">Payment Date</dt>
                    <dd class="col-sm-9"><?php echo formatDate($salaryRecord['payment_date']); ?></dd>
                    
                    <?php if (!empty($salaryRecord['remarks'])): ?>
                    <dt class="col-sm-3">Remarks</dt>
                    <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($salaryRecord['remarks'])); ?></dd>
                    <?php endif; ?>
                </dl>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="salary_history.php?id=<?php echo $staff_id; ?>" class="btn btn-primary">
                        <i class="fas fa-history me-1"></i> View Salary History
                    </a>
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