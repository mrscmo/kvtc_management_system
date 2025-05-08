<?php
// Set page title
$page_title = "Monthly Profit Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Default to current month if not specified
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Calculate the first and last day of the month
$startDate = date('Y-m-01', strtotime("$year-$month-01"));
$endDate = date('Y-m-t', strtotime("$year-$month-01"));

// Get income data for the month
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
$monthlyIncome = $incomeData['total'] ?? 0;
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

// Get expenses data for the month
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
$monthlyExpense = $expenseData['total'] ?? 0;
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
$profit = $monthlyIncome - $monthlyExpense;
$profitMargin = ($monthlyIncome > 0) ? ($profit / $monthlyIncome) * 100 : 0;

// Get daily financial data for chart
$dailyQuery = "SELECT 
                  DATE(t.transaction_date) as day,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income,
                  SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expense,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END) as profit
               FROM (
                  SELECT income_date as transaction_date, 'income' as transaction_type, amount FROM income WHERE income_date BETWEEN ? AND ?
                  UNION ALL
                  SELECT expense_date as transaction_date, 'expense' as transaction_type, amount FROM expenses WHERE expense_date BETWEEN ? AND ?
               ) t
               GROUP BY DATE(t.transaction_date)
               ORDER BY DATE(t.transaction_date)";
$stmt = $conn->prepare($dailyQuery);
$stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$dailyResult = $stmt->get_result();

$daysInMonth = date('t', strtotime($startDate));
$dailyData = [];

// Initialize with zeros for all days in month
for ($i = 1; $i <= $daysInMonth; $i++) {
    $day = date('Y-m-d', strtotime($startDate . ' + ' . ($i - 1) . ' days'));
    $dailyData[$day] = [
        'income' => 0,
        'expense' => 0,
        'profit' => 0
    ];
}

// Fill with actual data
while ($row = $dailyResult->fetch_assoc()) {
    $dailyData[$row['day']] = [
        'income' => $row['income'],
        'expense' => $row['expense'],
        'profit' => $row['profit']
    ];
}

// Calculate weekly data
$weeklyData = [
    'week1' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'week2' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'week3' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'week4' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'week5' => ['income' => 0, 'expense' => 0, 'profit' => 0]
];

$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);
while ($currentDate <= $endDateTime) {
    $day = $currentDate->format('j');
    $week = ceil($day / 7);
    if ($week > 5) $week = 5;
    
    $dayStr = $currentDate->format('Y-m-d');
    if (isset($dailyData[$dayStr])) {
        $weeklyData["week$week"]['income'] += $dailyData[$dayStr]['income'];
        $weeklyData["week$week"]['expense'] += $dailyData[$dayStr]['expense'];
        $weeklyData["week$week"]['profit'] += $dailyData[$dayStr]['profit'];
    }
    
    $currentDate->modify('+1 day');
}

// Get six-month profit trend
$sixMonthTrend = [];
$sixMonthLabels = [];

for ($i = 5; $i >= 0; $i--) {
    $trendMonth = date('m', strtotime("-$i months", strtotime("$year-$month-01")));
    $trendYear = date('Y', strtotime("-$i months", strtotime("$year-$month-01")));
    $trendStartDate = date('Y-m-01', strtotime("$trendYear-$trendMonth-01"));
    $trendEndDate = date('Y-m-t', strtotime("$trendYear-$trendMonth-01"));
    
    $sixMonthLabels[] = date('M Y', strtotime($trendStartDate));
    
    // Get income for this month
    $trendIncomeQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($trendIncomeQuery);
    $stmt->bind_param("ss", $trendStartDate, $trendEndDate);
    $stmt->execute();
    $trendIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // Get expenses for this month
    $trendExpenseQuery = "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($trendExpenseQuery);
    $stmt->bind_param("ss", $trendStartDate, $trendEndDate);
    $stmt->execute();
    $trendExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // Calculate profit
    $trendProfit = $trendIncome - $trendExpense;
    
    $sixMonthTrend[] = $trendProfit;
}

