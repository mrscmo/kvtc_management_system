<?php
// Set page title
$page_title = "Daily Profit Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Default to current date if not specified
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get income data for the day
$incomeQuery = "SELECT SUM(amount) as total, 
                COUNT(*) as count,
                MIN(amount) as min_transaction,
                MAX(amount) as max_transaction
                FROM income 
                WHERE DATE(income_date) = ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$incomeResult = $stmt->get_result();
$incomeData = $incomeResult->fetch_assoc();
$dailyIncome = $incomeData['total'] ?? 0;
$incomeTransactions = $incomeData['count'] ?? 0;
$minIncomeTransaction = $incomeData['min_transaction'] ?? 0;
$maxIncomeTransaction = $incomeData['max_transaction'] ?? 0;

// Get income by type
$incomeTypeQuery = "SELECT income_type, SUM(amount) as total 
                    FROM income 
                    WHERE DATE(income_date) = ?
                    GROUP BY income_type";
$stmt = $conn->prepare($incomeTypeQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$incomeTypeResult = $stmt->get_result();

$incomeByType = [];
while ($row = $incomeTypeResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
}

// Get expenses data for the day
$expenseQuery = "SELECT SUM(amount) as total,
                 COUNT(*) as count,
                 MIN(amount) as min_transaction,
                 MAX(amount) as max_transaction
                 FROM expenses 
                 WHERE DATE(expense_date) = ?";
$stmt = $conn->prepare($expenseQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$expenseResult = $stmt->get_result();
$expenseData = $expenseResult->fetch_assoc();
$dailyExpense = $expenseData['total'] ?? 0;
$expenseTransactions = $expenseData['count'] ?? 0;
$minExpenseTransaction = $expenseData['min_transaction'] ?? 0;
$maxExpenseTransaction = $expenseData['max_transaction'] ?? 0;

// Get expenses by type
$expenseTypeQuery = "SELECT expense_type, SUM(amount) as total 
                     FROM expenses 
                     WHERE DATE(expense_date) = ?
                     GROUP BY expense_type";
$stmt = $conn->prepare($expenseTypeQuery);
$stmt->bind_param("s", $date);
$stmt->execute();
$expenseTypeResult = $stmt->get_result();

$expensesByType = [];
while ($row = $expenseTypeResult->fetch_assoc()) {
    $expensesByType[$row['expense_type']] = $row['total'];
}

// Calculate profit/loss
$profit = $dailyIncome - $dailyExpense;
$profitMargin = ($dailyIncome > 0) ? ($profit / $dailyIncome) * 100 : 0;

// Get hourly financial data for chart
$hourlyQuery = "SELECT 
                    HOUR(t.transaction_time) as hour, 
                    SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income,
                    SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expense,
                    SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END) as profit
                FROM (
                    SELECT income_date as transaction_time, 'income' as transaction_type, amount FROM income WHERE DATE(income_date) = ?
                    UNION ALL
                    SELECT expense_date as transaction_time, 'expense' as transaction_type, amount FROM expenses WHERE DATE(expense_date) = ?
                ) t
                GROUP BY HOUR(t.transaction_time)
                ORDER BY HOUR(t.transaction_time)";
$stmt = $conn->prepare($hourlyQuery);
$stmt->bind_param("ss", $date, $date);
$stmt->execute();
$hourlyResult = $stmt->get_result();

$hourlyData = [];
for ($i = 0; $i < 24; $i++) {
    $hourlyData[$i] = [
        'income' => 0,
        'expense' => 0,
        'profit' => 0
    ];
}

while ($row = $hourlyResult->fetch_assoc()) {
    $hourlyData[$row['hour']] = [
        'income' => $row['income'],
        'expense' => $row['expense'],
        'profit' => $row['profit']
    ];
}

// Get all transactions for the day
$transactionsQuery = "SELECT * FROM (
                        SELECT 'income' as type, income_date as transaction_date, income_type as transaction_type, 
                               income_name as description, amount, student_id
                        FROM income
                        WHERE DATE(income_date) = ?
                        UNION ALL
                        SELECT 'expense' as type, expense_date as transaction_date, expense_type as transaction_type,
                               expense_name as description, amount, student_id
                        FROM expenses
                        WHERE DATE(expense_date) = ?
                      ) t
                      ORDER BY transaction_date";
