<?php
// Set page title
$page_title = "Edit Staff";

// Include header and functions
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required";
    header("Location: index.php");
    exit();
}

$staff_id = intval($_GET['id']); // Cast to integer for safety

// Get staff details
$staff = getStaffById($staff_id);

if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found";
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $nic_number = trim($_POST['nic_number']);
    $phone_number = trim($_POST['phone_number']);
    $staff_type = $_POST['staff_type'];
    $job_status = $_POST['job_status'];
    $training_end_date = NULL;

    if ($job_status == 'training' && !empty($_POST['training_end_date'])) {
        $training_end_date = $_POST['training_end_date'];
    }

    $monthly_salary = floatval($_POST['monthly_salary']);
    
    $errors = [];

    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    if (empty($nic_number)) {
        $errors[] = "NIC number is required";
    } else {
        // Check if NIC already exists (excluding current staff)
        $checkQuery = "SELECT COUNT(*) as count FROM staff WHERE nic_number = ? AND id != ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("si", $nic_number, $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "NIC number already exists in the system";
        }
    }
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    }
    if ($job_status == 'training' && empty($training_end_date)) {
        $errors[] = "Training end date is required for staff in training";
    }
    if (empty($monthly_salary) || $monthly_salary <= 0) {
        $errors[] = "Valid monthly salary is required";
    }

    // If no errors, update staff
    if (empty($errors)) {
        $query = "UPDATE staff 
                  SET name = ?, address = ?, nic_number = ?, phone_number = ?, 
                      staff_type = ?, job_status = ?, training_end_date = ?, monthly_salary = ? 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssdi", 
            $name, 
            $address, 
            $nic_number, 
            $phone_number, 
            $staff_type, 
            $job_status, 
            $training_end_date, 
            $monthly_salary, 
            $staff_id
        );

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Staff member updated successfully";
            header("Location: view_staff.php?id=$staff_id");
            exit();
        } else {
            $_SESSION['error_message'] = "Error updating staff member: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}
?>

<!-- Display Messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Edit Staff Form -->
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Edit Staff Member</h5>
                <a href="view_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Staff Details
                </a>
            </div>
            <div class="card-body">
                <form id="staffForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $staff_id; ?>">
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="form-section-title">Personal Information</div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($staff['address']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nic_number" class="form-label">NIC Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nic_number" name="nic_number" value="<?php echo htmlspecialchars($staff['nic_number']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($staff['phone_number']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details -->
                    <div class="form-section mt-4">
                        <div class="form-section-title">Employment Details</div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="staff_type" class="form-label">Staff Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="staff_type" name="staff_type" required>
                                    <option value="">Select Staff Type</option>
                                    <option value="academic" <?php echo ($staff['staff_type'] == 'academic') ? 'selected' : ''; ?>>Academic</option>
                                    <option value="non_academic" <?php echo ($staff['staff_type'] == 'non_academic') ? 'selected' : ''; ?>>Non-Academic</option>
                                </select>
                                <div class="form-text text-info">Academic staff can be assigned to teach batches.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="job_status" class="form-label">Job Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="job_status" name="job_status" required onchange="toggleTrainingEndDate()">
                                    <option value="">Select Job Status</option>
                                    <option value="training" <?php echo ($staff['job_status'] == 'training') ? 'selected' : ''; ?>>Training</option>
                                    <option value="permanent" <?php echo ($staff['job_status'] == 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3" id="training_end_date_container" style="<?php echo ($staff['job_status'] == 'training') ? '' : 'display:none;'; ?>">
                                <label for="training_end_date" class="form-label">Training End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="training_end_date" name="training_end_date" value="<?php echo htmlspecialchars($staff['training_end_date']); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="monthly_salary" class="form-label">Monthly Salary (Rs.) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" min="0" step="0.01" value="<?php echo htmlspecialchars($staff['monthly_salary']); ?>" required>
                            </div>
                        </div>

                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Update Staff Member</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to show/hide training end date -->
<script>
function toggleTrainingEndDate() {
    const jobStatus = document.getElementById('job_status').value;
    const trainingEndDateContainer = document.getElementById('training_end_date_container');
    const trainingEndDateInput = document.getElementById('training_end_date');

    if (jobStatus === 'training') {
        trainingEndDateContainer.style.display = 'block';
    } else {
        trainingEndDateContainer.style.display = 'none';
        trainingEndDateInput.value = ''; // Clear training end date if not training
    }
}
</script>
