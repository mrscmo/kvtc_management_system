<?php
// Set page title
$page_title = "Non-Academic Staff Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Get non-academic staff data
$query = "SELECT s.*, 
          (SELECT SUM(amount) FROM staff_salary WHERE staff_id = s.id AND payment_status = 'paid') as total_paid_salary,
          (SELECT COUNT(*) FROM staff_salary WHERE staff_id = s.id AND payment_status = 'paid') as paid_months
          FROM staff s
          WHERE s.staff_type = 'non_academic'
          ORDER BY s.name";
$result = $conn->query($query);

// Calculate totals
$totalStaff = $result->num_rows;
$totalSalary = 0;
$permanentStaff = 0;
$trainingStaff = 0;
$totalPaidSalary = 0;

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="non_academic_staff_report_' . date('Y-m-d') . '.pdf"');
    // In a real implementation, you would generate PDF here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Non-Academic Staff Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Non-Academic Staff Report</h5>
                <div>
                    <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=pdf'; ?>" class="btn btn-outline-secondary export-pdf">
                        <i class="fas fa-file-pdf me-1"></i> Export as PDF
                    </a>
                    <a href="../index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Staff
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-primary mb-2">Total Non-Academic Staff</h6>
                                <h4><?php echo $totalStaff; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-success mb-2">Permanent Staff</h6>
                                <h4 id="permanentStaff">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-warning mb-2">Training Staff</h6>
                                <h4 id="trainingStaff">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-2">Total Monthly Salary</h6>
                                <h4 id="totalSalary">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Staff List -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>NIC Number</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Monthly Salary</th>
                                <th>Total Paid</th>
                                <th>Paid Months</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result->num_rows > 0) {
                                $counter = 1;
                                $result->data_seek(0); // Reset pointer
                                while ($row = $result->fetch_assoc()) { 
                                    $totalSalary += $row['monthly_salary'];
                                    $totalPaidSalary += $row['total_paid_salary'] ?? 0;
                                    if ($row['job_status'] == 'permanent') {
                                        $permanentStaff++;
                                    } else {
                                        $trainingStaff++;
                                    }
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nic_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($row['job_status'] == 'permanent') ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($row['job_status']); ?>
                                        </span>
                                        <?php if ($row['job_status'] == 'training' && !empty($row['training_end_date'])): ?>
                                            <br><small class="text-muted">Until: <?php echo formatDate($row['training_end_date']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatCurrency($row['monthly_salary']); ?></td>
                                    <td><?php echo formatCurrency($row['total_paid_salary'] ?? 0); ?></td>
                                    <td><?php echo $row['paid_months'] ?? 0; ?></td>
                                    <td>
                                        <a href="../view_staff.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="9" class="text-center">No non-academic staff members found</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="5" class="text-end">Totals:</th>
                                <th><?php echo formatCurrency($totalSalary); ?></th>
                                <th><?php echo formatCurrency($totalPaidSalary); ?></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update summary totals
    document.getElementById('permanentStaff').textContent = '<?php echo $permanentStaff; ?>';
    document.getElementById('trainingStaff').textContent = '<?php echo $trainingStaff; ?>';
    document.getElementById('totalSalary').textContent = '<?php echo formatCurrency($totalSalary); ?>';
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>