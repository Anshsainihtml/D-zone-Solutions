<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();

if (empty($_GET['id'])) {
    header('Location: assignments.php');
    exit;
}

$conn = getDbConnection();
$assignment_id = (int) $_GET['id'];

// Get assignment details
$assignment_query = "
    SELECT 
        a.*,
        l.title as lesson_title,
        l.course_id,
        c.title as course_title
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE a.id = $assignment_id
";
$assignment_result = mysqli_query($conn, $assignment_query);
$assignment = mysqli_fetch_assoc($assignment_result);

if (!$assignment) {
    header('Location: assignments.php');
    exit;
}

// Check if user is enrolled in the course AND approval is approved
$check_enrollment = "SELECT * FROM enrollments WHERE user_id = {$user['id']} AND course_id = {$assignment['course_id']} AND approval_status = 'approved'";
$enrollment_result = mysqli_query($conn, $check_enrollment);
if (mysqli_num_rows($enrollment_result) === 0) {
    header('Location: assignments.php');
    exit;
}

// Get existing submission
$submission_query = "SELECT * FROM assignment_submissions WHERE assignment_id = $assignment_id AND user_id = {$user['id']}";
$submission_result = mysqli_query($conn, $submission_query);
$submission = mysqli_fetch_assoc($submission_result);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_text = mysqli_real_escape_string($conn, $_POST['submission_text'] ?? '');
    $now = date('Y-m-d H:i:s');

    if (!$submission) {
        // New submission
        $insert_query = "
            INSERT INTO assignment_submissions 
            (assignment_id, user_id, submission_text, submitted_at) 
            VALUES ($assignment_id, {$user['id']}, '$submission_text', '$now')
        ";
        if (mysqli_query($conn, $insert_query)) {
            $success_message = 'Assignment submitted successfully!';
            $submission = [
                'submission_text' => $submission_text,
                'submitted_at' => $now,
                'grade' => null,
                'feedback' => null
            ];
        } else {
            $error_message = 'Error submitting assignment. Please try again.';
        }
    } else {
        // Update existing submission
        $update_query = "
            UPDATE assignment_submissions 
            SET submission_text = '$submission_text', submitted_at = '$now'
            WHERE assignment_id = $assignment_id AND user_id = {$user['id']}
        ";
        if (mysqli_query($conn, $update_query)) {
            $success_message = 'Assignment updated successfully!';
            $submission['submission_text'] = $submission_text;
            $submission['submitted_at'] = $now;
        } else {
            $error_message = 'Error updating assignment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group textarea {
            min-height: 300px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--pw-500);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.08);
        }
        .form-group textarea:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }
        .submit-btn {
            background: linear-gradient(135deg, var(--pw-500) 0%, var(--pw-600) 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
            border: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .submit-btn:hover {
            transform: scale(1.05);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        .assignment-info {
            background: var(--pw-100);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid var(--pw-500);
        }
        .grade-badge {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        .feedback-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <nav>
        <a href="../../index.php">
                <img src="../../images/logo2.png" alt="">
            </a>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="assignments.php" class="active">Assignments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="dashboard-container">
        <a href="assignments.php" style="color: var(--pw-500); margin-bottom: 20px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> Back to Assignments
        </a>

        <div class="section">
            <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>

            <div class="assignment-info">
                <p style="margin: 0;"><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_title']); ?></p>
                <p style="margin: 8px 0 0 0;"><strong>Lesson:</strong> <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                <p style="margin: 8px 0 0 0;"><strong>Due Date:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($assignment['due_date'])); ?></p>
                <p style="margin: 8px 0 0 0;"><strong>Max Points:</strong> <?php echo $assignment['max_points']; ?></p>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success_message; ?></div>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>

            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h2>Assignment Description</h2>
                <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>

                <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

                <h2>Submit Your Work</h2>

                <?php if ($submission && $submission['grade'] !== null): ?>
                <div style="background: #e8f5e9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                    <p style="margin: 0;"><strong>Your Grade:</strong> <?php echo $submission['grade']; ?>/<?php echo $assignment['max_points']; ?></p>
                    <?php if ($submission['feedback']): ?>
                    <div class="feedback-box">
                        <h4 style="margin-top: 0;">Instructor Feedback:</h4>
                        <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="submission_text">Your Assignment Response:</label>
                        <textarea 
                            name="submission_text" 
                            id="submission_text" 
                            placeholder="Type or paste your assignment response here..."
                            <?php echo ($submission && $submission['grade'] !== null) ? 'disabled' : 'required'; ?>
                        ><?php echo htmlspecialchars($submission['submission_text'] ?? ''); ?></textarea>
                    </div>

                    <?php if (!($submission && $submission['grade'] !== null)): ?>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> 
                        <?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?>
                    </button>
                    <?php else: ?>
                    <div style="padding: 12px 16px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724; margin-top: 5px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i>
                        <span>This assignment has been graded and can no longer be edited.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($submission): ?>
                    <p style="margin-top: 10px; color: #999; font-size: 13px;">
                        <i class="fas fa-clock"></i> 
                        Last submitted: <?php echo date('M d, Y \a\t g:i A', strtotime($submission['submitted_at'])); ?>
                    </p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
