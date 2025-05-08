<?php
// Set page title
$page_title = "Yearly Income Report";

// Include header
include_once __DIR__ . '/../../../includes/header.php';
include_once __DIR__ . '/../../../includes/functions.php';

// Default to current year if not specified
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate the first and last day of the year
$startDate = "$year-01-01";
$endDate = "$year-12-31";

// Get income data for the year
$query = "SELECT i.*, s.student_id as student_number, s.full_name as student_name 
          FROM income i
          LEFT JOIN students s ON i.student_id = s.id
          WHERE YEAR(i.income_date) = ?
          ORDER BY i.income_date DESC, i.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total income for the year
$totalQuery = "SELECT SUM(amount) as total FROM income WHERE YEAR(income_date) = ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalIncome = $totalResult->fetch_assoc()['total'] ?? 0;

// Get income by type
$typeQuery = "SELECT income_type, SUM(amount) as total 
              FROM income 
              WHERE YEAR(income_date) = ?
              GROUP BY income_type";
$stmt = $conn->prepare($typeQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$typeResult = $stmt->get_result();

$incomeByType = [];
while ($row = $typeResult->fetch_assoc()) {
    $incomeByType[$row['income_type']] = $row['total'];
}

// Get monthly income data for chart
$monthlyQuery = "SELECT MONTH(income_date) as month, SUM(amount) as total
                FROM income
                WHERE YEAR(income_date) = ?
                GROUP BY MONTH(income_date)
                ORDER BY MONTH(income_date)";
$stmt = $conn->prepare($monthlyQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$monthlyResult = $stmt->get_result();

$monthlyData = array_fill(1, 12, 0); // Initialize with 0 for all 12 months
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyData[$row['month']] = $row['total'];
}

// Get quarterly data for pie chart
$quarterlyData = [0, 0, 0, 0]; // Initialize with 0 for all 4 quarters

// Calculate quarterly totals from monthly data
for ($i = 1; $i <= 12; $i++) {
    $quarter = ceil($i / 3) - 1; // 0-based index for quarters
    $quarterlyData[$quarter] += $monthlyData[$i];
}

// Get income by student type (with vs without student association)
$studentTypeQuery = "SELECT 
                        CASE WHEN student_id IS NULL THEN 'Without Student' ELSE 'With Student' END as student_type,
                        SUM(amount) as total
                     FROM income 
                     WHERE YEAR(income_date) = ?
                     GROUP BY student_type";
$stmt = $conn->prepare($studentTypeQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$studentTypeResult = $stmt->get_result();

$incomeByStudentType = [
    'With Student' => 0,
    'Without Student' => 0
];

while ($row = $studentTypeResult->fetch_assoc()) {
    $incomeByStudentType[$row['student_type']] = $row['total'];
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="yearly_income_report_' . $year . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Yearly Income Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Yearly Income Report - <?php echo $year; ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Income
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
                
                <!-- Income Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Yearly Income Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4>Total Income:</h4>
                                    <h4 class="text-success"><?php echo formatCurrency($totalIncome); ?></h4>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Income by Type:</h6>
                                    <?php if (count($incomeByType) > 0): ?>
                                        <ul class="list-group mt-2">
                                            <?php foreach ($incomeByType as $type => $amount): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                                    <span class="badge bg-success rounded-pill"><?php echo formatCurrency($amount); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted text-center mt-3">No income recorded for this year</p>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="mt-3">
                                    <h6>Income by Student Association:</h6>
                                    <ul class="list-group mt-2">
                                        <?php foreach ($incomeByStudentType as $type => $amount): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo $type; ?>
                                                <span class="badge bg-info rounded-pill"><?php echo formatCurrency($amount); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Monthly Income Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyIncomeChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quarterly Analysis -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Quarterly Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="quarterlyPieChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Quarterly Analysis</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Quarter</th>
                                            <th>Period</th>
                                            <th>Amount</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $quarters = [
                                            'Q1' => 'Jan - Mar',
                                            'Q2' => 'Apr - Jun',
                                            'Q3' => 'Jul - Sep',
                                            'Q4' => 'Oct - Dec'
                                        ];
                                        
                                        for ($i = 0; $i < 4; $i++): 
                                            $percentage = ($totalIncome > 0) ? ($quarterlyData[$i] / $totalIncome) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo 'Q' . ($i + 1); ?></td>
                                                <td><?php echo $quarters['Q' . ($i + 1)]; ?></td>
                                                <td><?php echo formatCurrency($quarterlyData[$i]); ?></td>
                                                <td><?php echo number_format($percentage, 2) . '%'; ?></td>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th colspan="2">Total</th>
                                            <th><?php echo formatCurrency($totalIncome); ?></th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
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
                                <th>% of Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $months = [
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ];
                            
                            foreach ($months as $monthNum => $monthName): 
                                $monthAmount = $monthlyData[$monthNum];
                                $percentage = ($totalIncome > 0) ? ($monthAmount / $totalIncome) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo $monthName; ?></td>
                                    <td><?php echo formatCurrency($monthAmount); ?></td>
                                    <td><?php echo number_format($percentage, 2) . '%'; ?></td>
                                    <td>
                                        <a href="monthly.php?month=<?php echo str_pad($monthNum, 2, '0', STR_PAD_LEFT); ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th>Total</th>
                                <th><?php echo formatCurrency($totalIncome); ?></th>
                                <th>100%</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Income List -->
                <h6 class="border-bottom pb-2 mb-3">Recent Income Transactions</h6>
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
                                $maxRows = 20; // Limit to most recent 20 transactions
                                
                                while ($row = $result->fetch_assoc() && $counter <= $maxRows) { 
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
                                    <td colspan="6" class="text-center">No income records found for the selected year</td>
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
    // Monthly Income Chart
    const monthlyCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
    
    // Monthly data
    const monthlyData = <?php echo json_encode(array_values($monthlyData)); ?>;
    const monthLabels = ['January', 'February', 'March', 'April', 'May', 'June', 
                         'July', 'August', 'September', 'October', 'November', 'December'];
    
    // Create the monthly chart
    const monthlyIncomeChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Monthly Income',
                    data: monthlyData,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgb(40, 167, 69)',
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
    
    // Quarterly Pie Chart
    const quarterlyCtx = document.getElementById('quarterlyPieChart').getContext('2d');
    
    // Quarterly data
    const quarterlyData = <?php echo json_encode($quarterlyData); ?>;
    const quarterLabels = ['Q1 (Jan-Mar)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dec)'];
    
    // Create the quarterly pie chart
    const quarterlyPieChart = new Chart(quarterlyCtx, {
        type: 'pie',
        data: {
            labels: quarterLabels,
            datasets: [
                {
                    data: quarterlyData,
                    backgroundColor: [
                        'rgba(0, 123, 255, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgb(0, 123, 255)',
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
    
    // Auto-submit form on year change
    document.getElementById('year').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../../includes/footer.php';
?>