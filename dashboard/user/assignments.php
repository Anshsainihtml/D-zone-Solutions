<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();

$conn = getDbConnection();

// Get all assignments for enrolled courses
$query = "
    SELECT 
        a.id,
        a.title,
        a.due_date,
        a.max_points,
        l.title as lesson_title,
        c.title as course_title,
        c.id as course_id,
        (SELECT COUNT(*) FROM assignment_submissions 
         WHERE assignment_id = a.id AND user_id = {$user['id']}) as submitted,
        (SELECT grade FROM assignment_submissions 
         WHERE assignment_id = a.id AND user_id = {$user['id']}) as grade,
        DATEDIFF(a.due_date, NOW()) as days_until_due,
        CASE 
            WHEN a.due_date < NOW() THEN 'overdue'
            WHEN a.due_date < DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'urgent'
            ELSE 'pending'
        END as status
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = {$user['id']} AND e.approval_status = 'approved'
    ORDER BY a.due_date ASC
";
$result = mysqli_query($conn, $query);
$assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Separate by status
$pending = [];
$urgent = [];
$overdue = [];
$completed = [];
$submitted_pending = [];

foreach ($assignments as $assignment) {
    if ($assignment['submitted'] && $assignment['grade'] !== null) {
        // Completed - submitted and graded
        $completed[] = $assignment;
    } elseif ($assignment['submitted'] && $assignment['grade'] === null) {
        // Submitted but pending review
        $submitted_pending[] = $assignment;
    } elseif ($assignment['status'] === 'overdue') {
        // Not submitted and overdue
        $overdue[] = $assignment;
    } elseif ($assignment['status'] === 'urgent') {
        // Not submitted and due soon
        $urgent[] = $assignment;
    } else {
        // Not submitted and not urgent
        $pending[] = $assignment;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        <div class="dashboard-header">
            <h1><i class="fas fa-tasks"></i> My Assignments</h1>
        </div>

        <!-- Overdue Assignments -->
        <?php if (!empty($overdue)): ?>
        <div class="section">
            <h2 style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> Overdue (<?php echo count($overdue); ?>)</h2>
            <div class="assignments-list">
                <?php foreach ($overdue as $assignment): ?>
                <div class="assignment-card" style="border-left-color: #f44336;">
                    <div class="assignment-header">
                        <div>
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p style="margin: 5px 0; font-size: 13px; color: #666;">From: <?php echo htmlspecialchars($assignment['course_title']); ?> > <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                        </div>
                        <span class="course-badge" style="background: #f44336;">OVERDUE</span>
                    </div>
                    <div class="assignment-footer" style="margin-top: 15px;">
                        <span class="due-date" style="color: #f44336;">
                            <i class="fas fa-calendar"></i>
                            Was due <?php echo abs($assignment['days_until_due']); ?> days ago
                        </span>
                        <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit">Submit Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Urgent Assignments -->
        <?php if (!empty($urgent)): ?>
        <div class="section">
            <h2 style="color: #FF9800;"><i class="fas fa-clock"></i> Due Soon (<?php echo count($urgent); ?>)</h2>
            <div class="assignments-list">
                <?php foreach ($urgent as $assignment): ?>
                <div class="assignment-card" style="border-left-color: #FF9800;">
                    <div class="assignment-header">
                        <div>
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p style="margin: 5px 0; font-size: 13px; color: #666;">From: <?php echo htmlspecialchars($assignment['course_title']); ?> > <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                        </div>
                        <span class="course-badge" style="background: #FF9800;">URGENT</span>
                    </div>
                    <div class="assignment-footer" style="margin-top: 15px;">
                        <span class="due-date" style="color: #FF9800;">
                            <i class="fas fa-calendar"></i>
                            Due in <?php echo $assignment['days_until_due']; ?> days
                        </span>
                        <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit">Start Assignment</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Assignments -->
        <?php if (!empty($pending)): ?>
        <div class="section">
            <h2><i class="fas fa-hourglass-start"></i> Pending (<?php echo count($pending); ?>)</h2>
            <div class="assignments-list">
                <?php foreach ($pending as $assignment): ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <div>
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p style="margin: 5px 0; font-size: 13px; color: #666;">From: <?php echo htmlspecialchars($assignment['course_title']); ?> > <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                        </div>
                    </div>
                    <div class="assignment-footer" style="margin-top: 15px;">
                        <span class="due-date">
                            <i class="fas fa-calendar"></i>
                            Due in <?php echo $assignment['days_until_due']; ?> days
                        </span>
                        <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit">Start Assignment</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Submitted - Pending Review -->
        <?php if (!empty($submitted_pending)): ?>
        <div class="section">
            <h2 style="color: #FF9800;"><i class="fas fa-hourglass-end"></i> Submitted - Pending Review (<?php echo count($submitted_pending); ?>)</h2>
            <div class="assignments-list">
                <?php foreach ($submitted_pending as $assignment): ?>
                <div class="assignment-card" style="border-left-color: #FF9800;">
                    <div class="assignment-header">
                        <div>
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p style="margin: 5px 0; font-size: 13px; color: #666;">From: <?php echo htmlspecialchars($assignment['course_title']); ?> > <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                        </div>
                        <span class="course-badge" style="background: #FF9800;">UNDER REVIEW</span>
                    </div>
                    <div class="assignment-footer" style="margin-top: 15px;">
                        <span class="due-date" style="color: #FF9800;">
                            <i class="fas fa-check"></i>
                            Awaiting teacher feedback
                        </span>
                        <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit" style="background: #FF9800;">View Submission</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Completed Assignments -->
        <?php if (!empty($completed)): ?>
        <div class="section">
            <h2 style="color: #4CAF50;"><i class="fas fa-check-circle"></i> Completed (<?php echo count($completed); ?>)</h2>
            <div class="assignments-list">
                <?php foreach ($completed as $assignment): ?>
                <div class="assignment-card" style="border-left-color: #4CAF50; opacity: 0.8;">
                    <div class="assignment-header">
                        <div>
                            <h3>✓ <?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p style="margin: 5px 0; font-size: 13px; color: #666;">From: <?php echo htmlspecialchars($assignment['course_title']); ?> > <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                        </div>
                        <span class="course-badge" style="background: #4CAF50;">GRADED</span>
                    </div>
                    <div class="assignment-footer" style="margin-top: 15px;">
                        <span style="font-weight: 600; color: #4CAF50;">
                            <i class="fas fa-star"></i>
                            Score: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($overdue) && empty($urgent) && empty($pending) && empty($submitted_pending) && empty($completed)): ?>
        <div class="section" style="text-align: center;">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No assignments yet. Enroll in a course to get started!</p>
                <a href="courses.php" class="btn-primary">Browse Courses</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
