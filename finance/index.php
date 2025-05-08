<?php
// Set page title
$page_title = "Finance Management";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Default to current date range if not specified
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('first day of this month'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('last day of this month'));
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'month';

// Set date range based on report type
if ($reportType == 'day') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
    $reportTitle = "Daily Report - " . formatDate($startDate);
} elseif ($reportType == 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
    $reportTitle = "Weekly Report - Week " . date('W, Y', strtotime($startDate));
} elseif ($reportType == 'month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $reportTitle = "Monthly Report - " . date('F Y', strtotime($startDate));
} elseif ($reportType == 'year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
    $reportTitle = "Yearly Report - " . date('Y', strtotime($startDate));
} else {
    $reportTitle = "Custom Report - " . formatDate($startDate) . " to " . formatDate($endDate);
}

// Get income summary
$incomeSummaryQuery = "SELECT income_type, SUM(amount) as total 
                       FROM income 
                       WHERE income_date BETWEEN ? AND ? 
                       GROUP BY income_type";
$stmt = $conn->prepare($incomeSummaryQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$incomeSummaryResult = $stmt->get_result();

$totalIncome = 0;
$incomeByType = array();

while ($row = $incomeSummaryResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
    $totalIncome += $row['total'];
}

// Get expense summary
$expenseSummaryQuery = "SELECT expense_type, SUM(amount) as total 
                        FROM expenses 
                        WHERE expense_date BETWEEN ? AND ? 
                        GROUP BY expense_type";
$stmt = $conn->prepare($expenseSummaryQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$expenseSummaryResult = $stmt->get_result();

$totalExpense = 0;
$expenseByType = array();

while ($row = $expenseSummaryResult->fetch_assoc()) {
    $expenseByType[$row['expense_type']] = $row['total'];
    $totalExpense += $row['total'];
}

// Calculate profit
$totalProfit = $totalIncome - $totalExpense;

// Get recent transactions
$recentTransactionsQuery = "
    (SELECT 'income' as type, income_type as transaction_type, income_name as description, amount, 
            income_date as transaction_date, student_id 
     FROM income 
     ORDER BY income_date DESC, id DESC LIMIT 5)
    UNION
    (SELECT 'expense' as type, expense_type as transaction_type, expense_name as description, amount, 
            expense_date as transaction_date, student_id 
     FROM expenses 
     ORDER BY expense_date DESC, id DESC LIMIT 5)
    ORDER BY transaction_date DESC, type
    LIMIT 10
";
$recentTransactionsResult = $conn->query($recentTransactionsQuery);

// Get monthly income/expense data for chart
$monthlyDataQuery = "
    SELECT 
        'income' as type,
        MONTH(income_date) as month,
        YEAR(income_date) as year,
        SUM(amount) as total
    FROM income
    WHERE YEAR(income_date) = YEAR(CURDATE())
    GROUP BY YEAR(income_date), MONTH(income_date)
    
    UNION
    
    SELECT 
        'expense' as type,
        MONTH(expense_date) as month,
        YEAR(expense_date) as year,
        SUM(amount) as total
    FROM expenses
    WHERE YEAR(expense_date) = YEAR(CURDATE())
    GROUP BY YEAR(expense_date), MONTH(expense_date)
    
    ORDER BY year, month
";
$monthlyDataResult = $conn->query($monthlyDataQuery);

$monthlyIncome = array_fill(1, 12, 0);
$monthlyExpense = array_fill(1, 12, 0);

while ($row = $monthlyDataResult->fetch_assoc()) {
    if ($row['type'] === 'income') {
        $monthlyIncome[$row['month']] = $row['total'];
    } else {
        $monthlyExpense[$row['month']] = $row['total'];
    }
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Finance Overview -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><?php echo $reportTitle; ?></h5>
                <div>
                    <a href="income/index.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> Add Income
                    </a>
                    <a href="expenses/index.php" class="btn btn-danger ms-2">
                        <i class="fas fa-plus-circle me-1"></i> Add Expense
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Report Selection -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="report_type" class="col-form-label">Report Type:</label>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="report_type" name="report_type" onchange="this.form.submit()">
                                    <option value="day" <?php echo ($reportType == 'day') ? 'selected' : ''; ?>>Daily</option>
                                    <option value="week" <?php echo ($reportType == 'week') ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="month" <?php echo ($reportType == 'month') ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="year" <?php echo ($reportType == 'year') ? 'selected' : ''; ?>>Yearly</option>
                                    <option value="custom" <?php echo ($reportType == 'custom') ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            
                            <div class="col-auto custom-date-range <?php echo ($reportType != 'custom') ? 'd-none' : ''; ?>">
                                <label for="start_date" class="col-form-label">From:</label>
                            </div>
                            <div class="col-auto custom-date-range <?php echo ($reportType != 'custom') ? 'd-none' : ''; ?>">
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-auto custom-date-range <?php echo ($reportType != 'custom') ? 'd-none' : ''; ?>">
                                <label for="end_date" class="col-form-label">To:</label>
                            </div>
                            <div class="col-auto custom-date-range <?php echo ($reportType != 'custom') ? 'd-none' : ''; ?>">
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-auto custom-date-range <?php echo ($reportType != 'custom') ? 'd-none' : ''; ?>">
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
                
                <!-- Financial Summary -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-success bg-opacity-10 h-100">
                            <div class="card-body text-center">
                                <h6 class="text-success mb-3">Total Income</h6>
                                <h3 class="mb-3"><?php echo formatCurrency($totalIncome); ?></h3>
                                <a href="income/index.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm btn-outline-success">View Details</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-danger bg-opacity-10 h-100">
                            <div class="card-body text-center">
                                <h6 class="text-danger mb-3">Total Expenses</h6>
                                <h3 class="mb-3"><?php echo formatCurrency($totalExpense); ?></h3>
                                <a href="expenses/index.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm btn-outline-danger">View Details</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card <?php echo ($totalProfit >= 0) ? 'bg-info bg-opacity-10' : 'bg-warning bg-opacity-10'; ?> h-100">
                            <div class="card-body text-center">
                                <h6 class="<?php echo ($totalProfit >= 0) ? 'text-info' : 'text-warning'; ?> mb-3">Net Profit</h6>
                                <h3 class="mb-3"><?php echo formatCurrency($totalProfit); ?></h3>
                                <a href="profit/index.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm <?php echo ($totalProfit >= 0) ? 'btn-outline-info' : 'btn-outline-warning'; ?>">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart and Recent Transactions -->
<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Financial Overview (<?php echo date('Y'); ?>)</h5>
            </div>
            <div class="card-body">
                <canvas id="financialChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Recent Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentTransactionsResult->num_rows > 0): ?>
                                <?php while ($transaction = $recentTransactionsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                        <td>
                                            <?php if ($transaction['type'] === 'income'): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-arrow-up me-1"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-arrow-down me-1"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No recent transactions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Report Links -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Financial Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Reports</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="income/reports/daily.php" class="text-decoration-none">Daily Income Report</a>
                                        <a href="income/reports/daily.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="income/reports/weekly.php" class="text-decoration-none">Weekly Income Report</a>
                                        <a href="income/reports/weekly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="income/reports/monthly.php" class="text-decoration-none">Monthly Income Report</a>
                                        <a href="income/reports/monthly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="income/reports/yearly.php" class="text-decoration-none">Yearly Income Report</a>
                                        <a href="income/reports/yearly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Expense Reports</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="expenses/reports/daily.php" class="text-decoration-none">Daily Expense Report</a>
                                        <a href="expenses/reports/daily.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="expenses/reports/weekly.php" class="text-decoration-none">Weekly Expense Report</a>
                                        <a href="expenses/reports/weekly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="expenses/reports/monthly.php" class="text-decoration-none">Monthly Expense Report</a>
                                        <a href="expenses/reports/monthly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="expenses/reports/yearly.php" class="text-decoration-none">Yearly Expense Report</a>
                                        <a href="expenses/reports/yearly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Profit Reports</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="profit/daily.php" class="text-decoration-none">Daily Profit Report</a>
                                        <a href="profit/daily.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="profit/weekly.php" class="text-decoration-none">Weekly Profit Report</a>
                                        <a href="profit/weekly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="profit/monthly.php" class="text-decoration-none">Monthly Profit Report</a>
                                        <a href="profit/monthly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="profit/yearly.php" class="text-decoration-none">Yearly Profit Report</a>
                                        <a href="profit/yearly.php" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                </ul>
                            </div>
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
    const ctx = document.getElementById('financialChart').getContext('2d');
    
    // Chart data
    const monthlyIncomeData = <?php echo json_encode(array_values($monthlyIncome)); ?>;
    const monthlyExpenseData = <?php echo json_encode(array_values($monthlyExpense)); ?>;
    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Create the chart
    const financialChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Income',
                    data: monthlyIncomeData,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 1
                },
                {
                    label: 'Expense',
                    data: monthlyExpenseData,
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
    
    // Toggle custom date range fields
    const reportTypeSelect = document.getElementById('report_type');
    const customDateRangeFields = document.querySelectorAll('.custom-date-range');
    
    reportTypeSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customDateRangeFields.forEach(function(field) {
                field.classList.remove('d-none');
            });
        } else {
            customDateRangeFields.forEach(function(field) {
                field.classList.add('d-none');
            });
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>