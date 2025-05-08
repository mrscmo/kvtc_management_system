<?php
// Set page title
$page_title = "Monthly Expenses Report";

// Include header
include_once __DIR__ . '/../../../includes/header.php';
include_once __DIR__ . '/../../../includes/functions.php';

// Default to current month if not specified
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Calculate the first and last day of the month
$startDate = date('Y-m-01', strtotime("$year-$month-01"));
$endDate = date('Y-m-t', strtotime("$year-$month-01"));

// Get expenses data for the month
$query = "SELECT e.*, s.student_id as student_number, s.full_name as student_name 
          FROM expenses e
          LEFT JOIN students s ON e.student_id = s.id
          WHERE e.expense_date BETWEEN ? AND ?
          ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total expenses for the month
$totalQuery = "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalExpenses = $totalResult->fetch_assoc()['total'] ?? 0;

// Get expenses by type
$typeQuery = "SELECT expense_type, SUM(amount) as total 
              FROM expenses 
              WHERE expense_date BETWEEN ? AND ?
              GROUP BY expense_type";
$stmt = $conn->prepare($typeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$typeResult = $stmt->get_result();

$expensesByType = [];
while ($row = $typeResult->fetch_assoc()) {
    $expensesByType[$row['expense_type']] = $row['total'];
}

// Get daily expenses data for chart
$dailyQuery = "SELECT DATE(expense_date) as day, SUM(amount) as total
               FROM expenses
               WHERE expense_date BETWEEN ? AND ?
               GROUP BY DATE(expense_date)
               ORDER BY DATE(expense_date)";
$stmt = $conn->prepare($dailyQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$dailyResult = $stmt->get_result();

$daysInMonth = date('t', strtotime("$year-$month-01"));
$dailyData = array_fill(1, $daysInMonth, 0);
$dailyLabels = [];

// Create labels for all days in month
for ($i = 1; $i <= $daysInMonth; $i++) {
    $dayDate = "$year-$month-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $dailyLabels[$i] = $i; // Just the day number for brevity
}

// Fill in the actual data
while ($row = $dailyResult->fetch_assoc()) {
    $day = intval(date('j', strtotime($row['day'])));
    $dailyData[$day] = $row['total'];
}

// Get weekly expenses data for comparison
$weeklyData = array_fill(1, 5, 0); // Up to 5 weeks in a month
$weeklyLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];

// Calculate weekly totals
$currentDate = new DateTime($startDate);
$currentWeek = 1;
$endDateTime = new DateTime($endDate);

while ($currentDate <= $endDateTime) {
    $dayOfMonth = $currentDate->format('j');
    $weekNum = ceil($dayOfMonth / 7);
    if ($weekNum > 5) $weekNum = 5; // Cap at week 5
    
    $dayStr = $currentDate->format('Y-m-d');
    if (isset($dailyData[intval($currentDate->format('j'))])) {
        $weeklyData[$weekNum] += $dailyData[intval($currentDate->format('j'))];
    }
    
    $currentDate->modify('+1 day');
}

// Get income for the same month for comparison
$incomeQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$incomeResult = $stmt->get_result();
$monthlyIncome = $incomeResult->fetch_assoc()['total'] ?? 0;

// Calculate profit/loss
$profit = $monthlyIncome - $totalExpenses;

// Get expenses by student type (with vs without student association)
$studentTypeQuery = "SELECT 
                        CASE WHEN student_id IS NULL THEN 'Without Student' ELSE 'With Student' END as student_type,
                        SUM(amount) as total
                     FROM expenses 
                     WHERE expense_date BETWEEN ? AND ?
                     GROUP BY student_type";
$stmt = $conn->prepare($studentTypeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$studentTypeResult = $stmt->get_result();

$expensesByStudentType = [
    'With Student' => 0,
    'Without Student' => 0
];

while ($row = $studentTypeResult->fetch_assoc()) {
    $expensesByStudentType[$row['student_type']] = $row['total'];
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="monthly_expenses_report_' . $year . '_' . $month . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Monthly Expenses Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Monthly Expenses Report - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Expenses
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
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Monthly Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <td>Total Income:</td>
                                        <td class="text-end text-success"><?php echo formatCurrency($monthlyIncome); ?></td>
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
                                    <a href="../../income/reports/monthly.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-exchange-alt me-1"></i> View Income Report
                                    </a>
                                </div>
                                
                                <hr>
                                
                                <h6 class="mt-4">Expenses by Association:</h6>
                                <canvas id="associationPieChart" height="180"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Daily Expenses Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyExpensesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expenses Breakdown -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Expenses by Type</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($expensesByType) > 0): ?>
                                    <canvas id="expenseTypeChart" height="250"></canvas>
                                <?php else: ?>
                                    <p class="text-muted text-center mt-3">No expenses recorded for this month</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Weekly Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="weeklyExpensesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weekly Breakdown -->
                <h6 class="border-bottom pb-2 mb-3">Weekly Breakdown</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Week</th>
                                <th>Expenses</th>
                                <th>% of Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalWeeks = 0;
                            for ($i = 1; $i <= 5; $i++):
                                if ($weeklyData[$i] > 0):
                                    $totalWeeks++;
                                    $percentage = ($totalExpenses > 0) ? ($weeklyData[$i] / $totalExpenses) * 100 : 0;
                                    
                                    // Calculate week start and end date
                                    $weekStart = ($i - 1) * 7 + 1;
                                    $weekEnd = min($i * 7, $daysInMonth);
                                    $weekStartDate = date('Y-m-d', strtotime("$year-$month-$weekStart"));
                                    $weekEndDate = date('Y-m-d', strtotime("$year-$month-$weekEnd"));
                            ?>
                                <tr>
                                    <td><?php echo "Week $i (" . formatDate($weekStartDate) . " - " . formatDate($weekEndDate) . ")"; ?></td>
                                    <td><?php echo formatCurrency($weeklyData[$i]); ?></td>
                                    <td><?php echo number_format($percentage, 2) . '%'; ?></td>
                                    <td>
                                        <a href="weekly.php?week=<?php echo date('W', strtotime($weekStartDate)); ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                                            View Week
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endfor;
                            
                            if ($totalWeeks == 0):
                            ?>
                                <tr>
                                    <td colspan="4" class="text-center">No expenses recorded for this month</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <th>Total</th>
                                <th><?php echo formatCurrency($totalExpenses); ?></th>
                                <th>100%</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Expenses List -->
                <h6 class="border-bottom pb-2 mb-3">Expenses Details</h6>
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
                                $maxRows = 30; // Limit to most recent 30 transactions
                                
                                while ($row = $result->fetch_assoc() && $counter <= $maxRows) { 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo formatDate($row['expense_date']); ?></td>
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
                                
                                if ($result->num_rows > $maxRows):
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <em>Showing the most recent <?php echo $maxRows; ?> transactions. The full dataset contains <?php echo $result->num_rows; ?> transactions.</em>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No expense records found for the selected month</td>
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
    const chartsData = {
        // Daily expenses chart data
        dailyData: {
            labels: <?php echo json_encode(array_values($dailyLabels)); ?>,
            values: <?php echo json_encode(array_values($dailyData)); ?>
        },
        
        // Weekly expenses chart data
        weeklyData: {
            labels: <?php echo json_encode(array_slice($weeklyLabels, 0, $totalWeeks)); ?>,
            values: <?php echo json_encode(array_slice(array_values($weeklyData), 0, $totalWeeks)); ?>
        },
        
        // Expense types chart data
        expenseTypesData: {
            labels: <?php echo json_encode(array_map(function($type) { 
                return ucwords(str_replace('_', ' ', $type)); 
            }, array_keys($expensesByType))); ?>,
            values: <?php echo json_encode(array_values($expensesByType)); ?>
        },
        
        // Student association data
        associationData: {
            labels: <?php echo json_encode(array_keys($expensesByStudentType)); ?>,
            values: <?php echo json_encode(array_values($expensesByStudentType)); ?>
        }
    };
    
    // Create chart color schemes
    const colorSchemes = {
        expenses: {
            backgroundColor: 'rgba(220, 53, 69, 0.5)',
            borderColor: 'rgb(220, 53, 69)'
        },
        types: [
            { backgroundColor: 'rgba(220, 53, 69, 0.7)', borderColor: 'rgb(220, 53, 69)' },
            { backgroundColor: 'rgba(255, 193, 7, 0.7)', borderColor: 'rgb(255, 193, 7)' },
            { backgroundColor: 'rgba(13, 110, 253, 0.7)', borderColor: 'rgb(13, 110, 253)' },
            { backgroundColor: 'rgba(25, 135, 84, 0.7)', borderColor: 'rgb(25, 135, 84)' },
            { backgroundColor: 'rgba(111, 66, 193, 0.7)', borderColor: 'rgb(111, 66, 193)' }
        ],
        association: [
            { backgroundColor: 'rgba(23, 162, 184, 0.7)', borderColor: 'rgb(23, 162, 184)' },
            { backgroundColor: 'rgba(108, 117, 125, 0.7)', borderColor: 'rgb(108, 117, 125)' }
        ]
    };
    
    // Currency formatter
    const currencyFormatter = {
        callback: function(value) {
            return 'Rs. ' + value.toLocaleString();
        }
    };
    
    // 1. Daily Expenses Chart
    if (document.getElementById('dailyExpensesChart')) {
        const dailyCtx = document.getElementById('dailyExpensesChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: chartsData.dailyData.labels,
                datasets: [{
                    label: 'Daily Expenses',
                    data: chartsData.dailyData.values,
                    backgroundColor: colorSchemes.expenses.backgroundColor,
                    borderColor: colorSchemes.expenses.borderColor,
                    borderWidth: 1
                }]
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
    
    // 2. Weekly Expenses Chart
    if (document.getElementById('weeklyExpensesChart')) {
        const weeklyCtx = document.getElementById('weeklyExpensesChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: chartsData.weeklyData.labels,
                datasets: [{
                    label: 'Weekly Expenses',
                    data: chartsData.weeklyData.values,
                    backgroundColor: colorSchemes.expenses.backgroundColor,
                    borderColor: colorSchemes.expenses.borderColor,
                    borderWidth: 1
                }]
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
    
    // 3. Expense Type Chart
    if (document.getElementById('expenseTypeChart') && chartsData.expenseTypesData.labels.length > 0) {
        const typesCtx = document.getElementById('expenseTypeChart').getContext('2d');
        
        // Prepare colors for each expense type
        const backgroundColors = [];
        const borderColors = [];
        
        for (let i = 0; i < chartsData.expenseTypesData.labels.length; i++) {
            const colorIndex = i % colorSchemes.types.length;
            backgroundColors.push(colorSchemes.types[colorIndex].backgroundColor);
            borderColors.push(colorSchemes.types[colorIndex].borderColor);
        }
        
        new Chart(typesCtx, {
            type: 'pie',
            data: {
                labels: chartsData.expenseTypesData.labels,
                datasets: [{
                    data: chartsData.expenseTypesData.values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
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
    
    // 4. Association Pie Chart
    if (document.getElementById('associationPieChart')) {
        const associationCtx = document.getElementById('associationPieChart').getContext('2d');
        
        new Chart(associationCtx, {
            type: 'doughnut',
            data: {
                labels: chartsData.associationData.labels,
                datasets: [{
                    data: chartsData.associationData.values,
                    backgroundColor: [
                        colorSchemes.association[0].backgroundColor,
                        colorSchemes.association[1].backgroundColor
                    ],
                    borderColor: [
                        colorSchemes.association[0].borderColor,
                        colorSchemes.association[1].borderColor
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
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
include_once __DIR__ . '/../../../includes/footer.php';
?>