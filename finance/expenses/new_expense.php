<?php
// Set page title
$page_title = "Add New Expense";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $expense_type = $_POST['expense_type'];
    $expense_name = !empty($_POST['expense_name']) ? trim($_POST['expense_name']) : null;
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];
    $student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
    
    $errors = [];
    
    if (empty($expense_type)) {
        $errors[] = "Expense type is required";
    }
    
    if ($expense_type === 'other' && empty($expense_name)) {
        $errors[] = "Expense name is required for other expense types";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (empty($expense_date)) {
        $errors[] = "Expense date is required";
    }
    
    // If no errors, insert the expense
    if (empty($errors)) {
        $query = "INSERT INTO expenses (expense_type, expense_name, amount, student_id, expense_date) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdis", $expense_type, $expense_name, $amount, $student_id, $expense_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Expense added successfully";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error adding expense: " . $conn->error;
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

<!-- Add Expense Form -->
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Add New Expense</h5>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Expenses List
                </a>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-3">
                        <label for="expense_type" class="form-label">Expense Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="expense_type" name="expense_type" required>
                            <option value="">Select Expense Type</option>
                            <option value="exam_fee" <?php echo (isset($_POST['expense_type']) && $_POST['expense_type'] == 'exam_fee') ? 'selected' : ''; ?>>Exam Fee</option>
                            <option value="final_assessment_fee" <?php echo (isset($_POST['expense_type']) && $_POST['expense_type'] == 'final_assessment_fee') ? 'selected' : ''; ?>>Final Assessment Fee</option>
                            <option value="other" <?php echo (isset($_POST['expense_type']) && $_POST['expense_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="expense_name_container" style="display: none;">
                        <label for="expense_name" class="form-label">Expense Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="expense_name" name="expense_name" value="<?php echo isset($_POST['expense_name']) ? htmlspecialchars($_POST['expense_name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_date" class="form-label">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo isset($_POST['expense_date']) ? htmlspecialchars($_POST['expense_date']) : date('Y-m-d'); ?>" required>
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
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle expense name field based on expense type
    const expenseTypeSelect = document.getElementById('expense_type');
    const expenseNameContainer = document.getElementById('expense_name_container');
    
    expenseTypeSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            expenseNameContainer.style.display = 'block';
            document.getElementById('expense_name').setAttribute('required', 'required');
        } else {
            expenseNameContainer.style.display = 'none';
            document.getElementById('expense_name').removeAttribute('required');
        }
    });
    
    // Trigger the change event on page load
    expenseTypeSelect.dispatchEvent(new Event('change'));
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>