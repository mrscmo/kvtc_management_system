<?php
// Set page title
$page_title = "Academic Staff Report";

// Include header
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/functions.php';

// Get academic staff with their batch assignments
$query = "SELECT s.*, 
          COUNT(DISTINCT sb.batch_id) as assigned_batches,
          SUM(CASE WHEN sb.status = 'active' THEN 1 ELSE 0 END) as active_batches,
          (SELECT SUM(amount) FROM staff_salary WHERE staff_id = s.id AND payment_status = 'paid') as total_paid_salary,
          (SELECT COUNT(*) FROM students st 
           JOIN batches b ON st.batch_id = b.id 
           JOIN staff_batch sb2 ON b.id = sb2.batch_id 
           WHERE sb2.staff_id = s.id) as total_students
          FROM staff s
          LEFT JOIN staff_batch sb ON s.id = sb.staff_id
          WHERE s.staff_type = 'academic'
          GROUP BY s.id
          ORDER BY s.name";
$result = $conn->query($query);

// Calculate totals
$totalStaff = $result->num_rows;
$totalSalary = 0;
$totalStudents = 0;
$totalBatches = 0;

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="academic_staff_report_' . date('Y-m-d') . '.pdf"');
    // In a real implementation, you would generate PDF here
    exit;
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Academic Staff Report -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Academic Staff Report</h5>
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
                                <h6 class="text-primary mb-2">Total Academic Staff</h6>
                                <h4><?php echo $totalStaff; ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-success mb-2">Total Batches Assigned</h6>
                                <h4 id="totalBatches">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info mb-2">Total Students</h6>
                                <h4 id="totalStudents">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-warning mb-2">Total Salary per Month</h6>
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
                                <th>Active Batches</th>
                                <th>Total Students</th>
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
                                    $totalStudents += $row['total_students'];
                                    $totalBatches += $row['active_batches'];
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
                                    </td>
                                    <td><?php echo formatCurrency($row['monthly_salary']); ?></td>
                                    <td>
                                        <?php if ($row['active_batches'] > 0): ?>
                                            <span class="badge bg-primary"><?php echo $row['active_batches']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['total_students'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $row['total_students']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
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
                                    <td colspan="9" class="text-center">No academic staff members found</td>
                                </tr>
                            <?php 
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update summary totals
    document.getElementById('totalBatches').textContent = '<?php echo $totalBatches; ?>';
    document.getElementById('totalStudents').textContent = '<?php echo $totalStudents; ?>';
    document.getElementById('totalSalary').textContent = '<?php echo formatCurrency($totalSalary); ?>';
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>