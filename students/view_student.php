<?php
// Set page title
$page_title = "View Student";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Student ID is required";
    header("Location: index.php");
    exit();
}

$student_id = $_GET['id'];

// Handle payment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $fee_type = $_POST['fee_type'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $installment_number = null;
    $fee_name = null;
    
    // For installment payments
    if ($fee_type === 'course_fee' && isset($_POST['installment_number'])) {
        $installment_number = intval($_POST['installment_number']);
    }
    
    // For other fee types
    if ($fee_type === 'other_fee' && isset($_POST['fee_name'])) {
        $fee_name = trim($_POST['fee_name']);
    }
    
    $errors = [];
    
    if (empty($fee_type)) {
        $errors[] = "Fee type is required";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (empty($payment_date)) {
        $errors[] = "Payment date is required";
    }
    
    if ($fee_type === 'other_fee' && empty($fee_name)) {
        $errors[] = "Fee name is required for other fees";
    }
    
    // If no errors, insert the payment
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert into fees table
            $query = "INSERT INTO fees (student_id, fee_type, fee_name, amount, payment_date, installment_number) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issdsi", $student_id, $fee_type, $fee_name, $amount, $payment_date, $installment_number);
            $stmt->execute();
            
            // Insert into income table
            $income_type = ($fee_type === 'registration_fee') ? 'admission_fee' : ($fee_type === 'course_fee' ? 'course_fee' : 'other');
            $income_name = ($fee_type === 'other_fee') ? $fee_name : $fee_type;
            
            $query = "INSERT INTO income (income_type, income_name, amount, student_id, income_date) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdis", $income_type, $income_name, $amount, $student_id, $payment_date);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Payment added successfully";
            header("Location: view_student.php?id=$student_id");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Error adding payment: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Handle employment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employment'])) {
    $employment_type = $_POST['employment_type'];
    $company_name = trim($_POST['company_name']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    
    $errors = [];
    
    if (empty($employment_type)) {
        $errors[] = "Employment type is required";
    }
    
    if (empty($company_name)) {
        $errors[] = "Company name is required";
    }
    
    if (!empty($start_date) && !empty($end_date) && $end_date < $start_date) {
        $errors[] = "End date cannot be before start date";
    }
    
    // If no errors, insert the employment record
    if (empty($errors)) {
        $query = "INSERT INTO employment (student_id, type, company_name, start_date, end_date) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issss", $student_id, $employment_type, $company_name, $start_date, $end_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Employment record added successfully";
            header("Location: view_student.php?id=$student_id");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding employment record: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Handle certification form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_certification'])) {
    $completion_status = $_POST['completion_status'];
    $certification_type = null;
    $nvq_level = null;
    
    if ($completion_status === 'yes') {
        $certification_type = $_POST['certification_type'];
        if ($certification_type === 'nvq') {
            $nvq_level = $_POST['nvq_level'];
        }
    }
    
    $query = "UPDATE certifications SET completion_status = ?, certification_type = ?, nvq_level = ? 
              WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $completion_status, $certification_type, $nvq_level, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Certification information updated successfully";
        header("Location: view_student.php?id=$student_id");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating certification information: " . $conn->error;
    }
}

// Get student details
$query = "SELECT s.*, c.course_name, c.duration, c.course_fee, b.batch_name, b.installments 
          FROM students s
          JOIN courses c ON s.course_id = c.id
          JOIN batches b ON s.batch_id = b.id
          WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Student not found";
    header("Location: index.php");
    exit();
}

$student = $result->fetch_assoc();