$stmt = $conn->prepare($transactionsQuery);
$stmt->bind_param("ss", $date, $date);
$stmt->execute();
$transactionsResult = $stmt->get_result();

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="daily_profit_report_' . $date . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Daily Profit Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Daily Profit Report - <?php echo formatDate($date); ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Finance
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
                
                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Daily Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <td>Total Income:</td>
                                        <td class="text-end text-success"><?php echo formatCurrency($dailyIncome); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Total Expenses:</td>
                                        <td class="text-end text-danger"><?php echo formatCurrency($dailyExpense); ?></td>
                                    </tr>
                                    <tr class="<?php echo $profit >= 0 ? 'table-success' : 'table-danger'; ?>">
                                        <th>Net Profit/Loss:</th>
                                        <th class="text-end <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($profit); ?>
                                        </th>
                                    </tr>
                                    <tr>
                                        <td>Profit Margin:</td>
                                        <td class="text-end <?php echo $profitMargin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($profitMargin, 2) . '%'; ?>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="../income/reports/daily.php?date=<?php echo $date; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-arrow-up me-1"></i> Income Details
                                    </a>
                                    <a href="../expenses/reports/daily.php?date=<?php echo $date; ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-arrow-down me-1"></i> Expense Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($dailyIncome > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Transactions:</span>
                                        <span class="badge bg-primary rounded-pill"><?php echo $incomeTransactions; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Average Transaction:</span>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo formatCurrency($incomeTransactions > 0 ? $dailyIncome / $incomeTransactions : 0); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Smallest Transaction:</span>
                                        <span class="badge bg-secondary rounded-pill">
                                            <?php echo formatCurrency($minIncomeTransaction); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Largest Transaction:</span>
                                        <span class="badge bg-success rounded-pill">
                                            <?php echo formatCurrency($maxIncomeTransaction); ?>
                                        </span>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-2">Income Breakdown:</h6>
                                    <ul class="list-group">
                                        <?php foreach ($incomeByType as $type => $amount): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                                <span class="badge bg-success rounded-pill">
                                                    <?php echo formatCurrency($amount); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No income recorded for this day.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Expense Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($dailyExpense > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Transactions:</span>
                                        <span class="badge bg-primary rounded-pill"><?php echo $expenseTransactions; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Average Transaction:</span>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo formatCurrency($expenseTransactions > 0 ? $dailyExpense / $expenseTransactions : 0); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Smallest Transaction:</span>
                                        <span class="badge bg-secondary rounded-pill">
                                            <?php echo formatCurrency($minExpenseTransaction); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Largest Transaction:</span>
                                        <span class="badge bg-danger rounded-pill">
                                            <?php echo formatCurrency($maxExpenseTransaction); ?>
                                        </span>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-2">Expense Breakdown:</h6>
                                    <ul class="list-group">
                                        <?php foreach ($expensesByType as $type => $amount): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                                <span class="badge bg-danger rounded-pill">
                                                    <?php echo formatCurrency($amount); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No expenses recorded for this day.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hourly Financial Trend -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Hourly Financial Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="hourlyFinancialChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Profit Visual -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income vs Expense</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="incomeVsExpenseChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Profit Visualization</h6>
                            </div>
                            <div class="card-body text-center">
                                <canvas id="profitGaugeChart" height="250"></canvas>
                                
                                <?php if ($dailyIncome > 0): ?>
                                <div class="mt-3">
                                    <h4 class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($profit); ?>
                                    </h4>
                                    <p class="text-muted mb-0">
                                        Profit Margin: <?php echo number_format($profitMargin, 2); ?>%
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transactions List -->
                <h6 class="border-bottom pb-2 mb-3">Financial Transactions</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($transactionsResult->num_rows > 0) {
                                $counter = 1;
                                while ($row = $transactionsResult->fetch_assoc()) { 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['transaction_date'])); ?></td>
                                    <td>
                                        <?php if ($row['type'] === 'income'): ?>
                                            <span class="badge bg-success">Income</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expense</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($row['description'])) {
                                            echo htmlspecialchars($row['description']);
                                        } else {
                                            echo ucwords(str_replace('_', ' ', $row['transaction_type']));
                                        }
                                        ?>
                                    </td>
                                    <td class="<?php echo ($row['type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($row['amount']); ?>
                                    </td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $row['transaction_type'])); ?></td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No transactions found for the selected date</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total Income:</th>
                                <th class="text-success"><?php echo formatCurrency($dailyIncome); ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Total Expenses:</th>
                                <th class="text-danger"><?php echo formatCurrency($dailyExpense); ?></th>
                                <th></th>
                            </tr>
                            <tr class="<?php echo $profit >= 0 ? 'table-success' : 'table-danger'; ?>">
                                <th colspan="4" class="text-end">Net Profit/Loss:</th>
                                <th class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </th>
                                <th></th>
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

