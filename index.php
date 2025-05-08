<?php
// Set page title
$page_title = "Dashboard";

// Include header
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/functions.php';

// Get counts for dashboard
$totalStudentsQuery = "SELECT COUNT(*) as count FROM students";
$totalStudentsResult = $conn->query($totalStudentsQuery);
$totalStudents = $totalStudentsResult->fetch_assoc()['count'];

$totalCoursesQuery = "SELECT COUNT(*) as count FROM courses";
$totalCoursesResult = $conn->query($totalCoursesQuery);
$totalCourses = $totalCoursesResult->fetch_assoc()['count'];

$totalBatchesQuery = "SELECT COUNT(*) as count FROM batches";
$totalBatchesResult = $conn->query($totalBatchesQuery);
$totalBatches = $totalBatchesResult->fetch_assoc()['count'];

$activeBatchesQuery = "SELECT COUNT(*) as count FROM batches WHERE status = 'active'";
$activeBatchesResult = $conn->query($activeBatchesQuery);
$activeBatches = $activeBatchesResult->fetch_assoc()['count'];

// Get recent students
$recentStudentsQuery = "SELECT s.id, s.student_id, s.full_name, s.registration_date, c.course_name 
                        FROM students s 
                        JOIN courses c ON s.course_id = c.id 
                        ORDER BY s.id DESC LIMIT 5";
$recentStudentsResult = $conn->query($recentStudentsQuery);

// Get recent income
$recentIncomeQuery = "SELECT * FROM income ORDER BY id DESC LIMIT 5";
$recentIncomeResult = $conn->query($recentIncomeQuery);

// Get monthly income/expense for current year
$currentYear = date('Y');
$monthlySummaryQuery = "
    SELECT 
        MONTH(income_date) as month,
        SUM(amount) as income_total
    FROM income
    WHERE YEAR(income_date) = $currentYear
    GROUP BY MONTH(income_date)
    ORDER BY MONTH(income_date)
";
$monthlySummaryResult = $conn->query($monthlySummaryQuery);

$monthlyIncome = array_fill(0, 12, 0);
while ($row = $monthlySummaryResult->fetch_assoc()) {
    $monthlyIncome[$row['month'] - 1] = $row['income_total'];
}

$monthlyExpenseQuery = "
    SELECT 
        MONTH(expense_date) as month,
        SUM(amount) as expense_total
    FROM expenses
    WHERE YEAR(expense_date) = $currentYear
    GROUP BY MONTH(expense_date)
    ORDER BY MONTH(expense_date)
";
$monthlyExpenseResult = $conn->query($monthlyExpenseQuery);

$monthlyExpense = array_fill(0, 12, 0);
while ($row = $monthlyExpenseResult->fetch_assoc()) {
    $monthlyExpense[$row['month'] - 1] = $row['expense_total'];
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Dashboard Overview -->
<div class="row">
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="count"><?php echo $totalStudents; ?></div>
                <div class="label">Total Students</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="icon"><i class="fas fa-book"></i></div>
                <div class="count"><?php echo $totalCourses; ?></div>
                <div class="label">Total Courses</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="icon"><i class="fas fa-layer-group"></i></div>
                <div class="count"><?php echo $totalBatches; ?></div>
                <div class="label">Total Batches</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="count"><?php echo $activeBatches; ?></div>
                <div class="label">Active Batches</div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Overview and Recent Activity -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Financial Overview (<?php echo $currentYear; ?>)</h5>
            </div>
            <div class="card-body">
                <canvas id="financialChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Students</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentStudentsResult->num_rows > 0): ?>
                                <?php while ($student = $recentStudentsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $student['student_id']; ?></td>
                                        <td>
                                            <a href="/vocational_training_center/students/view_student.php?id=<?php echo $student['id']; ?>">
                                                <?php echo $student['full_name']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $student['course_name']; ?></td>
                                        <td><?php echo formatDate($student['registration_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No recent students found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Income</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentIncomeResult->num_rows > 0): ?>
                                <?php while ($income = $recentIncomeResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo ucfirst(str_replace('_', ' ', $income['income_type']));
                                            if ($income['income_type'] === 'other' && !empty($income['income_name'])) {
                                                echo ' - ' . $income['income_name'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatCurrency($income['amount']); ?></td>
                                        <td><?php echo formatDate($income['income_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No recent income found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Quick Links</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="/vocational_training_center/students/add_student.php" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus me-2"></i> Add New Student
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="/vocational_training_center/courses/add_course.php" class="btn btn-info w-100">
                            <i class="fas fa-plus-circle me-2"></i> Add New Course
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="/vocational_training_center/batches/add_batch.php" class="btn btn-success w-100">
                            <i class="fas fa-users-cog me-2"></i> Add New Batch
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="/vocational_training_center/finance/income/new_income.php" class="btn btn-warning w-100">
                            <i class="fas fa-money-bill-wave me-2"></i> Record Income
                        </a>
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
    const ctx = document.getElementById('financialChart').getContext('2d');
    
    // Chart data
    const monthlyIncomeData = <?php echo json_encode($monthlyIncome); ?>;
    const monthlyExpenseData = <?php echo json_encode($monthlyExpense); ?>;
    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Create the chart
    const financialChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Income',
                    data: monthlyIncomeData,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 1
                },
                {
                    label: 'Expense',
                    data: monthlyExpenseData,
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
});
</script>

<?php
// Include footer
include_once __DIR__ . '/includes/footer.php';
?>