// Get certification information
$certificationQuery = "SELECT * FROM certifications WHERE student_id = ?";
$stmt = $conn->prepare($certificationQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$certificationResult = $stmt->get_result();
$certification = $certificationResult->fetch_assoc();

// Get employment information
$employmentQuery = "SELECT * FROM employment WHERE student_id = ? ORDER BY type, start_date DESC";
$stmt = $conn->prepare($employmentQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$employmentResult = $stmt->get_result();

// Get fee payments
$feeQuery = "SELECT * FROM fees WHERE student_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($feeQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$feeResult = $stmt->get_result();

// Calculate fee statistics
$totalCourseFee = $student['course_fee'];
$paidCourseFee = getFeePaidAmount($student_id, 'course_fee');
$remainingCourseFee = $totalCourseFee - $paidCourseFee;

$paidRegistrationFee = getFeePaidAmount($student_id, 'registration_fee');
$paidExamFee = getFeePaidAmount($student_id, 'exam_fee');
$paidFinalAssessmentFee = getFeePaidAmount($student_id, 'final_assessment_fee');
$paidOtherFees = getFeePaidAmount($student_id, 'other_fee');

$totalPaid = $paidCourseFee + $paidRegistrationFee + $paidExamFee + $paidFinalAssessmentFee + $paidOtherFees;
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Student Information -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Student Information</h5>
                <div>
                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-sm btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                        <dl class="row student-info">
                            <dt class="col-sm-4">Student ID</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['student_id']); ?></dd>
                            
                            <dt class="col-sm-4">Full Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['full_name']); ?></dd>
                            
                            <dt class="col-sm-4">Registration Date</dt>
                            <dd class="col-sm-8"><?php echo formatDate($student['registration_date']); ?></dd>
                            
                            <dt class="col-sm-4">Contact Number</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['contact_number']); ?></dd>
                            
                            <dt class="col-sm-4">Address</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['address']); ?></dd>
                        </dl>
                    </div>
                    
                    <!-- Course Details -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Course Details</h6>
                        <dl class="row student-info">
                            <dt class="col-sm-4">Course</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['course_name']); ?></dd>
                            
                            <dt class="col-sm-4">Batch</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['batch_name']); ?></dd>
                            
                            <dt class="col-sm-4">Duration</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($student['duration']); ?></dd>
                            
                            <dt class="col-sm-4">Course Fee</dt>
                            <dd class="col-sm-8"><?php echo formatCurrency($student['course_fee']); ?></dd>
                            
                            <dt class="col-sm-4">Installments</dt>
                            <dd class="col-sm-8"><?php echo $student['installments']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Certification Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Certificate of Eligibility</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $student_id); ?>">
                    <div class="mb-3">
                        <label class="form-label">Completion of Course</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="completion_status" id="completion_no" value="no" <?php echo (!$certification || $certification['completion_status'] === 'no') ? 'checked' : ''; ?> onchange="toggleCertificationOptions()">
                            <label class="form-check-label" for="completion_no">No</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="completion_status" id="completion_yes" value="yes" <?php echo ($certification && $certification['completion_status'] === 'yes') ? 'checked' : ''; ?> onchange="toggleCertificationOptions()">
                            <label class="form-check-label" for="completion_yes">Yes</label>
                        </div>
                    </div>
                    
                    <div id="certification_options" class="<?php echo (!$certification || $certification['completion_status'] !== 'yes') ? 'd-none' : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label">Certification Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="certification_type" id="cert_non_nvq" value="non_nvq" <?php echo ($certification && $certification['certification_type'] === 'non_nvq') ? 'checked' : ''; ?> onchange="toggleNvqLevel()">
                                <label class="form-check-label" for="cert_non_nvq">Non-NVQ</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="certification_type" id="cert_nvq" value="nvq" <?php echo ($certification && $certification['certification_type'] === 'nvq') ? 'checked' : ''; ?> onchange="toggleNvqLevel()">
                                <label class="form-check-label" for="cert_nvq">NVQ</label>
                            </div>
                        </div>
                        
                        <div id="nvq_level_options" class="mb-3 <?php echo (!$certification || $certification['certification_type'] !== 'nvq') ? 'd-none' : ''; ?>">
                            <label class="form-label">NVQ Level</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="nvq_level" id="level_03" value="level_03" <?php echo ($certification && $certification['nvq_level'] === 'level_03') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="level_03">Level 03</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="nvq_level" id="level_04" value="level_04" <?php echo ($certification && $certification['nvq_level'] === 'level_04') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="level_04">Level 04</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" name="update_certification" class="btn btn-primary">Update Certification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Employment Information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Employment Information</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEmploymentModal">
                    <i class="fas fa-plus-circle me-1"></i> Add
                </button>
            </div>
            <div class="card-body">
                <?php if ($employmentResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Company</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($employment = $employmentResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo ($employment['type'] === 'internship') ? 'Internship Place' : 'Current Workplace';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($employment['company_name']); ?></td>
                                        <td><?php echo $employment['start_date'] ? formatDate($employment['start_date']) : '-'; ?></td>
                                        <td><?php echo $employment['end_date'] ? formatDate($employment['end_date']) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No employment information available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Information -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Payment Information</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addPaymentBtn">
                    <i class="fas fa-plus-circle me-1"></i> Add Payment
                </button>
            </div>
            <div class="card-body">
                <!-- Payment Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Course Fee Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">Total Course Fee:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($totalCourseFee); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">Paid Course Fee:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($paidCourseFee); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">Remaining Course Fee:</div>
                                    <div class="col-md-4 text-end fw-bold"><?php echo formatCurrency($remainingCourseFee); ?></div>
                                </div>
                                
                                <?php if ($student['installments'] > 1 && $remainingCourseFee > 0): ?>
                                    <div class="alert alert-info mt-3 mb-0">
                                        <small>
                                            <strong>Installment Information:</strong><br>
                                            Installments: <?php echo $student['installments']; ?><br>
                                            Amount per installment: <?php echo formatCurrency($totalCourseFee / $student['installments']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Other Fees Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">Registration Fee:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($paidRegistrationFee); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">Exam Fee:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($paidExamFee); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">Final Assessment Fee:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($paidFinalAssessmentFee); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">Other Fees:</div>
                                    <div class="col-md-4 text-end"><?php echo formatCurrency($paidOtherFees); ?></div>
                                </div>
                                <div class="row mt-2 pt-2 border-top">
                                    <div class="col-md-8">Total Paid (All Fees):</div>
                                    <div class="col-md-4 text-end fw-bold"><?php echo formatCurrency($totalPaid); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Form -->
                <div id="paymentForm" class="mt-4 mb-4 p-3 border rounded bg-light d-none">
                    <h6 class="mb-3">Add New Payment</h6>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $student_id); ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="fee_type" class="form-label">Fee Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="fee_type" name="fee_type" required>
                                    <option value="">Select Fee Type</option>
                                    <option value="course_fee">Course Fee</option>
                                    <option value="registration_fee">Registration Fee</option>
                                    <option value="exam_fee">Exam Fee</option>
                                    <option value="final_assessment_fee">Final Assessment Fee</option>
                                    <option value="other_fee">Other Fee</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3 d-none" id="installment_container">
                                <label for="installment_number" class="form-label">Installment Number</label>
                                <select class="form-select" id="installment_number" name="installment_number">
                                    <?php for ($i = 1; $i <= $student['installments']; $i++): ?>
                                        <option value="<?php echo $i; ?>">Installment <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3 d-none" id="other_fee_name_container">
                                <label for="fee_name" class="form-label">Fee Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fee_name" name="fee_name">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-secondary" onclick="hidePaymentForm()">Cancel</button>
                            <button type="submit" name="add_payment" class="btn btn-primary">Save Payment</button>
                        </div>
                    </form>
                </div>
                
                <!-- Payment History -->
                <h6 class="border-bottom pb-2 mb-3">Payment History</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Fee Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($feeResult->num_rows > 0): ?>
                                <?php while ($fee = $feeResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo formatDate($fee['payment_date']); ?></td>
                                        <td>
                                            <?php 
                                            echo ucwords(str_replace('_', ' ', $fee['fee_type']));
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($fee['fee_type'] === 'course_fee' && !empty($fee['installment_number'])) {
                                                echo "Installment " . $fee['installment_number'];
                                            } elseif ($fee['fee_type'] === 'other_fee' && !empty($fee['fee_name'])) {
                                                echo htmlspecialchars($fee['fee_name']);
                                            } else {
                                                echo "-";
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatCurrency($fee['amount']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No payment records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Employment Modal -->
<div class="modal fade" id="addEmploymentModal" tabindex="-1" aria-labelledby="addEmploymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEmploymentModalLabel">Add Employment Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $student_id); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employment_type" class="form-label">Employment Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="employment_type" name="employment_type" required>
                            <option value="">Select Type</option>
                            <option value="internship">Internship Place</option>
                            <option value="current_workplace">Current Workplace</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="company_name" name="company_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_employment" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle certification options based on completion status
function toggleCertificationOptions() {
    const completionYes = document.getElementById('completion_yes');
    const certificationOptions = document.getElementById('certification_options');
    
    if (completionYes.checked) {
        certificationOptions.classList.remove('d-none');
    } else {
        certificationOptions.classList.add('d-none');
    }
}

// Toggle NVQ level options based on certification type
function toggleNvqLevel() {
    const certNvq = document.getElementById('cert_nvq');
    const nvqLevelOptions = document.getElementById('nvq_level_options');
    
    if (certNvq.checked) {
        nvqLevelOptions.classList.remove('d-none');
    } else {
        nvqLevelOptions.classList.add('d-none');
    }
}

// Show/hide payment form
const addPaymentBtn = document.getElementById('addPaymentBtn');
const paymentForm = document.getElementById('paymentForm');

addPaymentBtn.addEventListener('click', function() {
    paymentForm.classList.remove('d-none');
});

function hidePaymentForm() {
    paymentForm.classList.add('d-none');
}

// Handle fee type change
const feeTypeSelect = document.getElementById('fee_type');
const installmentContainer = document.getElementById('installment_container');
const otherFeeNameContainer = document.getElementById('other_fee_name_container');

feeTypeSelect.addEventListener('change', function() {
    if (this.value === 'course_fee') {
        installmentContainer.classList.remove('d-none');
        otherFeeNameContainer.classList.add('d-none');
    } else if (this.value === 'other_fee') {
        otherFeeNameContainer.classList.remove('d-none');
        installmentContainer.classList.add('d-none');
    } else {
        installmentContainer.classList.add('d-none');
        otherFeeNameContainer.classList.add('d-none');
    }
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>