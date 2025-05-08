<?php
// Set page title
$page_title = "Daily Expenses Report";

// Include header
include_once __DIR__ . '/../../../includes/header.php';
include_once __DIR__ . '/../../../includes/functions.php';

// Default to current date if not specified
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get expenses data for the day
$query = "SELECT e.*, s.student_id as student_number, s.full_name as student_name 
          FROM expenses e
          LEFT JOIN students s ON e.student_id = s.id
          WHERE DATE(e.expense_date) = ?
          ORDER BY e.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total expenses for the day
$totalQuery = "SELECT SUM(amount) as total FROM expenses WHERE DATE(expense_date) = ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalExpenses = $totalResult->fetch_assoc()['total'] ?? 0;

// Get expenses by type
$typeQuery = "SELECT expense_type, SUM(amount) as total 
              FROM expenses 
              WHERE DATE(expense_date) = ?
              GROUP BY expense_type";
$stmt = $conn->prepare($typeQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$typeResult = $stmt->get_result();

$expensesByType = [];
while ($row = $typeResult->fetch_assoc()) {
    $expensesByType[$row['expense_type']] = $row['total'];
}

// Get hourly expenses data for chart
$hourlyQuery = "SELECT HOUR(expense_date) as hour, SUM(amount) as total
               FROM expenses
               WHERE DATE(expense_date) = ?
               GROUP BY HOUR(expense_date)
               ORDER BY HOUR(expense_date)";
$stmt = $conn->prepare($hourlyQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$hourlyResult = $stmt->get_result();

$hourlyData = array_fill(0, 24, 0); // Initialize with 0 for all 24 hours
while ($row = $hourlyResult->fetch_assoc()) {
    $hourlyData[$row['hour']] = $row['total'];
}

// Get income for the same day for comparison
$incomeQuery = "SELECT SUM(amount) as total FROM income WHERE DATE(income_date) = ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$incomeResult = $stmt->get_result();
$dailyIncome = $incomeResult->fetch_assoc()['total'] ?? 0;

// Calculate profit/loss
$profit = $dailyIncome - $totalExpenses;

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="daily_expenses_report_' . $date . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Daily Expenses Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Daily Expenses Report - <?php echo formatDate($date); ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Expenses
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Date Selector -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="date" class="col-form-label">Select Date:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">View</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=pdf'; ?>" class="btn btn-outline-secondary export-pdf">
                            <i class="fas fa-file-pdf me-1"></i> Export as PDF
                        </a>
                    </div>
                </div>
                
                <!-- Expenses Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Daily Expenses Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4>Total Expenses:</h4>
                                    <h4 class="text-danger"><?php echo formatCurrency($totalExpenses); ?></h4>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Expenses by Type:</h6>
                                    <?php if (count($expensesByType) > 0): ?>
                                        <ul class="list-group mt-2">
                                            <?php foreach ($expensesByType as $type => $amount): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                                    <span class="badge bg-danger rounded-pill"><?php echo formatCurrency($amount); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted text-center mt-3">No expenses recorded for this day</p>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Daily Financial Summary:</h6>
                                    <table class="table table-bordered mt-2">
                                        <tr>
                                            <td>Total Income:</td>
                                            <td class="text-end text-success"><?php echo formatCurrency($dailyIncome); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Total Expenses:</td>
                                            <td class="text-end text-danger"><?php echo formatCurrency($totalExpenses); ?></td>
                                        </tr>
                                        <tr class="<?php echo $profit >= 0 ? 'table-success' : 'table-danger'; ?>">
                                            <th>Net Profit/Loss:</th>
                                            <th class="text-end <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($profit); ?>
                                            </th>
                                        </tr>
                                    </table>
                                    
                                    <div class="text-center mt-3">
                                        <a href="../../income/reports/daily.php?date=<?php echo $date; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-exchange-alt me-1"></i> View Income Report for This Day
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Hourly Expenses Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="hourlyExpensesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expenses List -->
                <h6 class="border-bottom pb-2 mb-3">Expenses Details</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
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
                                    <td><?php echo date('h:i A', strtotime($row['expense_date'])); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $row['expense_type'])); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($row['expense_name'])) {
                                            echo htmlspecialchars($row['expense_name']);
                                        } else {
                                            echo ucwords(str_replace('_', ' ', $row['expense_type']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($row['student_id'])) {
                                            echo '<a href="../../../students/view_student.php?id=' . $row['student_id'] . '">';
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
                                    <td colspan="6" class="text-center">No expense records found for the selected date</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <th colspan="5" class="text-end">Total:</th>
                                <th><?php echo formatCurrency($totalExpenses); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Quick Navigation -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?date=' . date('Y-m-d', strtotime($date . ' -1 day'))); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left me-1"></i> Previous Day
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?date=' . date('Y-m-d')); ?>" class="btn btn-outline-secondary">
                                Today
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?date=' . date('Y-m-d', strtotime($date . ' +1 day'))); ?>" class="btn btn-outline-primary">
                                Next Day <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Get the canvas element
    const ctx = document.getElementById('hourlyExpensesChart').getContext('2d');
    
    // Chart data
    const hourlyData = <?php echo json_encode(array_values($hourlyData)); ?>;
    const labels = [];
    
    // Create labels for 24 hours
    for (let i = 0; i < 24; i++) {
        let hour = i;
        let meridiem = 'AM';
        
        if (hour === 0) {
            hour = 12;
        } else if (hour === 12) {
            meridiem = 'PM';
        } else if (hour > 12) {
            hour = hour - 12;
            meridiem = 'PM';
        }
        
        labels.push(hour + ' ' + meridiem);
    }
    
    // Create the chart
    const hourlyExpensesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Hourly Expenses',
                    data: hourlyData,
                    backgroundColor: 'rgba(220, 53, 69, 0.5)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rs. ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Auto-submit form on date change
    document.getElementById('date').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../../includes/footer.php';
?>