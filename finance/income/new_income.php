<?php
// Set page title
$page_title = "Add New Income";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $income_type = $_POST['income_type'];
    $income_name = !empty($_POST['income_name']) ? trim($_POST['income_name']) : null;
    $amount = floatval($_POST['amount']);
    $income_date = $_POST['income_date'];
    $student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
    
    $errors = [];
    
    if (empty($income_type)) {
        $errors[] = "Income type is required";
    }
    
    if ($income_type === 'other' && empty($income_name)) {
        $errors[] = "Income name is required for other income types";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (empty($income_date)) {
        $errors[] = "Income date is required";
    }
    
    // If no errors, insert the income
    if (empty($errors)) {
        $query = "INSERT INTO income (income_type, income_name, amount, student_id, income_date) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdis", $income_type, $income_name, $amount, $student_id, $income_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Income added successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding income: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get students for dropdown
$studentsQuery = "SELECT id, student_id, full_name FROM students ORDER BY full_name";
$studentsResult = $conn->query($studentsQuery);
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Add Income Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Add New Income</h5>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Income List
                </a>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="income_type" class="form-label">Income Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="income_type" name="income_type" required>
                            <option value="">Select Income Type</option>
                            <option value="admission_fee" <?php echo (isset($_POST['income_type']) && $_POST['income_type'] == 'admission_fee') ? 'selected' : ''; ?>>Admission Fee</option>
                            <option value="course_fee" <?php echo (isset($_POST['income_type']) && $_POST['income_type'] == 'course_fee') ? 'selected' : ''; ?>>Course Fee</option>
                            <option value="other" <?php echo (isset($_POST['income_type']) && $_POST['income_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="income_name_container" style="display: none;">
                        <label for="income_name" class="form-label">Income Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="income_name" name="income_name" value="<?php echo isset($_POST['income_name']) ? htmlspecialchars($_POST['income_name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="income_date" class="form-label">Income Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="income_date" name="income_date" value="<?php echo isset($_POST['income_date']) ? htmlspecialchars($_POST['income_date']) : date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student (Optional)</label>
                        <select class="form-select" id="student_id" name="student_id">
                            <option value="">Select Student</option>
                            <?php while ($student = $studentsResult->fetch_assoc()): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['student_id'] . ' - ' . $student['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Income</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle income name field based on income type
    const incomeTypeSelect = document.getElementById('income_type');
    const incomeNameContainer = document.getElementById('income_name_container');
    
    incomeTypeSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            incomeNameContainer.style.display = 'block';
            document.getElementById('income_name').setAttribute('required', 'required');
        } else {
            incomeNameContainer.style.display = 'none';
            document.getElementById('income_name').removeAttribute('required');
        }
    });
    
    // Trigger the change event on page load
    incomeTypeSelect.dispatchEvent(new Event('change'));
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>