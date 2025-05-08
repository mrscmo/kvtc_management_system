<?php
/**
 * Common functions for the student management system
 */

/**
 * Display success messages
 */
function displaySuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo $_SESSION['success_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['success_message']);
    }
}

/**
 * Display error messages
 */
function displayErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo $_SESSION['error_message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['error_message']);
    }
}

/**
 * Generate a unique student ID
 */
function generateStudentID() {
    global $conn;
    $year = date('Y');
    $month = date('m');
    
    // Get the count of students registered in the current month
    $sql = "SELECT COUNT(*) as count FROM students WHERE YEAR(registration_date) = ? AND MONTH(registration_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    
    // Format: STU-YYYYMM-XXX where XXX is sequential number
    $student_id = "STU-" . $year . $month . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    return $student_id;
}

/**
 * Generate a unique staff ID
 */
function generateStaffID($staff_type) {
    global $conn;
    $year = date('Y');
    $prefix = ($staff_type == 'academic') ? 'ACA' : 'NON';
    
    // Get the count of staff members added in the current year
    $sql = "SELECT COUNT(*) as count FROM staff WHERE YEAR(created_at) = ? AND staff_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $year, $staff_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    
    // Format: ACA/NON-YYYY-XXX where XXX is sequential number
    $staff_id = $prefix . "-" . $year . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    return $staff_id;
}

/**
 * Get course details by ID
 */
function getCourseById($course_id) {
    global $conn;
    
    $sql = "SELECT * FROM courses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get batch details by ID
 */
function getBatchById($batch_id) {
    global $conn;
    
    $sql = "SELECT b.*, c.course_name, c.duration, c.course_fee FROM batches b 
            JOIN courses c ON b.course_id = c.id 
            WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get student details by ID
 */
function getStudentById($student_id) {
    global $conn;
    
    $sql = "SELECT s.*, b.batch_name, c.course_name, c.duration, c.course_fee 
            FROM students s
            JOIN batches b ON s.batch_id = b.id
            JOIN courses c ON s.course_id = c.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get staff details by ID
 */
function getStaffById($staff_id) {
    global $conn;
    
    $sql = "SELECT * FROM staff WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get batches assigned to a staff member
 */
function getStaffBatches($staff_id) {
    global $conn;
    
    $sql = "SELECT sb.*, b.batch_name, b.start_date, b.end_date, c.course_name 
            FROM staff_batch sb
            JOIN batches b ON sb.batch_id = b.id
            JOIN courses c ON b.course_id = c.id
            WHERE sb.staff_id = ?
            ORDER BY sb.assigned_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $batches = array();
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    
    return $batches;
}

/**
 * Get salary history for a staff member
 */
function getStaffSalaryHistory($staff_id) {
    global $conn;
    
    $sql = "SELECT * FROM staff_salary WHERE staff_id = ? ORDER BY payment_month DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $salaries = array();
    while ($row = $result->fetch_assoc()) {
        $salaries[] = $row;
    }
    
    return $salaries;
}

/**
 * Check for staff with upcoming training end dates
 */
function checkTrainingEndDates() {
    global $conn;
    
    // Check for staff with training end dates within the next 7 days
    $futureDate = date('Y-m-d', strtotime('+7 days'));
    $today = date('Y-m-d');
    
    $sql = "SELECT * FROM staff 
            WHERE job_status = 'training' 
            AND training_end_date BETWEEN ? AND ?
            AND id NOT IN (
                SELECT related_id FROM notifications 
                WHERE related_to = 'staff_training' 
                AND related_id = staff.id
                AND notification_date >= ?
            )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $today, $futureDate, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($staff = $result->fetch_assoc()) {
        // Create notification
        $title = "Training Period Ending";
        $message = "Staff member {$staff['name']} (NIC: {$staff['nic_number']}) training period ends on " . 
                   date('d-m-Y', strtotime($staff['training_end_date'])) . ".";
        
        $insertQuery = "INSERT INTO notifications (title, message, related_to, related_id, notification_date) 
                        VALUES (?, ?, 'staff_training', ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ssis", $title, $message, $staff['id'], $today);
        $insertStmt->execute();
    }
}

/**
 * Create monthly salary expenses for staff
 */
function createMonthlySalaryExpenses() {
    global $conn;
    
    // Get current month and year
    $currentMonth = date('Y-m');
    $expenseDate = date('Y-m-d');
    
    // Check if we've already created expenses for this month
    $checkQuery = "SELECT COUNT(*) as count FROM expenses 
                   WHERE expense_type = 'staff_salary' 
                   AND expense_name LIKE ?";
    $stmt = $conn->prepare($checkQuery);
    $monthPattern = $currentMonth . "%";
    $stmt->bind_param("s", $monthPattern);
    $stmt->execute();
    $checkResult = $stmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['count'] > 0) {
        // Already created expenses for this month
        return;
    }
    
    // Get all staff members
    $staffQuery = "SELECT id, name, monthly_salary FROM staff WHERE job_status = 'permanent'";
    $staffResult = $conn->query($staffQuery);
    
    while ($staff = $staffResult->fetch_assoc()) {
        // Create expense record
        $expenseName = "Salary for " . $staff['name'] . " - " . $currentMonth;
        
        $insertQuery = "INSERT INTO expenses (expense_type, expense_name, amount, expense_date) 
                        VALUES ('staff_salary', ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sds", $expenseName, $staff['monthly_salary'], $expenseDate);
        $insertStmt->execute();
        
        // Also create staff_salary record
        $salaryQuery = "INSERT INTO staff_salary (staff_id, amount, payment_date, payment_month, payment_status) 
                        VALUES (?, ?, ?, ?, 'pending')";
        $salaryStmt = $conn->prepare($salaryQuery);
        $salaryStmt->bind_param("idss", $staff['id'], $staff['monthly_salary'], $expenseDate, $currentMonth);
        $salaryStmt->execute();
    }
}

/**
 * Calculate total paid amount for a specific fee type
 */
function getFeePaidAmount($student_id, $fee_type) {
    global $conn;
    
    $sql = "SELECT SUM(amount) as total FROM fees WHERE student_id = ? AND fee_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $student_id, $fee_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ? $row['total'] : 0;
}

/**
 * Calculate remaining fee amount
 */
function getRemainingFeeAmount($student_id, $fee_type, $total_fee) {
    $paid_amount = getFeePaidAmount($student_id, $fee_type);
    return $total_fee - $paid_amount;
}

/**
 * Generate PDF from HTML
 */
function generatePDF($html, $filename) {
    // You would typically use a library like TCPDF, FPDF, or Dompdf here
    // For simplicity, we're just setting headers to force a download
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // In a real implementation, you would convert $html to PDF here
    echo $html;
    exit;
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('d-m-Y', strtotime($date));
}

/**
 * Format currency for display
 */
function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

/**
 * Check for pending notifications
 */
function getUnreadNotificationsCount() {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE is_read = 0";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Check for training end dates and create salary expenses on page load
if (basename($_SERVER['PHP_SELF']) == 'index.php') {
    checkTrainingEndDates();
    
    // Only create salary expenses on the 1st day of the month
    if (date('j') == 1) {
        createMonthlySalaryExpenses();
    }
}
?>