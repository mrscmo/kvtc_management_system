<?php
// Set page title
$page_title = "Weekly Expenses Report";

// Include header
include_once __DIR__ . '/../../../includes/header.php';
include_once __DIR__ . '/../../../includes/functions.php';

// Get the week start and end dates
$week = isset($_GET['week']) ? intval($_GET['week']) : date('W');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate the first and last day of the week
$dto = new DateTime();
$dto->setISODate($year, $week);
$startDate = $dto->format('Y-m-d');
$dto->modify('+6 days');
$endDate = $dto->format('Y-m-d');

// Get expenses data for the week
$query = "SELECT e.*, s.student_id as student_number, s.full_name as student_name 
          FROM expenses e
          LEFT JOIN students s ON e.student_id = s.id
          WHERE e.expense_date BETWEEN ? AND ?
          ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total expenses for the week
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

$dailyData = [];
$dailyLabels = [];
$daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Initialize daily data with zeros
$currentDate = new DateTime($startDate);
for ($i = 0; $i < 7; $i++) {
    $day = $currentDate->format('Y-m-d');
    $dayOfWeek = $daysOfWeek[$currentDate->format('w')];
    $dailyLabels[$day] = $dayOfWeek . ' (' . $currentDate->format('d/m') . ')';
    $dailyData[$day] = 0;
    $currentDate->modify('+1 day');
}

// Fill in the actual data
while ($row = $dailyResult->fetch_assoc()) {
    $dailyData[$row['day']] = $row['total'];
}

// Get income for the same week for comparison
$incomeQuery = "SELECT SUM(amount) as total FROM income WHERE income_date BETWEEN ? AND ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$incomeResult = $stmt->get_result();
$weeklyIncome = $incomeResult->fetch_assoc()['total'] ?? 0;

// Calculate profit/loss
$profit = $weeklyIncome - $totalExpenses;

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="weekly_expenses_report_' . $year . '_week_' . $week . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Weekly Expenses Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Weekly Expenses Report - Week <?php echo $week; ?>, <?php echo $year; ?> (<?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?>)</h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Expenses
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
                
                <!-- Expenses Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Weekly Expenses Summary</h6>
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
                                        <p class="text-muted text-center mt-3">No expenses recorded for this week</p>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Weekly Financial Summary:</h6>
                                    <table class="table table-bordered mt-2">
                                        <tr>
                                            <td>Total Income:</td>
                                            <td class="text-end text-success"><?php echo formatCurrency($weeklyIncome); ?></td>
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
                                        <a href="../../income/reports/weekly.php?week=<?php echo $week; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-exchange-alt me-1"></i> View Income Report for This Week
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
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
                
                <!-- Daily Breakdown -->
                <h6 class="border-bottom pb-2 mb-3">Daily Breakdown</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Date</th>
                                <th>Expenses</th>
                                <th>% of Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentDate = new DateTime($startDate);
                            for ($i = 0; $i < 7; $i++):
                                $day = $currentDate->format('Y-m-d');
                                $dayName = $daysOfWeek[$currentDate->format('w')];
                                $dayAmount = $dailyData[$day];
                                $percentage = ($totalExpenses > 0) ? ($dayAmount / $totalExpenses) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo $dayName; ?></td>
                                    <td><?php echo formatDate($day); ?></td>
                                    <td><?php echo formatCurrency($dayAmount); ?></td>
                                    <td><?php echo number_format($percentage, 2) . '%'; ?></td>
                                    <td>
                                        <a href="daily.php?date=<?php echo $day; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                $currentDate->modify('+1 day');
                            endfor; 
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <th colspan="2">Total</th>
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
                                while ($row = $result->fetch_assoc()) { 
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
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No expense records found for the selected week</td>
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

<!-- JavaScript for Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Get the canvas element
    const ctx = document.getElementById('dailyExpensesChart').getContext('2d');
    
    // Chart data
    const dailyData = <?php echo json_encode(array_values($dailyData)); ?>;
    const labels = <?php echo json_encode(array_values($dailyLabels)); ?>;
    
    // Create the chart
    const dailyExpensesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Daily Expenses',
                    data: dailyData,
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
include_once __DIR__ . '/../../../includes/footer.php';
?>