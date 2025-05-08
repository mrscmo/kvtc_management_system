<?php
// Set page title
$page_title = "Yearly Profit Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Default to current year if not specified
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate the first and last day of the year
$startDate = "$year-01-01";
$endDate = "$year-12-31";

// Get income data for the year
$incomeQuery = "SELECT SUM(amount) as total, 
                COUNT(*) as count,
                MIN(amount) as min_transaction,
                MAX(amount) as max_transaction,
                AVG(amount) as avg_transaction
                FROM income 
                WHERE YEAR(income_date) = ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$incomeResult = $stmt->get_result();
$incomeData = $incomeResult->fetch_assoc();
$yearlyIncome = $incomeData['total'] ?? 0;
$incomeTransactions = $incomeData['count'] ?? 0;
$avgIncomeTransaction = $incomeData['avg_transaction'] ?? 0;
$minIncomeTransaction = $incomeData['min_transaction'] ?? 0;
$maxIncomeTransaction = $incomeData['max_transaction'] ?? 0;

// Get income by type
$incomeTypeQuery = "SELECT income_type, SUM(amount) as total 
                    FROM income 
                    WHERE YEAR(income_date) = ?
                    GROUP BY income_type
                    ORDER BY total DESC";
$stmt = $conn->prepare($incomeTypeQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$incomeTypeResult = $stmt->get_result();

$incomeByType = [];
while ($row = $incomeTypeResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
}

// Get expenses data for the year
$expenseQuery = "SELECT SUM(amount) as total,
                 COUNT(*) as count,
                 MIN(amount) as min_transaction,
                 MAX(amount) as max_transaction,
                 AVG(amount) as avg_transaction
                 FROM expenses 
                 WHERE YEAR(expense_date) = ?";
$stmt = $conn->prepare($expenseQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$expenseResult = $stmt->get_result();
$expenseData = $expenseResult->fetch_assoc();
$yearlyExpense = $expenseData['total'] ?? 0;
$expenseTransactions = $expenseData['count'] ?? 0;
$avgExpenseTransaction = $expenseData['avg_transaction'] ?? 0;
$minExpenseTransaction = $expenseData['min_transaction'] ?? 0;
$maxExpenseTransaction = $expenseData['max_transaction'] ?? 0;

// Get expenses by type
$expenseTypeQuery = "SELECT expense_type, SUM(amount) as total 
                     FROM expenses 
                     WHERE YEAR(expense_date) = ?
                     GROUP BY expense_type
                     ORDER BY total DESC";
$stmt = $conn->prepare($expenseTypeQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$expenseTypeResult = $stmt->get_result();

$expensesByType = [];
while ($row = $expenseTypeResult->fetch_assoc()) {
    $expensesByType[$row['expense_type']] = $row['total'];
}

// Calculate profit/loss
$profit = $yearlyIncome - $yearlyExpense;
$profitMargin = ($yearlyIncome > 0) ? ($profit / $yearlyIncome) * 100 : 0;

// Get monthly financial data for chart
$monthlyQuery = "SELECT 
                  MONTH(t.transaction_date) as month,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as income,
                  SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as expense,
                  SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END) as profit
                FROM (
                  SELECT income_date as transaction_date, 'income' as transaction_type, amount FROM income WHERE YEAR(income_date) = ?
                  UNION ALL
                  SELECT expense_date as transaction_date, 'expense' as transaction_type, amount FROM expenses WHERE YEAR(expense_date) = ?
                ) t
                GROUP BY MONTH(t.transaction_date)
                ORDER BY MONTH(t.transaction_date)";
$stmt = $conn->prepare($monthlyQuery);
$stmt->bind_param("ii", $year, $year);
$stmt->execute();
$monthlyResult = $stmt->get_result();

$monthlyData = [];
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Initialize with zeros for all months
for ($i = 1; $i <= 12; $i++) {
    $monthlyData[$i] = [
        'name' => $monthNames[$i],
        'income' => 0,
        'expense' => 0,
        'profit' => 0
    ];
}

// Fill with actual data
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyData[$row['month']]['income'] = $row['income'];
    $monthlyData[$row['month']]['expense'] = $row['expense'];
    $monthlyData[$row['month']]['profit'] = $row['profit'];
}

// Calculate quarterly data
$quarterlyData = [
    'Q1' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'Q2' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'Q3' => ['income' => 0, 'expense' => 0, 'profit' => 0],
    'Q4' => ['income' => 0, 'expense' => 0, 'profit' => 0]
];

foreach ($monthlyData as $month => $data) {
    $quarter = ceil($month / 3);
    $quarterKey = 'Q' . $quarter;
    $quarterlyData[$quarterKey]['income'] += $data['income'];
    $quarterlyData[$quarterKey]['expense'] += $data['expense'];
    $quarterlyData[$quarterKey]['profit'] += $data['profit'];
}

// Compare with previous year
$prevYear = $year - 1;
$prevStartDate = "$prevYear-01-01";
$prevEndDate = "$prevYear-12-31";

// Get previous year income
$prevIncomeQuery = "SELECT SUM(amount) as total FROM income WHERE YEAR(income_date) = ?";
$stmt = $conn->prepare($prevIncomeQuery);
$stmt->bind_param("i", $prevYear);
$stmt->execute();
$prevIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get previous year expenses
$prevExpenseQuery = "SELECT SUM(amount) as total FROM expenses WHERE YEAR(expense_date) = ?";
$stmt = $conn->prepare($prevExpenseQuery);
$stmt->bind_param("i", $prevYear);
$stmt->execute();
$prevExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Calculate previous year profit
$prevProfit = $prevIncome - $prevExpense;
$prevProfitMargin = ($prevIncome > 0) ? ($prevProfit / $prevIncome) * 100 : 0;

// Calculate year-over-year changes
$incomeChange = ($prevIncome > 0) ? (($yearlyIncome - $prevIncome) / $prevIncome) * 100 : 0;
$expenseChange = ($prevExpense > 0) ? (($yearlyExpense - $prevExpense) / $prevExpense) * 100 : 0;
$profitChange = ($prevProfit > 0) ? (($profit - $prevProfit) / $prevProfit) * 100 : 0;
$marginChange = $profitMargin - $prevProfitMargin;

// Get 5-year profit trend data
$fiveYearTrend = [];
$fiveYearLabels = [];

for ($i = 4; $i >= 0; $i--) {
    $trendYear = $year - $i;
    $fiveYearLabels[] = $trendYear;
    
    // Get income for this year
    $trendIncomeQuery = "SELECT SUM(amount) as total FROM income WHERE YEAR(income_date) = ?";
    $stmt = $conn->prepare($trendIncomeQuery);
    $stmt->bind_param("i", $trendYear);
    $stmt->execute();
    $trendIncome = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // Get expenses for this year
    $trendExpenseQuery = "SELECT SUM(amount) as total FROM expenses WHERE YEAR(expense_date) = ?";
    $stmt = $conn->prepare($trendExpenseQuery);
    $stmt->bind_param("i", $trendYear);
    $stmt->execute();
    $trendExpense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // Calculate profit
    $trendProfit = $trendIncome - $trendExpense;
    
    $fiveYearTrend[] = [
        'year' => $trendYear,
        'income' => $trendIncome,
        'expense' => $trendExpense,
        'profit' => $trendProfit
    ];
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="yearly_profit_report_' . $year . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Yearly Profit Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Yearly Profit Report - <?php echo $year; ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Finance
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Year Selector -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                            <div class="col-auto">
                                <label for="year" class="col-form-label">Select Year:</label>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="year" name="year">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 10; $i--): ?>
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
                
                <!-- Annual Summary -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Annual Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-body bg-success bg-opacity-10 text-center">
                                                <h6 class="mb-2">Total Income</h6>
                                                <h3 class="text-success mb-1"><?php echo formatCurrency($yearlyIncome); ?></h3>
                                                <?php if ($incomeChange != 0): ?>
                                                    <div class="small <?php echo $incomeChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $incomeChange >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($incomeChange, 1)); ?>% from <?php echo $prevYear; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-body bg-danger bg-opacity-10 text-center">
                                                <h6 class="mb-2">Total Expenses</h6>
                                                <h3 class="text-danger mb-1"><?php echo formatCurrency($yearlyExpense); ?></h3>
                                                <?php if ($expenseChange != 0): ?>
                                                    <div class="small <?php echo $expenseChange <= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $expenseChange >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($expenseChange, 1)); ?>% from <?php echo $prevYear; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card mb-3">
                                            <div class="card-body <?php echo $profit >= 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 text-center">
                                                <h6 class="mb-2">Net Profit/Loss</h6>
                                                <h3 class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                                                    <?php echo formatCurrency($profit); ?>
                                                </h3>
                                                <?php if ($profitChange != 0): ?>
                                                    <div class="small <?php echo $profitChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo $profitChange >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($profitChange, 1)); ?>% from <?php echo $prevYear; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3">Profit Margin: 
                                                    <span class="<?php echo $profitMargin >= 0 ? 'text-success' : 'text-danger'; ?> float-end">
                                                        <?php echo number_format($profitMargin, 2); ?>%
                                                    </span>
                                                </h6>
                                                
                                                <h6 class="mb-3">Income-to-Expense Ratio: 
                                                    <span class="<?php echo ($yearlyIncome > $yearlyExpense) ? 'text-success' : 'text-danger'; ?> float-end">
                                                        <?php echo ($yearlyExpense > 0) ? number_format($yearlyIncome / $yearlyExpense, 2) : 'N/A'; ?>
                                                    </span>
                                                </h6>
                                                
                                                <h6 class="mb-3">Total Transactions: 
                                                    <span class="text-primary float-end">
                                                        <?php echo number_format($incomeTransactions + $expenseTransactions); ?>
                                                    </span>
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-center gap-2 mb-3">
                                                    <a href="../income/reports/yearly.php?year=<?php echo $year; ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-arrow-up me-1"></i> Income Report
                                                    </a>
                                                    <a href="../expenses/reports/yearly.php?year=<?php echo $year; ?>" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-arrow-down me-1"></i> Expense Report
                                                    </a>
                                                </div>
                                                
                                                <div class="progress mb-2" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo ($yearlyIncome > 0 || $yearlyExpense > 0) ? ($yearlyIncome / ($yearlyIncome + $yearlyExpense) * 100) : 0; ?>%" 
                                                         aria-valuenow="<?php echo $yearlyIncome; ?>" aria-valuemin="0" 
                                                         aria-valuemax="<?php echo $yearlyIncome + $yearlyExpense; ?>">
                                                        Income
                                                    </div>
                                                    <div class="progress-bar bg-danger" role="progressbar" 
                                                         style="width: <?php echo ($yearlyIncome > 0 || $yearlyExpense > 0) ? ($yearlyExpense / ($yearlyIncome + $yearlyExpense) * 100) : 0; ?>%" 
                                                         aria-valuenow="<?php echo $yearlyExpense; ?>" aria-valuemin="0" 
                                                         aria-valuemax="<?php echo $yearlyIncome + $yearlyExpense; ?>">
                                                        Expenses
                                                    </div>
                                                </div>
                                                <small class="text-muted">Income-Expense Distribution</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">5-Year Profit Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="fiveYearTrendChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly and Quarterly Analysis -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Monthly Financial Performance</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyFinancialChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Quarterly Analysis</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="quarterlyPieChart" height="200"></canvas>
                                
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Quarter</th>
                                                <th>Profit/Loss</th>
                                                <th>% of Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            foreach ($quarterlyData as $quarter => $data): 
                                                $quarterProfit = $data['income'] - $data['expense'];
                                                $percentage = ($profit != 0) ? ($quarterProfit / abs($profit)) * 100 : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo $quarter; ?></td>
                                                    <td class="<?php echo $quarterProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo formatCurrency($quarterProfit); ?>
                                                    </td>
                                                    <td><?php echo number_format($percentage, 1); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Category Analysis -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Income Categories</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($incomeByType) > 0): ?>
                                    <canvas id="incomeTypeChart" height="200"></canvas>
                                    
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Amount</th>
                                                    <th>% of Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                foreach ($incomeByType as $type => $amount): 
                                                    $percentage = ($yearlyIncome > 0) ? ($amount / $yearlyIncome) * 100 : 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo ucwords(str_replace('_', ' ', $type)); ?></td>
                                                        <td><?php echo formatCurrency($amount); ?></td>
                                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No income data available for this year.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Expense Categories</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($expensesByType) > 0): ?>
                                    <canvas id="expenseTypeChart" height="200"></canvas>
                                    
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th>Amount</th>
                                                    <th>% of Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                foreach ($expensesByType as $type => $amount): 
                                                    $percentage = ($yearlyExpense > 0) ? ($amount / $yearlyExpense) * 100 : 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo ucwords(str_replace('_', ' ', $type)); ?></td>
                                                        <td><?php echo formatCurrency($amount); ?></td>
                                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No expense data available for this year.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Breakdown -->
                <h6 class="border-bottom pb-2 mb-3">Monthly Breakdown</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Income</th>
                                <th>Expenses</th>
                                <th>Profit/Loss</th>
                                <th>Profit Margin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasData = false;
                            foreach ($monthlyData as $month => $data): 
                                if ($data['income'] > 0 || $data['expense'] > 0):
                                    $hasData = true;
                                    $monthProfit = $data['income'] - $data['expense'];
                                    $monthMargin = ($data['income'] > 0) ? ($monthProfit / $data['income']) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo $data['name']; ?></td>
                                    <td class="text-success"><?php echo formatCurrency($data['income']); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($data['expense']); ?></td>
                                    <td class="<?php echo $monthProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($monthProfit); ?>
                                    </td>
                                    <td class="<?php echo $monthMargin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($monthMargin, 1); ?>%
                                    </td>
                                    <td>
                                        <a href="monthly.php?month=<?php echo str_pad($month, 2, '0', STR_PAD_LEFT); ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
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
                                    <td colspan="6" class="text-center">No financial data available for this year</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Total</th>
                                <th class="text-success"><?php echo formatCurrency($yearlyIncome); ?></th>
                                <th class="text-danger"><?php echo formatCurrency($yearlyExpense); ?></th>
                                <th class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($profit); ?>
                                </th>
                                <th class="<?php echo $profitMargin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($profitMargin, 1); ?>%
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
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?year=' . ($year - 1)); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left me-1"></i> <?php echo $year - 1; ?>
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?year=' . date('Y')); ?>" class="btn btn-outline-secondary">
                                Current Year
                            </a>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?year=' . ($year + 1)); ?>" class="btn btn-outline-primary">
                                <?php echo $year + 1; ?> <i class="fas fa-chevron-right ms-1"></i>
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
        // Monthly financial data
        monthly: {
            labels: <?php echo json_encode(array_column($monthlyData, 'name')); ?>,
            income: <?php echo json_encode(array_column($monthlyData, 'income')); ?>,
            expense: <?php echo json_encode(array_column($monthlyData, 'expense')); ?>,
            profit: <?php echo json_encode(array_column($monthlyData, 'profit')); ?>
        },
        
        // Quarterly data
        quarterly: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4'],
            income: [
                <?php echo $quarterlyData['Q1']['income']; ?>,
                <?php echo $quarterlyData['Q2']['income']; ?>,
                <?php echo $quarterlyData['Q3']['income']; ?>,
                <?php echo $quarterlyData['Q4']['income']; ?>
            ],
            expense: [
                <?php echo $quarterlyData['Q1']['expense']; ?>,
                <?php echo $quarterlyData['Q2']['expense']; ?>,
                <?php echo $quarterlyData['Q3']['expense']; ?>,
                <?php echo $quarterlyData['Q4']['expense']; ?>
            ],
            profit: [
                <?php echo $quarterlyData['Q1']['profit']; ?>,
                <?php echo $quarterlyData['Q2']['profit']; ?>,
                <?php echo $quarterlyData['Q3']['profit']; ?>,
                <?php echo $quarterlyData['Q4']['profit']; ?>
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
        
        // Five-year trend data
        fiveYearTrend: {
            labels: <?php echo json_encode(array_column($fiveYearTrend, 'year')); ?>,
            income: <?php echo json_encode(array_column($fiveYearTrend, 'income')); ?>,
            expense: <?php echo json_encode(array_column($fiveYearTrend, 'expense')); ?>,
            profit: <?php echo json_encode(array_column($fiveYearTrend, 'profit')); ?>
        }
    };
    
    // Currency formatter for chart
    const currencyFormatter = {
        callback: function(value) {
            return 'Rs. ' + value.toLocaleString();
        }
    };
    
    // 1. Monthly Financial Chart
    if (document.getElementById('monthlyFinancialChart')) {
        const monthlyCtx = document.getElementById('monthlyFinancialChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: chartData.monthly.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.monthly.income,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1,
                        order: 2
                    },
                    {
                        label: 'Expense',
                        data: chartData.monthly.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1,
                        order: 3
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.monthly.profit,
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
                        ticks: currencyFormatter
                    }
                }
            }
        });
    }
    
    // 2. Five-Year Trend Chart
    if (document.getElementById('fiveYearTrendChart')) {
        const trendCtx = document.getElementById('fiveYearTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.fiveYearTrend.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.fiveYearTrend.income,
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 2,
                        tension: 0.1
                    },
                    {
                        label: 'Expense',
                        data: chartData.fiveYearTrend.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.2)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 2,
                        tension: 0.1
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.fiveYearTrend.profit,
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
                        ticks: currencyFormatter
                    }
                }
            }
        });
    }
    
    // 3. Quarterly Pie Chart
    if (document.getElementById('quarterlyPieChart')) {
        const quarterlyCtx = document.getElementById('quarterlyPieChart').getContext('2d');
        new Chart(quarterlyCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.quarterly.labels,
                datasets: [
                    {
                        data: chartData.quarterly.profit,
                        backgroundColor: [
                            'rgba(13, 110, 253, 0.7)',
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderColor: [
                            'rgb(13, 110, 253)',
                            'rgb(40, 167, 69)',
                            'rgb(255, 193, 7)',
                            'rgb(220, 53, 69)'
                        ],
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
                                return `${label}: ${new Intl.NumberFormat('en-US', { 
                                    style: 'currency', 
                                    currency: 'LKR',
                                    maximumFractionDigits: 0
                                }).format(value)}`;
                            }
                        }
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
    
    // Auto-submit form on year change
    document.getElementById('year').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>