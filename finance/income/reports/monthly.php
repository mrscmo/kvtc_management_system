<?php
// Set page title
$page_title = "Monthly Income Report";

// Include header
include_once __DIR__ . '/../../../includes/header.php';
include_once __DIR__ . '/../../../includes/functions.php';

// Default to current month if not specified
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Calculate the first and last day of the month
$startDate = date('Y-m-01', strtotime("$year-$month-01"));
$endDate = date('Y-m-t', strtotime("$year-$month-01"));

// Get income data for the month
$query = "SELECT i.*, s.student_id as student_number, s.full_name as student_name 
          FROM income i
          LEFT JOIN students s ON i.student_id = s.id
          WHERE i.income_date BETWEEN ? AND ?
          ORDER BY i.income_date DESC, i.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total income for the month
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

// Get daily income data for chart
$dailyQuery = "SELECT DATE(income_date) as day, SUM(amount) as total
               FROM income
               WHERE income_date BETWEEN ? AND ?
               GROUP BY DATE(income_date)
               ORDER BY DATE(income_date)";
$stmt = $conn->prepare($dailyQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$dailyResult = $stmt->get_result();

$dailyData = [];
$labels = [];
while ($row = $dailyResult->fetch_assoc()) {
    $labels[] = formatDate($row['day']);
    $dailyData[] = $row['total'];
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="monthly_income_report_' . $year . '_' . $month . '.pdf"');
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Monthly Income Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Monthly Income Report - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h5>
                <div>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Income
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
                
                <!-- Income Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Monthly Income Summary</h6>
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
                                <h6 class="mb-0">Daily Income Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyIncomeChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Income List -->
                <h6 class="border-bottom pb-2 mb-3">Income Details</h6>
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
                                    <td colspan="6" class="text-center">No income records found for the selected month</td>
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

<!-- JavaScript for Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Get the canvas element
    const ctx = document.getElementById('dailyIncomeChart').getContext('2d');
    
    // Chart data
    const dailyData = <?php echo json_encode($dailyData); ?>;
    const labels = <?php echo json_encode($labels); ?>;
    
    // Create the chart
    const dailyIncomeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Daily Income',
                    data: dailyData,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(40, 167, 69)',
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