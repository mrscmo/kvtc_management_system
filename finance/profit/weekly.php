<?php
// Set page title
$page_title = "Weekly Profit Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Get the week start and end dates
$week = isset($_GET['week']) ? intval($_GET['week']) : date('W');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate the first and last day of the week
$dto = new DateTime();
$dto->setISODate($year, $week);
$startDate = $dto->format('Y-m-d');
$dto->modify('+6 days');
$endDate = $dto->format('Y-m-d');

// Get income data for the week
$incomeQuery = "SELECT SUM(amount) as total, 
                COUNT(*) as count,
                MIN(amount) as min_transaction,
                MAX(amount) as max_transaction,
                AVG(amount) as avg_transaction
                FROM income 
                WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$incomeResult = $stmt->get_result();
$incomeData = $incomeResult->fetch_assoc();
$weeklyIncome = $incomeData['total'] ?? 0;
$incomeTransactions = $incomeData['count'] ?? 0;
$avgIncomeTransaction = $incomeData['avg_transaction'] ?? 0;
$minIncomeTransaction = $incomeData['min_transaction'] ?? 0;
$maxIncomeTransaction = $incomeData['max_transaction'] ?? 0;

// Get income by type
$incomeTypeQuery = "SELECT income_type, SUM(amount) as total 
                    FROM income 
                    WHERE income_date BETWEEN ? AND ?
                    GROUP BY income_type
                    ORDER BY total DESC";
