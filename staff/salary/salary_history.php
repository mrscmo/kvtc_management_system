<?php
// Set page title
$page_title = "Salary History";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required";
    header("Location: ../index.php");
    exit();
}

$staff_id = $_GET['id'];

// Get staff details
$staff = getStaffById($staff_id);
if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found";
    header("Location: ../index.php");
    exit();
}

// Get salary history
$salaryHistory = getStaffSalaryHistory($staff_id);

// Calculate statistics
$totalPaid = 0;
$totalPending = 0;
$paidMonths = 0;
$pendingMonths = 0;

foreach ($salaryHistory as $salary) {
    if ($salary['payment_status'] == 'paid') {
        $totalPaid += $salary['amount'];
        $paidMonths++;
    } else {
        $totalPending += $salary['amount'];
        $pendingMonths++;
    }
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // In a real implementation, you would use a PDF library here
    // For this example, we're just setting headers to force a download
    
    $filename = "salary_history_" . str_replace(' ', '_', $staff['name']) . ".pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create PDF content here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Salary History for <?php echo htmlspecialchars($staff['name']); ?></h5>
                <div>
                    <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=pdf'; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-file-pdf me-1"></i> Export as PDF
                    </a>
                    <a href="../view_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Staff
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-primary mb-2">Monthly Salary</h6>
                                <h4><?php echo formatCurrency($staff['monthly_salary']); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-success mb-2">Total Paid</h6>
                                <h4><?php echo formatCurrency($totalPaid); ?></h4>
                                <small class="text-muted"><?php echo $paidMonths; ?> month(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-warning mb-2">Pending Payments</h6>
                                <h4><?php echo formatCurrency($totalPending); ?></h4>
                                <small class="text-muted"><?php echo $pendingMonths; ?> month(s)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-2">Total Months</h6>
                                <h4><?php echo count($salaryHistory); ?></h4>
                                <small class="text-muted">
                                    <?php 
                                    if (count($salaryHistory) > 0) {
                                        $firstMonth = end($salaryHistory)['payment_month'];
                                        $lastMonth = $salaryHistory[0]['payment_month'];
                                        echo date('M Y', strtotime($firstMonth . '-01')) . ' to ' . date('M Y', strtotime($lastMonth . '-01'));
                                    } else {
                                        echo 'No salary history';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Salary History Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($salaryHistory) > 0): 
                                $counter = 1;
                                foreach ($salaryHistory as $salary): 
                                    $monthYear = date('F Y', strtotime($salary['payment_month'] . '-01'));
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $monthYear; ?></td>
                                    <td><?php echo formatCurrency($salary['amount']); ?></td>
                                    <td>
                                        <?php 
                                        if ($salary['payment_status'] == 'paid') {
                                            echo formatDate($salary['payment_date']);
                                        } else {
                                            echo 'Pending';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($salary['payment_status'] == 'paid') ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($salary['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($salary['remarks'])) {
                                            echo '<span class="text-truncate d-inline-block" style="max-width: 150px;" title="' . htmlspecialchars($salary['remarks']) . '">';
                                            echo htmlspecialchars(substr($salary['remarks'], 0, 20)) . (strlen($salary['remarks']) > 20 ? '...' : '');
                                            echo '</span>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($salary['payment_status'] == 'pending'): ?>
                                            <a href="pay_salary.php?id=<?php echo $staff_id; ?>&month=<?php echo $salary['payment_month']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill-wave me-1"></i> Process
                                            </a>
                                        <?php else: ?>
                                            <a href="view_payment.php?id=<?php echo $salary['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center">No salary records found</td>
                                </tr>
                            <?php 
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Monthly Salary Chart -->
                <?php if (count($salaryHistory) > 0): ?>
                <div class="mt-4">
                    <h6 class="border-bottom pb-2 mb-3">Monthly Salary Trend</h6>
                    <canvas id="salaryChart" height="250"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Chart -->
<?php if (count($salaryHistory) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prepare data for chart
    const salaryData = [
        <?php
        // We need to reverse the array to show oldest to newest
        $reversedHistory = array_reverse($salaryHistory);
        foreach ($reversedHistory as $index => $salary) {
            echo '{';
            echo 'month: "' . date('M Y', strtotime($salary['payment_month'] . '-01')) . '",';
            echo 'amount: ' . $salary['amount'] . ',';
            echo 'status: "' . $salary['payment_status'] . '"';
            echo '}';
            if ($index < count($reversedHistory) - 1) {
                echo ',';
            }
        }
        ?>
    ];
    
    // Extract labels and data
    const labels = salaryData.map(item => item.month);
    const amounts = salaryData.map(item => item.amount);
    const statuses = salaryData.map(item => item.status);
    
    // Generate colors based on status
    const backgroundColors = statuses.map(status => 
        status === 'paid' ? 'rgba(40, 167, 69, 0.5)' : 'rgba(255, 193, 7, 0.5)'
    );
    
    const borderColors = statuses.map(status => 
        status === 'paid' ? 'rgb(40, 167, 69)' : 'rgb(255, 193, 7)'
    );
    
    // Create chart
    const ctx = document.getElementById('salaryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Monthly Salary',
                data: amounts,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1
            }]
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
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const status = statuses[context.dataIndex];
                            return [
                                'Amount: Rs. ' + context.raw.toLocaleString(),
                                'Status: ' + status.charAt(0).toUpperCase() + status.slice(1)
                            ];
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>