<!-- JavaScript for Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Extract chart data from PHP
    const hourlyData = {
        income: <?php echo json_encode(array_map(function($hour) { return $hour['income']; }, $hourlyData)); ?>,
        expense: <?php echo json_encode(array_map(function($hour) { return $hour['expense']; }, $hourlyData)); ?>,
        profit: <?php echo json_encode(array_map(function($hour) { return $hour['profit']; }, $hourlyData)); ?>
    };
    
    const summaryData = {
        income: <?php echo $dailyIncome; ?>,
        expense: <?php echo $dailyExpense; ?>,
        profit: <?php echo $profit; ?>,
        profitMargin: <?php echo $profitMargin; ?>
    };
    
    // Format for hour labels
    const hourLabels = [];
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
        
        hourLabels.push(hour + ' ' + meridiem);
    }
    
    // 1. Hourly Financial Chart
    const hourlyCtx = document.getElementById('hourlyFinancialChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourLabels,
            datasets: [
                {
                    label: 'Income',
                    data: hourlyData.income,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'Expense',
                    data: hourlyData.expense,
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'Profit',
                    data: hourlyData.profit,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 2,
                    tension: 0.1
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
    
    // 2. Income vs Expense Chart
    const comparisonCtx = document.getElementById('incomeVsExpenseChart').getContext('2d');
    new Chart(comparisonCtx, {
        type: 'bar',
        data: {
            labels: ['Daily Financial Summary'],
            datasets: [
                {
                    label: 'Income',
                    data: [summaryData.income],
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 1
                },
                {
                    label: 'Expense',
                    data: [summaryData.expense],
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
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
    
    // 3. Profit Gauge Chart
    const gaugeCtx = document.getElementById('profitGaugeChart').getContext('2d');
    
    // Only create gauge chart if there is income or expense
    if (summaryData.income > 0 || summaryData.expense > 0) {
        let profitColor = 'rgb(40, 167, 69)'; // Green for profit
        if (summaryData.profit < 0) {
            profitColor = 'rgb(220, 53, 69)'; // Red for loss
        } else if (summaryData.profitMargin < 10) {
            profitColor = 'rgb(255, 193, 7)'; // Yellow for low margin
        }
        
        new Chart(gaugeCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [
                        Math.max(summaryData.profit, 0), // Profit or 0 if loss
                        Math.max(-summaryData.profit, 0), // Loss or 0 if profit
                        summaryData.expense // Expense portion always shown
                    ],
                    backgroundColor: [
                        profitColor,
                        'rgb(220, 53, 69)',
                        'rgba(108, 117, 125, 0.2)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });
    } else {
        // If no financial data, display a message
        gaugeCtx.canvas.style.display = 'none';
        gaugeCtx.canvas.parentNode.innerHTML = '<div class="alert alert-info mt-5">No financial data available for visualization.</div>';
    }
    
    // Auto-submit form on date change
    document.getElementById('date').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>