// Compare with previous month
$prevMonth = date('m', strtotime('-1 month', strtotime("$year-$month-01")));
$prevYear = date('Y', strtotime('-1 month', strtotime("$year-$month-01")));
$prevStartDate = date('Y-m-01', strtotime("$prevYear-$prevMonth-01"));
$prevEndDate = date('Y-m-t', strtotime("$prevYear-$prevMonth-01"));

// Get previous month income
$prevIncomeQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($prevIncomeQuery);
$stmt->bind_param("ss", $prevStartDate, $prevEndDate);
$stmt->execute();
$prevIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get previous month expenses
$prevExpenseQuery = "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?";
$stmt = $conn->prepare($prevExpenseQuery);
$stmt->bind_param("ss", $prevStartDate, $prevEndDate);
$stmt->execute();
$prevExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Calculate previous month profit
$prevProfit = $prevIncome - $prevExpense;
$prevProfitMargin = ($prevIncome > 0) ? ($prevProfit / $prevIncome) * 100 : 0;

// Calculate month-over-month changes
$incomeChange = ($prevIncome > 0) ? (($monthlyIncome - $prevIncome) / $prevIncome) * 100 : 0;
$expenseChange = ($prevExpense > 0) ? (($monthlyExpense - $prevExpense) / $prevExpense) * 100 : 0;
$profitChange = ($prevProfit > 0) ? (($profit - $prevProfit) / $prevProfit) * 100 : 0;
$marginChange = $profitMargin - $prevProfitMargin;

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="monthly_profit_report_' . $year . '_' . $month . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Monthly Profit Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Monthly Profit Report - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Finance
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Month Selector -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="month" class="col-form-label">Month:</label>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="month" name="month">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($month == str_pad($i, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                                            <?php echo date('F', strtotime("2000-$i-01")); ?>
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
                                <h6 class="mb-0">Monthly Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body bg-success bg-opacity-10">
                                                <div class="d-flex justify-content-between">
                                                    <h6>Total Income</h6>
                                                    <?php if ($incomeChange != 0): ?>
                                                        <span class="badge <?php echo $incomeChange >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $incomeChange >= 0 ? '+' : ''; ?><?php echo number_format($incomeChange, 1); ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h4 class="text-success mb-0"><?php echo formatCurrency($monthlyIncome); ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body bg-danger bg-opacity-10">
                                                <div class="d-flex justify-content-between">
                                                    <h6>Total Expenses</h6>
                                                    <?php if ($expenseChange != 0): ?>
                                                        <span class="badge <?php echo $expenseChange <= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $expenseChange >= 0 ? '+' : ''; ?><?php echo number_format($expenseChange, 1); ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h4 class="text-danger mb-0"><?php echo formatCurrency($monthlyExpense); ?></h4>
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
                                        <h4 class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                            <?php echo formatCurrency($profit); ?>
                                        </h4>
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
                                        <h4 class="<?php echo $profitMargin >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                            <?php echo number_format($profitMargin, 2); ?>%
                                        </h4>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="../income/reports/monthly.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-arrow-up me-1"></i> Income Report
                                    </a>
                                    <a href="../expenses/reports/monthly.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-arrow-down me-1"></i> Expense Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Six-Month Profit Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="sixMonthTrendChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Analysis -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($monthlyIncome > 0): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
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
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Smallest Transaction:</span>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?php echo formatCurrency($minIncomeTransaction); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Largest Transaction:</span>
                                                <span class="badge bg-success rounded-pill">
                                                    <?php echo formatCurrency($maxIncomeTransaction); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-2">Top Income Sources:</h6>
                                    <canvas id="incomeTypeChart" height="180"></canvas>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No income recorded for this month.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Expense Analysis</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($monthlyExpense > 0): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
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
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Smallest Transaction:</span>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?php echo formatCurrency($minExpenseTransaction); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Largest Transaction:</span>
                                                <span class="badge bg-danger rounded-pill">
                                                    <?php echo formatCurrency($maxExpenseTransaction); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-2">Top Expense Categories:</h6>
                                    <canvas id="expenseTypeChart" height="180"></canvas>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No expenses recorded for this month.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Financial Trend -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Daily Financial Trend</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active" id="showAll">All</button>
                                    <button type="button" class="btn btn-outline-success" id="showIncome">Income</button>
                                    <button type="button" class="btn btn-outline-danger" id="showExpense">Expense</button>
                                    <button type="button" class="btn btn-outline-info" id="showProfit">Profit/Loss</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyFinancialChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weekly Analysis -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Weekly Financial Analysis</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="weeklyChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Weekly Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Week</th>
                                            <th>Income</th>
                                            <th>Expense</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $weekCount = 0;
                                        foreach ($weeklyData as $week => $data): 
                                            if ($data['income'] > 0 || $data['expense'] > 0): 
                                                $weekCount++;
                                                $weekNum = substr($week, 4);
                                                $weekProfit = $data['income'] - $data['expense'];
                                        ?>
                                            <tr>
                                                <td>Week <?php echo $weekNum; ?></td>
                                                <td class="text-success"><?php echo formatCurrency($data['income']); ?></td>
                                                <td class="text-danger"><?php echo formatCurrency($data['expense']); ?></td>
                                                <td class="<?php echo $weekProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo formatCurrency($weekProfit); ?>
                                                </td>
                                            </tr>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        
                                        if ($weekCount === 0):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-success"><?php echo formatCurrency($monthlyIncome); ?></th>
                                            <th class="text-danger"><?php echo formatCurrency($monthlyExpense); ?></th>
                                            <th class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($profit); ?>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
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
                                if ($data['income'] > 0 || $data['expense'] > 0):
                                    $hasData = true;
                                    $dayProfit = $data['income'] - $data['expense'];
                            ?>
                                <tr>
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
                                    <td colspan="5" class="text-center">No financial data available for this month</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th>Total</th>
                                <th class="text-success"><?php echo formatCurrency($monthlyIncome); ?></th>
                                <th class="text-danger"><?php echo formatCurrency($monthlyExpense); ?></th>
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
                            <a href="<?php 
                                $prevMonth = $month - 1;
                                $prevYear = $year;
                                if ($prevMonth < 1) {
                                    $prevMonth = 12;
                                    $prevYear--;
                                }
                                echo htmlspecialchars($_SERVER["PHP_SELF"] . '?month=' . str_pad($prevMonth, 2, '0', STR_PAD_LEFT) . '&year=' . $prevYear); 
                            ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left me-1"></i> Previous Month
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?month=' . date('m') . '&year=' . date('Y')); ?>" class="btn btn-outline-secondary">
                                Current Month
                            </a>
                            <a href="<?php 
                                $nextMonth = $month + 1;
                                $nextYear = $year;
                                if ($nextMonth > 12) {
                                    $nextMonth = 1;
                                    $nextYear++;
                                }
                                echo htmlspecialchars($_SERVER["PHP_SELF"] . '?month=' . str_pad($nextMonth, 2, '0', STR_PAD_LEFT) . '&year=' . $nextYear); 
                            ?>" class="btn btn-outline-primary">
                                Next Month <i class="fas fa-chevron-right ms-1"></i>
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
    // Prepare chart data
    const chartData = {
        // Daily financial data
        daily: {
            labels: <?php echo json_encode(array_map(function($date) { return date('d', strtotime($date)); }, array_keys($dailyData))); ?>,
            income: <?php echo json_encode(array_map(function($data) { return $data['income']; }, $dailyData)); ?>,
            expense: <?php echo json_encode(array_map(function($data) { return $data['expense']; }, $dailyData)); ?>,
            profit: <?php echo json_encode(array_map(function($data) { return $data['profit']; }, $dailyData)); ?>
        },
        
        // Weekly financial data
        weekly: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
            income: [
                <?php echo $weeklyData['week1']['income']; ?>,
                <?php echo $weeklyData['week2']['income']; ?>,
                <?php echo $weeklyData['week3']['income']; ?>,
                <?php echo $weeklyData['week4']['income']; ?>,
                <?php echo $weeklyData['week5']['income']; ?>
            ],
            expense: [
                <?php echo $weeklyData['week1']['expense']; ?>,
                <?php echo $weeklyData['week2']['expense']; ?>,
                <?php echo $weeklyData['week3']['expense']; ?>,
                <?php echo $weeklyData['week4']['expense']; ?>,
                <?php echo $weeklyData['week5']['expense']; ?>
            ],
            profit: [
                <?php echo $weeklyData['week1']['profit']; ?>,
                <?php echo $weeklyData['week2']['profit']; ?>,
                <?php echo $weeklyData['week3']['profit']; ?>,
                <?php echo $weeklyData['week4']['profit']; ?>,
                <?php echo $weeklyData['week5']['profit']; ?>
            ]
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
        
        // Six-month trend data
        sixMonthTrend: {
            labels: <?php echo json_encode($sixMonthLabels); ?>,
            values: <?php echo json_encode($sixMonthTrend); ?>
        }
    };
    
    // Currency formatter for chart
    const currencyFormatter = {
        callback: function(value) {
            return 'Rs. ' + value.toLocaleString();
        }
    };
    
    // 1. Daily Financial Chart
    let dailyFinancialChart;
    if (document.getElementById('dailyFinancialChart')) {
        const dailyCtx = document.getElementById('dailyFinancialChart').getContext('2d');
        dailyFinancialChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: chartData.daily.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.daily.income,
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 2,
                        tension: 0.1
                    },
                    {
                        label: 'Expense',
                        data: chartData.daily.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.2)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 2,
                        tension: 0.1
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.daily.profit,
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
                        ticks: currencyFormatter
                    }
                }
            }
        });
        
        // Daily chart toggle buttons
        document.getElementById('showAll').addEventListener('click', function() {
            toggleChartDatasets(dailyFinancialChart, [true, true, true]);
            setActiveButton('showAll');
        });
        
        document.getElementById('showIncome').addEventListener('click', function() {
            toggleChartDatasets(dailyFinancialChart, [true, false, false]);
            setActiveButton('showIncome');
        });
        
        document.getElementById('showExpense').addEventListener('click', function() {
            toggleChartDatasets(dailyFinancialChart, [false, true, false]);
            setActiveButton('showExpense');
        });
        
        document.getElementById('showProfit').addEventListener('click', function() {
            toggleChartDatasets(dailyFinancialChart, [false, false, true]);
            setActiveButton('showProfit');
        });
    }
    
    // 2. Weekly Financial Chart
    if (document.getElementById('weeklyChart')) {
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: chartData.weekly.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.weekly.income,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expense',
                        data: chartData.weekly.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.weekly.profit,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgb(13, 110, 253)',
                        borderWidth: 1,
                        type: 'line',
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: currencyFormatter
                    }
                }
            }
        });
    }
    
    // 3. Six-Month Trend Chart
    if (document.getElementById('sixMonthTrendChart')) {
        const trendCtx = document.getElementById('sixMonthTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.sixMonthTrend.labels,
                datasets: [
                    {
                        label: 'Profit/Loss',
                        data: chartData.sixMonthTrend.values,
                        backgroundColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 'rgba(40, 167, 69, 0.5)' : 'rgba(220, 53, 69, 0.5)';
                        },
                        borderColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 'rgb(40, 167, 69)' : 'rgb(220, 53, 69)';
                        },
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        ticks: currencyFormatter
                    }
                }
            }
        });
    }
    
    // 4. Income Type Pie Chart
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
                            boxWidth: 15
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
    
    // 5. Expense Type Pie Chart
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
                            boxWidth: 15
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
    
    // Helper function to toggle chart datasets visibility
    function toggleChartDatasets(chart, visibility) {
        chart.data.datasets.forEach((dataset, index) => {
            chart.setDatasetVisibility(index, visibility[index]);
        });
        chart.update();
    }
    
    // Helper function to mark buttons as active
    function setActiveButton(activeId) {
        ['showAll', 'showIncome', 'showExpense', 'showProfit'].forEach(id => {
            document.getElementById(id).classList.remove('active');
            if (id === activeId) {
                document.getElementById(id).classList.add('active');
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
    
    // Auto-submit form on month/year change
    document.getElementById('month').addEventListener('change', function() {
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