$stmt = $conn->prepare($incomeTypeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$incomeTypeResult = $stmt->get_result();

$incomeByType = [];
while ($row = $incomeTypeResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
}

// Get expenses data for the week
$expenseQuery = "SELECT SUM(amount) as total,
                 COUNT(*) as count,
                 MIN(amount) as min_transaction,
                 MAX(amount) as max_transaction,
                 AVG(amount) as avg_transaction
                 FROM expenses 
                 WHERE expense_date BETWEEN ? AND ?";
$stmt = $conn->prepare($expenseQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$expenseResult = $stmt->get_result();
$expenseData = $expenseResult->fetch_assoc();
$weeklyExpense = $expenseData['total'] ?? 0;
$expenseTransactions = $expenseData['count'] ?? 0;
$avgExpenseTransaction = $expenseData['avg_transaction'] ?? 0;
$minExpenseTransaction = $expenseData['min_transaction'] ?? 0;
$maxExpenseTransaction = $expenseData['max_transaction'] ?? 0;

// Get expenses by type
$expenseTypeQuery = "SELECT expense_type, SUM(amount) as total 
                     FROM expenses 
                     WHERE expense_date BETWEEN ? AND ?
                     GROUP BY expense_type
                     ORDER BY total DESC";
$stmt = $conn->prepare($expenseTypeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$expenseTypeResult = $stmt->get_result();

$expensesByType = [];
while ($row = $expenseTypeResult->fetch_assoc()) {
    $expensesByType[$row['expense_type']] = $row['total'];
}

// Calculate profit/loss
$profit = $weeklyIncome - $weeklyExpense;
$profitMargin = ($weeklyIncome > 0) ? ($profit / $weeklyIncome) * 100 : 0;

// Get daily financial data for chart
$dailyQuery = "SELECT 
                  DATE(t.transaction_date) as day,
                  DAYNAME(t.transaction_date) as day_name,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income,
                  SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expense,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END) as profit
               FROM (
                  SELECT income_date as transaction_date, 'income' as transaction_type, amount FROM income WHERE income_date BETWEEN ? AND ?
                  UNION ALL
                  SELECT expense_date as transaction_date, 'expense' as transaction_type, amount FROM expenses WHERE expense_date BETWEEN ? AND ?
               ) t
               GROUP BY DATE(t.transaction_date), DAYNAME(t.transaction_date)
               ORDER BY DATE(t.transaction_date)";
$stmt = $conn->prepare($dailyQuery);
$stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$dailyResult = $stmt->get_result();

$dailyData = [];
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Initialize daily data with zeros
$currentDate = new DateTime($startDate);
for ($i = 0; $i < 7; $i++) {
    $day = $currentDate->format('Y-m-d');
    $dayName = $currentDate->format('l');
    $dailyData[$day] = [
        'day_name' => $dayName,
        'income' => 0,
        'expense' => 0,
        'profit' => 0
    ];
    $currentDate->modify('+1 day');
}

// Fill with actual data
while ($row = $dailyResult->fetch_assoc()) {
    $dailyData[$row['day']]['income'] = $row['income'];
    $dailyData[$row['day']]['expense'] = $row['expense'];
    $dailyData[$row['day']]['profit'] = $row['profit'];
}

// Compare with previous week
$prevWeek = $week - 1;
$prevYear = $year;
if ($prevWeek < 1) {
    $prevWeek = 52;
    $prevYear--;
}

$prevDto = new DateTime();
$prevDto->setISODate($prevYear, $prevWeek);
$prevStartDate = $prevDto->format('Y-m-d');
$prevDto->modify('+6 days');
$prevEndDate = $prevDto->format('Y-m-d');

// Get previous week income
$prevIncomeQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($prevIncomeQuery);
$stmt->bind_param("ss", $prevStartDate, $prevEndDate);
$stmt->execute();
$prevIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get previous week expenses
$prevExpenseQuery = "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?";
$stmt = $conn->prepare($prevExpenseQuery);
$stmt->bind_param("ss", $prevStartDate, $prevEndDate);
$stmt->execute();
$prevExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Calculate previous week profit
$prevProfit = $prevIncome - $prevExpense;
$prevProfitMargin = ($prevIncome > 0) ? ($prevProfit / $prevIncome) * 100 : 0;

// Calculate week-over-week changes
$incomeChange = ($prevIncome > 0) ? (($weeklyIncome - $prevIncome) / $prevIncome) * 100 : 0;
$expenseChange = ($prevExpense > 0) ? (($weeklyExpense - $prevExpense) / $prevExpense) * 100 : 0;
$profitChange = ($prevProfit > 0) ? (($profit - $prevProfit) / $prevProfit) * 100 : 0;
$marginChange = $profitMargin - $prevProfitMargin;

// Get all transactions for the week
$transactionsQuery = "SELECT * FROM (
                        SELECT 'income' as type, income_date as transaction_date, income_type as transaction_type, 
                               income_name as description, amount, student_id
                        FROM income
                        WHERE income_date BETWEEN ? AND ?
                        UNION ALL
                        SELECT 'expense' as type, expense_date as transaction_date, expense_type as transaction_type,
                               expense_name as description, amount, student_id
                        FROM expenses
                        WHERE expense_date BETWEEN ? AND ?
                      ) t
                      ORDER BY transaction_date DESC";
$stmt = $conn->prepare($transactionsQuery);
$stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$transactionsResult = $stmt->get_result();

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="weekly_profit_report_' . $year . '_week_' . $week . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Weekly Profit Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Weekly Profit Report - Week <?php echo $week; ?>, <?php echo $year; ?> (<?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?>)</h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Finance
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Week Selector -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="week" class="col-form-label">Week:</label>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="week" name="week">
                                    <?php for ($i = 1; $i <= 53; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($week == $i) ? 'selected' : ''; ?>>
                                            Week <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label for="year" class="col-form-label">Year:</label>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="year" name="year">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($year == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Weekly Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body bg-success bg-opacity-10 text-center">
                                                <h6 class="mb-2">Total Income</h6>
                                                <h3 class="text-success mb-1"><?php echo formatCurrency($weeklyIncome); ?></h3>
                                                <?php if ($incomeChange != 0): ?>
                                                    <div class="small <?php echo $incomeChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $incomeChange >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($incomeChange, 1)); ?>% from last week
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body bg-danger bg-opacity-10 text-center">
                                                <h6 class="mb-2">Total Expenses</h6>
                                                <h3 class="text-danger mb-1"><?php echo formatCurrency($weeklyExpense); ?></h3>
                                                <?php if ($expenseChange != 0): ?>
                                                    <div class="small <?php echo $expenseChange <= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $expenseChange >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($expenseChange, 1)); ?>% from last week
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card <?php echo $profit >= 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h6>Net Profit/Loss</h6>
                                            <?php if ($profitChange != 0): ?>
                                                <span class="badge <?php echo $profitChange >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $profitChange >= 0 ? '+' : ''; ?><?php echo number_format($profitChange, 1); ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                            <?php echo formatCurrency($profit); ?>
                                        </h3>
                                    </div>
                                </div>
                                
                                <div class="card bg-info bg-opacity-10">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h6>Profit Margin</h6>
                                            <?php if ($marginChange != 0): ?>
                                                <span class="badge <?php echo $marginChange >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $marginChange >= 0 ? '+' : ''; ?><?php echo number_format($marginChange, 1); ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="<?php echo $profitMargin >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                            <?php echo number_format($profitMargin, 2); ?>%
                                        </h3>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="../income/reports/weekly.php?week=<?php echo $week; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-arrow-up me-1"></i> Income Report
                                    </a>
                                    <a href="../expenses/reports/weekly.php?week=<?php echo $week; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-arrow-down me-1"></i> Expense Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Daily Profit Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyProfitChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Analysis Section -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($weeklyIncome > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Transactions:</span>
                                        <span class="badge bg-primary rounded-pill"><?php echo $incomeTransactions; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Average Transaction:</span>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo formatCurrency($avgIncomeTransaction); ?>
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
                                    
                                    <?php if (count($incomeByType) > 0): ?>
                                        <h6 class="mt-3 mb-2">Income by Type:</h6>
                                        <canvas id="incomeTypeChart" height="180"></canvas>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No income recorded for this week.
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
                                <?php if ($weeklyExpense > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Transactions:</span>
                                        <span class="badge bg-primary rounded-pill"><?php echo $expenseTransactions; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Average Transaction:</span>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo formatCurrency($avgExpenseTransaction); ?>
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
                                    
                                    <?php if (count($expensesByType) > 0): ?>
                                        <h6 class="mt-3 mb-2">Expenses by Type:</h6>
                                        <canvas id="expenseTypeChart" height="180"></canvas>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No expenses recorded for this week.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income vs Expense</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($weeklyIncome > 0 || $weeklyExpense > 0): ?>
                                    <canvas id="comparisonChart" height="215"></canvas>
                                    
                                    <div class="progress mt-3" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo ($weeklyIncome > 0 || $weeklyExpense > 0) ? ($weeklyIncome / ($weeklyIncome + $weeklyExpense) * 100) : 0; ?>%" 
                                             aria-valuenow="<?php echo $weeklyIncome; ?>" aria-valuemin="0" 
                                             aria-valuemax="<?php echo $weeklyIncome + $weeklyExpense; ?>">
                                            Income
                                        </div>
                                        <div class="progress-bar bg-danger" role="progressbar" 
                                             style="width: <?php echo ($weeklyIncome > 0 || $weeklyExpense > 0) ? ($weeklyExpense / ($weeklyIncome + $weeklyExpense) * 100) : 0; ?>%" 
                                             aria-valuenow="<?php echo $weeklyExpense; ?>" aria-valuemin="0" 
                                             aria-valuemax="<?php echo $weeklyIncome + $weeklyExpense; ?>">
                                            Expenses
                                        </div>
                                    </div>
                                    <small class="text-muted">Income-Expense Distribution</small>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No financial data available for this week.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Breakdown -->
                <h6 class="border-bottom pb-2 mb-3">Daily Breakdown</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Date</th>
                                <th>Income</th>
                                <th>Expenses</th>
                                <th>Profit/Loss</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasData = false;
                            foreach ($dailyData as $date => $data): 
                                $dayProfit = $data['income'] - $data['expense'];
                                if ($data['income'] > 0 || $data['expense'] > 0):
                                    $hasData = true;
                            ?>
                                <tr>
                                    <td><?php echo $data['day_name']; ?></td>
                                    <td><?php echo formatDate($date); ?></td>
                                    <td class="text-success"><?php echo formatCurrency($data['income']); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($data['expense']); ?></td>
                                    <td class="<?php echo $dayProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($dayProfit); ?>
                                    </td>
                                    <td>
                                        <a href="daily.php?date=<?php echo $date; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$hasData):
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No financial data available for this week</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="2">Total</th>
                                <th class="text-success"><?php echo formatCurrency($weeklyIncome); ?></th>
                                <th class="text-danger"><?php echo formatCurrency($weeklyExpense); ?></th>
                                <th class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Transactions List -->
                <h6 class="border-bottom pb-2 mb-3">Transaction History</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
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
                                $maxRows = 30; // Limit to most recent 30 transactions
                                
                                while ($row = $transactionsResult->fetch_assoc() && $counter <= $maxRows) { 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo formatDate($row['transaction_date']) . ' ' . date('g:i A', strtotime($row['transaction_date'])); ?></td>
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
                                
                                if ($transactionsResult->num_rows > $maxRows):
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <em>Showing the most recent <?php echo $maxRows; ?> transactions. The full dataset contains <?php echo $transactionsResult->num_rows; ?> transactions.</em>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No transactions found for the selected week</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Quick Navigation -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between">
                            <a href="<?php 
                                $prevWeek = $week - 1;
                                $prevYear = $year;
                                if ($prevWeek < 1) {
                                    $prevWeek = 52;
                                    $prevYear--;
                                }
                                echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week=' . $prevWeek . '&year=' . $prevYear); 
                            ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left me-1"></i> Previous Week
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week=' . date('W') . '&year=' . date('Y')); ?>" class="btn btn-outline-secondary">
                                Current Week
                            </a>
                            <a href="<?php 
                                $nextWeek = $week + 1;
                                $nextYear = $year;
                                if ($nextWeek > 52) {
                                    $nextWeek = 1;
                                    $nextYear++;
                                }
                                echo htmlspecialchars($_SERVER["PHP_SELF"] . '?week=' . $nextWeek . '&year=' . $nextYear); 
                            ?>" class="btn btn-outline-primary">
                                Next Week <i class="fas fa-chevron-right ms-1"></i>
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
    const chartData = {
        // Daily financial data
        daily: {
            labels: <?php echo json_encode(array_map(function($data) { return $data['day_name']; }, $dailyData)); ?>,
            income: <?php echo json_encode(array_map(function($data) { return $data['income']; }, $dailyData)); ?>,
            expense: <?php echo json_encode(array_map(function($data) { return $data['expense']; }, $dailyData)); ?>,
            profit: <?php echo json_encode(array_map(function($data) { return $data['profit']; }, $dailyData)); ?>
        },
        
        // Income types data
        incomeTypes: {
            labels: <?php echo json_encode(array_map(function($type) { return ucwords(str_replace('_', ' ', $type)); }, array_keys($incomeByType))); ?>,
            values: <?php echo json_encode(array_values($incomeByType)); ?>
        },
        
        // Expense types data
        expenseTypes: {
            labels: <?php echo json_encode(array_map(function($type) { return ucwords(str_replace('_', ' ', $type)); }, array_keys($expensesByType))); ?>,
            values: <?php echo json_encode(array_values($expensesByType)); ?>
        },
        
        // Summary data
        summary: {
            income: <?php echo $weeklyIncome; ?>,
            expense: <?php echo $weeklyExpense; ?>,
            profit: <?php echo $profit; ?>
        }
    };
    
    // 1. Daily Profit Chart
    if (document.getElementById('dailyProfitChart')) {
        const dailyCtx = document.getElementById('dailyProfitChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: chartData.daily.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.daily.income,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1,
                        order: 2
                    },
                    {
                        label: 'Expense',
                        data: chartData.daily.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1,
                        order: 3
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.daily.profit,
                        type: 'line',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgb(13, 110, 253)',
                        borderWidth: 2,
                        pointBackgroundColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 'rgb(40, 167, 69)' : 'rgb(220, 53, 69)';
                        },
                        pointBorderColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 'rgb(40, 167, 69)' : 'rgb(220, 53, 69)';
                        },
                        pointRadius: 5,
                        tension: 0.1,
                        fill: true,
                        order: 1
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
    }
    
    // 2. Income Type Pie Chart
    if (document.getElementById('incomeTypeChart') && chartData.incomeTypes.values.length > 0) {
        const incomeTypeCtx = document.getElementById('incomeTypeChart').getContext('2d');
        
        // Generate colors based on number of income types
        const incomeColors = generateColorArray(chartData.incomeTypes.labels.length, 'success');
        
        new Chart(incomeTypeCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.incomeTypes.labels,
                datasets: [
                    {
                        data: chartData.incomeTypes.values,
                        backgroundColor: incomeColors.bg,
                        borderColor: incomeColors.border,
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${new Intl.NumberFormat('en-US', { 
                                    style: 'currency', 
                                    currency: 'LKR',
                                    maximumFractionDigits: 0
                                }).format(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 3. Expense Type Pie Chart
    if (document.getElementById('expenseTypeChart') && chartData.expenseTypes.values.length > 0) {
        const expenseTypeCtx = document.getElementById('expenseTypeChart').getContext('2d');
        
        // Generate colors based on number of expense types
        const expenseColors = generateColorArray(chartData.expenseTypes.labels.length, 'danger');
        
        new Chart(expenseTypeCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.expenseTypes.labels,
                datasets: [
                    {
                        data: chartData.expenseTypes.values,
                        backgroundColor: expenseColors.bg,
                        borderColor: expenseColors.border,
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${new Intl.NumberFormat('en-US', { 
                                    style: 'currency', 
                                    currency: 'LKR',
                                    maximumFractionDigits: 0
                                }).format(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 4. Comparison Chart
    if (document.getElementById('comparisonChart')) {
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(comparisonCtx, {
            type: 'bar',
            data: {
                labels: ['Weekly Summary'],
                datasets: [
                    {
                        label: 'Income',
                        data: [chartData.summary.income],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expense',
                        data: [chartData.summary.expense],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1
                    },
                    {
                        label: 'Profit/Loss',
                        data: [chartData.summary.profit],
                        backgroundColor: chartData.summary.profit >= 0 ? 'rgba(13, 110, 253, 0.7)' : 'rgba(255, 193, 7, 0.7)',
                        borderColor: chartData.summary.profit >= 0 ? 'rgb(13, 110, 253)' : 'rgb(255, 193, 7)',
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
    }
    
    // Helper function to generate color arrays
    function generateColorArray(count, type) {
        const bgColors = [];
        const borderColors = [];
        
        // Base color based on type
        let baseHue = 0;
        if (type === 'success') baseHue = 120; // Green
        else if (type === 'danger') baseHue = 0; // Red
        else if (type === 'info') baseHue = 200; // Blue
        
        // Generate variations
        for (let i = 0; i < count; i++) {
            // Calculate hue with enough separation
            const hue = (baseHue + (i * 30)) % 360;
            bgColors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
            borderColors.push(`hsl(${hue}, 70%, 40%)`);
        }
        
        return {
            bg: bgColors,
            border: borderColors
        };
    }
    
    // Auto-submit form on week/year change
    document.getElementById('week').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('year').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>