<?php
// Set page title
$page_title = "View Batch";

// Include header
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Batch ID is required";
    header("Location: index.php");
    exit();
}

$batch_id = $_GET['id'];

// Get batch details
$query = "SELECT b.*, c.course_name, c.duration, c.course_fee 
          FROM batches b
          JOIN courses c ON b.course_id = c.id
          WHERE b.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Batch not found";
    header("Location: index.php");
    exit();
}

$batch = $result->fetch_assoc();

// Get students in this batch
$studentsQuery = "SELECT s.id, s.student_id, s.full_name, s.contact_number, s.registration_date
                  FROM students s
                  WHERE s.batch_id = ?
                  ORDER BY s.full_name";
$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$studentsResult = $stmt->get_result();
$studentCount = $studentsResult->num_rows;

// Calculate batch progress
$startDate = new DateTime($batch['start_date']);
$endDate = new DateTime($batch['end_date']);
$currentDate = new DateTime();

$totalDays = $startDate->diff($endDate)->days;
$elapsedDays = ($currentDate > $startDate) ? $startDate->diff($currentDate)->days : 0;

// Ensure we don't exceed 100%
$progress = min(($elapsedDays / max(1, $totalDays)) * 100, 100);

// Check if batch has started
$batchStarted = $currentDate >= $startDate;

// Check if batch has ended
$batchEnded = $currentDate > $endDate;

// Get batch financial summary
$feesQuery = "SELECT SUM(f.amount) as total_fees
              FROM fees f
              JOIN students s ON f.student_id = s.id
              WHERE s.batch_id = ?";
$stmt = $conn->prepare($feesQuery);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$feesResult = $stmt->get_result();
$totalFees = $feesResult->fetch_assoc()['total_fees'] ?? 0;

// Calculate expected total fees
$expectedTotalFees = $batch['course_fee'] * $studentCount;
$feesPercentage = ($expectedTotalFees > 0) ? ($totalFees / $expectedTotalFees) * 100 : 0;

// Get course fee collection by student
$studentFeesQuery = "SELECT s.id, s.student_id, s.full_name, 
                          COALESCE(SUM(CASE WHEN f.fee_type = 'course_fee' THEN f.amount ELSE 0 END), 0) as paid_course_fee
                     FROM students s
                     LEFT JOIN fees f ON s.id = f.student_id AND f.fee_type = 'course_fee'
                     WHERE s.batch_id = ?
                     GROUP BY s.id
                     ORDER BY s.full_name";
$stmt = $conn->prepare($studentFeesQuery);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$studentFeesResult = $stmt->get_result();
$studentFees = [];

while ($row = $studentFeesResult->fetch_assoc()) {
    $studentFees[$row['id']] = [
        'student_id' => $row['student_id'],
        'full_name' => $row['full_name'],
        'paid_course_fee' => $row['paid_course_fee'],
        'remaining_fee' => $batch['course_fee'] - $row['paid_course_fee']
    ];
}
?>

<!-- Display success/error messages -->
<?php 
displaySuccessMessage();
displayErrorMessage();
?>

