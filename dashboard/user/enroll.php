<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();

if (empty($_GET['id'])) {
    header('Location: courses.php');
    exit;
}

$conn = getDbConnection();
$course_id = (int) $_GET['id'];

// Verify course exists
$course_check = mysqli_query($conn, "SELECT id FROM courses WHERE id = $course_id");
if (mysqli_num_rows($course_check) === 0) {
    header('Location: courses.php');
    exit;
}

// Check if already enrolled and approved
$enrollment_check = mysqli_query($conn, "SELECT id, approval_status FROM enrollments WHERE user_id = {$user['id']} AND course_id = $course_id");
if (mysqli_num_rows($enrollment_check) > 0) {
    $existing = mysqli_fetch_assoc($enrollment_check);
    
    // If approved, redirect to course details
    if ($existing['approval_status'] === 'approved') {
        header('Location: course-detail.php?id=' . $course_id);
        exit;
    }
    
    // If rejected or pending, delete old enrollment and allow re-enrollment
    mysqli_query($conn, "DELETE FROM enrollments WHERE id = {$existing['id']}");
}

// Enroll user - starts as pending, awaiting admin approval
$now = date('Y-m-d H:i:s');
if (mysqli_query($conn, "INSERT INTO enrollments (user_id, course_id, enrollment_date, progress, status, approval_status) VALUES ({$user['id']}, $course_id, '$now', 0, 'active', 'pending')")) {
    // Store enrollment success in session
    session_start();
    $_SESSION['enrollment_success'] = true;
    $_SESSION['enrolled_course_id'] = $course_id;
    // Redirect back to courses
    header('Location: courses.php?success=enrolled');
    exit;
} else {
    // Error handling
    session_start();
    $_SESSION['enrollment_error'] = true;
    header('Location: courses.php?error=enroll_failed');
    exit;
}
?>
