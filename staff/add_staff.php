<?php
// Set page title
$page_title = "Add Staff";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $nic_number = trim($_POST['nic_number']);
    $phone_number = trim($_POST['phone_number']);
    $staff_type = $_POST['staff_type'];
    $job_status = $_POST['job_status'];
    $training_end_date = ($job_status == 'training' && !empty($_POST['training_end_date'])) ? $_POST['training_end_date'] : null;
    $monthly_salary = floatval($_POST['monthly_salary']);
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($nic_number)) {
        $errors[] = "NIC Number is required";
    } else {
        // Check if NIC already exists
        $checkQuery = "SELECT COUNT(*) as count FROM staff WHERE nic_number = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $nic_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "NIC Number already exists";
        }
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone Number is required";
    }
    
    if ($job_status == 'training' && empty($training_end_date)) {
        $errors[] = "Training end date is required for staff in training";
    }
    
    if (empty($monthly_salary) || $monthly_salary <= 0) {
        $errors[] = "Valid monthly salary is required";
    }
    
    // If no errors, insert the staff
    if (empty($errors)) {
        $query = "INSERT INTO staff (name, address, nic_number, phone_number, staff_type, job_status, training_end_date, monthly_salary) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssd", $name, $address, $nic_number, $phone_number, $staff_type, $job_status, $training_end_date, $monthly_salary);
        
        if ($stmt->execute()) {
            // Get the staff ID
            $staff_id = $conn->insert_id;
            
            // Create initial salary expense record if job status is permanent
            if ($job_status == 'permanent') {
                $currentMonth = date('Y-m');
                $expenseDate = date('Y-m-d');
                $expenseName = "Initial Salary for " . $name . " - " . $currentMonth;
                
                // Create expense record
                $expenseQuery = "INSERT INTO expenses (expense_type, expense_name, amount, expense_date) 
                                VALUES ('staff_salary', ?, ?, ?)";
                $expenseStmt = $conn->prepare($expenseQuery);
                $expenseStmt->bind_param("sds", $expenseName, $monthly_salary, $expenseDate);
                $expenseStmt->execute();
                
                // Also create staff_salary record
                $salaryQuery = "INSERT INTO staff_salary (staff_id, amount, payment_date, payment_month, payment_status) 
                                VALUES (?, ?, ?, ?, 'pending')";
                $salaryStmt = $conn->prepare($salaryQuery);
                $salaryStmt->bind_param("idss", $staff_id, $monthly_salary, $expenseDate, $currentMonth);
                $salaryStmt->execute();
            }
            
            $_SESSION['success_message'] = "Staff member added successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding staff member: " . $conn->error;
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

<!-- Add Staff Form -->
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h5>Add New Staff Member</h5>
            </div>
            <div class="card-body">
                <form id="staffForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">Personal Information</div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nic_number" class="form-label">NIC Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nic_number" name="nic_number" value="<?php echo isset($_POST['nic_number']) ? htmlspecialchars($_POST['nic_number']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment Details Section -->
                    <div class="form-section mt-4">
                        <div class="form-section-title">Employment Details</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="staff_type" class="form-label">Staff Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="staff_type" name="staff_type" required>
                                    <option value="">Select Staff Type</option>
                                    <option value="academic" <?php echo (isset($_POST['staff_type']) && $_POST['staff_type'] == 'academic') ? 'selected' : ''; ?>>Academic</option>
                                    <option value="non_academic" <?php echo (isset($_POST['staff_type']) && $_POST['staff_type'] == 'non_academic') ? 'selected' : ''; ?>>Non-Academic</option>
                                </select>
                                <div class="form-text" id="academicHelp">Academic staff can be assigned to batches for teaching.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="job_status" class="form-label">Job Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="job_status" name="job_status" required>
                                    <option value="">Select Job Status</option>
                                    <option value="training" <?php echo (isset($_POST['job_status']) && $_POST['job_status'] == 'training') ? 'selected' : ''; ?>>Training</option>
                                    <option value="permanent" <?php echo (isset($_POST['job_status']) && $_POST['job_status'] == 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="training_end_date_container" style="display: <?php echo (isset($_POST['job_status']) && $_POST['job_status'] == 'training') ? 'block' : 'none'; ?>;">
                            <label for="training_end_date" class="form-label">Training End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="training_end_date" name="training_end_date" value="<?php echo isset($_POST['training_end_date']) ? htmlspecialchars($_POST['training_end_date']) : ''; ?>">
                            <div class="form-text">A notification will be sent as this date approaches.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monthly_salary" class="form-label">Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="monthly_salary" name="monthly_salary" value="<?php echo isset($_POST['monthly_salary']) ? htmlspecialchars($_POST['monthly_salary']) : ''; ?>" required>
                            </div>
                            <div class="form-text">This amount will be recorded as an expense each month for permanent staff.</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide training end date based on job status
    const jobStatusSelect = document.getElementById('job_status');
    const trainingEndDateContainer = document.getElementById('training_end_date_container');
    const trainingEndDateInput = document.getElementById('training_end_date');
    
    jobStatusSelect.addEventListener('change', function() {
        if (this.value === 'training') {
            trainingEndDateContainer.style.display = 'block';
            trainingEndDateInput.setAttribute('required', 'required');
        } else {
            trainingEndDateContainer.style.display = 'none';
            trainingEndDateInput.removeAttribute('required');
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>