<!-- Batch Details -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Batch Details</h5>
                <div>
                    <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-sm btn-secondary ms-1">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 35%">Batch Name</th>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Batch Number</th>
                                <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Course</th>
                                <td>
                                    <a href="../courses/view_course.php?id=<?php echo $batch['course_id']; ?>">
                                        <?php echo htmlspecialchars($batch['course_name']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td><?php echo htmlspecialchars($batch['duration']); ?></td>
                            </tr>
                            <tr>
                                <th>Course Fee</th>
                                <td><?php echo formatCurrency($batch['course_fee']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 35%">Start Date</th>
                                <td><?php echo formatDate($batch['start_date']); ?></td>
                            </tr>
                            <tr>
                                <th>End Date</th>
                                <td><?php echo formatDate($batch['end_date']); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php if ($batch['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Installments</th>
                                <td><?php echo $batch['installments']; ?></td>
                            </tr>
                            <tr>
                                <th>Certificate Issue</th>
                                <td>
                                    <?php if ($batch['certificate_issue'] == 'yes'): ?>
                                        <span class="badge bg-info">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 35%">Pre-Assignment Date</th>
                                <td><?php echo $batch['pre_assignment_date'] ? formatDate($batch['pre_assignment_date']) : 'Not set'; ?></td>
                            </tr>
                            <tr>
                                <th>Final Assignment Date</th>
                                <td><?php echo $batch['final_assignment_date'] ? formatDate($batch['final_assignment_date']) : 'Not set'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Batch Progress -->
                <div class="mt-4">
                    <h6>Batch Progress</h6>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar <?php echo $batchEnded ? 'bg-success' : 'bg-primary'; ?>" 
                             role="progressbar" 
                             style="width: <?php echo $progress; ?>%;" 
                             aria-valuenow="<?php echo $progress; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo round($progress); ?>%
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small><?php echo formatDate($batch['start_date']); ?></small>
                        <small><?php echo formatDate($batch['end_date']); ?></small>
                    </div>
                    <div class="text-center mt-2">
                        <?php if (!$batchStarted): ?>
                            <span class="badge bg-warning">Not Started Yet</span>
                        <?php elseif ($batchEnded): ?>
                            <span class="badge bg-success">Completed</span>
                        <?php else: ?>
                            <span class="badge bg-info">In Progress</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Batch Statistics</h5>
            </div>
            <div class="card-body">
                <div class="card mb-3">
                    <div class="card-body bg-light text-center">
                        <h2 class="mb-0"><?php echo $studentCount; ?></h2>
                        <p class="text-muted mb-0">Enrolled Students</p>
                    </div>
                </div>
                
                <!-- Fee Collection Progress -->
                <div class="mt-3">
                    <h6>Fee Collection Progress</h6>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" 
                             role="progressbar" 
                             style="width: <?php echo $feesPercentage; ?>%;" 
                             aria-valuenow="<?php echo $feesPercentage; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo round($feesPercentage); ?>%
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small>Collected: <?php echo formatCurrency($totalFees); ?></small>
                        <small>Expected: <?php echo formatCurrency($expectedTotalFees); ?></small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Batch Timeline</h6>
                    <ul class="list-group">
                        <?php if ($batch['pre_assignment_date']): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Pre-Assignment
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo formatDate($batch['pre_assignment_date']); ?>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($batch['final_assignment_date']): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Final Assignment
                                <span class="badge bg-info rounded-pill">
                                    <?php echo formatDate($batch['final_assignment_date']); ?>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            End of Course
                            <span class="badge bg-dark rounded-pill">
                                <?php echo formatDate($batch['end_date']); ?>
                            </span>
                        </li>
                    </ul>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="#studentsSection" class="btn btn-primary">
                        <i class="fas fa-users me-1"></i> View Enrolled Students
                    </a>
                    <a href="../students/add_student.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-success">
                        <i class="fas fa-user-plus me-1"></i> Add Student to Batch
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students in Batch -->
<div class="row mt-4" id="studentsSection">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Students Enrolled in this Batch (<?php echo $studentCount; ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($studentCount > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ID Number</th>
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th>Registration Date</th>
                                    <th>Fee Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                while ($student = $studentsResult->fetch_assoc()): 
                                    $fee_info = $studentFees[$student['id']] ?? [
                                        'paid_course_fee' => 0,
                                        'remaining_fee' => $batch['course_fee']
                                    ];
                                    $fee_percentage = ($batch['course_fee'] > 0) ? ($fee_info['paid_course_fee'] / $batch['course_fee']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['contact_number']); ?></td>
                                        <td><?php echo formatDate($student['registration_date']); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?php echo ($fee_percentage >= 100) ? 'bg-success' : 'bg-warning'; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $fee_percentage; ?>%;" 
                                                     aria-valuenow="<?php echo $fee_percentage; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo round($fee_percentage); ?>%
                                                </div>
                                            </div>
                                            <small class="d-block mt-1">
                                                <?php 
                                                echo formatCurrency($fee_info['paid_course_fee']); 
                                                echo ' / ';
                                                echo formatCurrency($batch['course_fee']);
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="../students/view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No students are currently enrolled in this batch.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>