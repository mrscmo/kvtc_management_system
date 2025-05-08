<?php
// Set page title
$page_title = "Income Management";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Default to current month if not specified
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get income data
$query = "SELECT i.*, s.student_id as student_number, s.full_name as student_name 
          FROM income i
          LEFT JOIN students s ON i.student_id = s.id
          WHERE i.income_date BETWEEN ? AND ?
          ORDER BY i.income_date DESC, i.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total income for the period
$totalQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalIncome = $totalResult->fetch_assoc()['total'] ?? 0;

// Get income by type
$typeQuery = "SELECT income_type, SUM(amount) as total 
              FROM income 
              WHERE income_date BETWEEN ? AND ?
              GROUP BY income_type";
$stmt = $conn->prepare($typeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$typeResult = $stmt->get_result();

$incomeByType = [];
while ($row = $typeResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Income Overview -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Income Overview (<?php echo formatDate($startDate) . ' - ' . formatDate($endDate); ?>)</h5>
                <div>
                    <a href="new_income.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> Add New Income
                    </a>
                    <a href="../index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Finance
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="start_date" class="col-form-label">From:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-auto">
                                <label for="end_date" class="col-form-label">To:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&export=pdf" class="btn btn-outline-secondary export-pdf">
                            <i class="fas fa-file-pdf me-1"></i> Export as PDF
                        </a>
                    </div>
                </div>
                
                <!-- Income Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4>Total Income:</h4>
                                    <h4 class="text-success"><?php echo formatCurrency($totalIncome); ?></h4>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Income by Type:</h6>
                                    <ul class="list-group mt-2">
                                        <?php foreach ($incomeByType as $type => $amount): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                                <span class="badge bg-success rounded-pill"><?php echo formatCurrency($amount); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Quick Links</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <a href="reports/daily.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-calendar-day me-1"></i> Daily Report
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <a href="reports/weekly.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-calendar-week me-1"></i> Weekly Report
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <a href="reports/monthly.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-calendar-alt me-1"></i> Monthly Report
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <a href="reports/yearly.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-calendar me-1"></i> Yearly Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Income List -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Student</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result->num_rows > 0) {
                                $counter = 1;
                                while ($row = $result->fetch_assoc()) { 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo formatDate($row['income_date']); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $row['income_type'])); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($row['income_name'])) {
                                            echo htmlspecialchars($row['income_name']);
                                        } else {
                                            echo ucwords(str_replace('_', ' ', $row['income_type']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($row['student_id'])) {
                                            echo '<a href="../../students/view_student.php?id=' . $row['student_id'] . '">';
                                            echo htmlspecialchars($row['student_number'] . ' - ' . $row['student_name']);
                                            echo '</a>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo formatCurrency($row['amount']); ?></td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No income records found for the selected period</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th colspan="5" class="text-end">Total:</th>
                                <th><?php echo formatCurrency($totalIncome); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range validation
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && this.value && new Date(this.value) < new Date(startDateInput.value)) {
            alert('End date cannot be before start date');
            this.value = startDateInput.value;
        }
    });
    
    startDateInput.addEventListener('change', function() {
        if (endDateInput.value && this.value && new Date(endDateInput.value) < new Date(this.value)) {
            alert('Start date cannot be after end date');
            endDateInput.value = this.value;
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>