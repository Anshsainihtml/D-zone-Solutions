<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();

if (empty($_GET['id'])) {
    header('Location: courses.php');
    exit;
}

$conn = getDbConnection();
$lesson_id = (int) $_GET['id'];

// Get lesson details
$lesson_query = "
    SELECT l.*, c.id as course_id, c.title as course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = $lesson_id
";
$lesson_result = mysqli_query($conn, $lesson_query);
$lesson = mysqli_fetch_assoc($lesson_result);

if (!$lesson) {
    header('Location: courses.php');
    exit;
}

// Check if user is enrolled in the course AND approval is approved
$check_enrollment = "SELECT * FROM enrollments WHERE user_id = {$user['id']} AND course_id = {$lesson['course_id']} AND approval_status = 'approved'";
$enrollment_result = mysqli_query($conn, $check_enrollment);
if (mysqli_num_rows($enrollment_result) === 0) {
    header('Location: courses.php');
    exit;
}

// Get lesson progress
$progress_query = "SELECT * FROM lesson_progress WHERE user_id = {$user['id']} AND lesson_id = $lesson_id";
$progress_result = mysqli_query($conn, $progress_query);
$lesson_progress = mysqli_fetch_assoc($progress_result);

// Mark as viewed if not already
if (!$lesson_progress) {
    mysqli_query($conn, "INSERT INTO lesson_progress (user_id, lesson_id, completed) VALUES ({$user['id']}, $lesson_id, 0)");
}

// Get related assignments with submission status
$assignments_query = "
    SELECT 
        a.*,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND user_id = {$user['id']}) as submitted,
        (SELECT grade FROM assignment_submissions WHERE assignment_id = a.id AND user_id = {$user['id']}) as student_grade,
        (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND user_id = {$user['id']}) as student_feedback,
        (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND user_id = {$user['id']}) as submission_date
    FROM assignments a WHERE a.lesson_id = $lesson_id ORDER BY a.due_date ASC
";
$assignments_result = mysqli_query($conn, $assignments_query);
$assignments = mysqli_fetch_all($assignments_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .lesson-header {
            background: linear-gradient(135deg, var(--pw-500) 0%, var(--pw-600) 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .lesson-video {
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .lesson-video iframe {
            width: 100%;
            height: 500px;
            border: none;
        }
        .lesson-body h2 {
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .lesson-body p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        /* Modern Assignment Styles */
        .assignments-section {
            margin-top: 30px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .assignments-section h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 24px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .assignments-section h2 i {
            color: var(--pw-500);
        }
        
        .assignments-list {
            display: grid;
            gap: 20px;
        }
        
        .assignment-card {
            background: linear-gradient(135deg, var(--pw-100) 0%, rgba(108, 99, 255, 0.05) 100%);
            border: 2px solid var(--pw-200);
            padding: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        
        .assignment-card:hover {
            border-color: var(--pw-500);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .assignment-details {
            flex: 1;
            min-width: 0;
        }
        
        .assignment-card h3 {
            margin: 0 0 10px 0;
            color: var(--text);
            font-size: 18px;
            font-weight: 700;
        }
        
        .assignment-description {
            color: #666;
            font-size: 14px;
            margin: 0 0 12px 0;
            line-height: 1.5;
        }
        
        .assignment-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #999;
        }
        
        .meta-item i {
            color: var(--pw-500);
        }
        
        .points-badge {
            background: var(--pw-500);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .due-status {
            font-weight: 600;
        }
        
        .due-status.overdue {
            color: #f44336;
        }
        
        .due-status.upcoming {
            color: #FF9800;
        }
        
        .due-status.completed {
            color: #4CAF50;
        }
        
        .assignment-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .btn-start-assignment {
            background: linear-gradient(135deg, var(--pw-500) 0%, var(--pw-600) 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-start-assignment:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.3);
        }
        
        .no-assignments {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-assignments i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 10px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .assignment-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .assignment-actions {
                width: 100%;
            }
            
            .btn-start-assignment {
                width: 100%;
                justify-content: center;
            }
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
            <li><a href="courses.php" class="active">Courses</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="dashboard-container">
        <a href="course-detail.php?id=<?php echo $lesson['course_id']; ?>" style="color: var(--pw-500); margin-bottom: 20px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> Back to <?php echo htmlspecialchars($lesson['course_title']); ?>
        </a>

        <div class="lesson-header">
            <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <p style="margin: 10px 0 0 0;"><i class="fas fa-book"></i> From course: <?php echo htmlspecialchars($lesson['course_title']); ?></p>
            <?php if ($lesson['duration_minutes']): ?>
                <p style="margin: 5px 0 0 0;"><i class="fas fa-clock"></i> Duration: <?php echo $lesson['duration_minutes']; ?> minutes</p>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 30px;">
            <!-- Main Content -->
            <div>
                <?php if ($lesson['video_url']): ?>
                <div class="lesson-video">
                    <iframe src="<?php echo htmlspecialchars($lesson['video_url']); ?>" allowfullscreen></iframe>
                </div>
                <?php endif; ?>

                <div class="lesson-body" style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h2><?php echo htmlspecialchars($lesson['title']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                    
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

                    <h3>Lesson Content</h3>
                    <div style="background: var(--pw-200); padding: 20px; border-radius: 5px; color: var(--muted);">
                        <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                    </div>

                    <?php if (!($lesson_progress && $lesson_progress['completed'])): ?>
                    <form method="POST" style="margin-top: 30px;">
                        <button type="submit" name="mark_complete" class="btn-continue" style="width: auto; display: inline-block; padding: 12px 30px;">
                            <i class="fas fa-check"></i> Mark Lesson Complete
                        </button>
                    </form>
                    <?php else: ?>
                    <div style="margin-top: 30px; padding: 15px; background: var(--pw-200); border-radius: 5px; color: var(--pw-600);">
                        <i class="fas fa-check-circle"></i> You completed this lesson on <?php echo date('M d, Y', strtotime($lesson_progress['completion_date'])); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($assignments)): ?>
                <div class="assignments-section">
                    <h2><i class="fas fa-tasks"></i> Assignments for this Lesson</h2>
                    <div class="assignments-list">
                        <?php foreach ($assignments as $assignment): 
                            $is_overdue = $assignment['due_date'] && strtotime($assignment['due_date']) < time();
                        ?>
                        <div class="assignment-card">
                            <div class="assignment-details">
                                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <?php if ($assignment['description']): ?>
                                    <p class="assignment-description">
                                        <?php echo htmlspecialchars(substr($assignment['description'], 0, 150)); 
                                        if (strlen($assignment['description']) > 150) echo '...'; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="assignment-meta">
                                    <?php if ($assignment['max_points']): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-star"></i>
                                            <span class="points-badge"><?php echo $assignment['max_points']; ?> Points</span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($assignment['submitted']): ?>
                                        <span class="meta-item" style="color: #4CAF50; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> 
                                            Submitted
                                        </span>
                                        <?php if ($assignment['student_grade'] !== null): ?>
                                            <span class="meta-item">
                                                <i class="fas fa-award" style="color: #FF9800;"></i>
                                                <span style="color: #FF9800; font-weight: 600;">Grade: <?php echo $assignment['student_grade']; ?>%</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="meta-item" style="color: #FF9800;">
                                                <i class="fas fa-hourglass-end"></i> Pending Review
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($assignment['due_date']): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-calendar-times"></i>
                                            <span class="due-status <?php echo $is_overdue ? 'overdue' : 'upcoming'; ?>">
                                                Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                <?php if ($is_overdue) echo ' (Overdue)'; ?>
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span class="meta-item">
                                            <i class="fas fa-hourglass"></i>
                                            <span>No deadline</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="assignment-actions">
                                <?php if ($assignment['submitted']): ?>
                                    <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-start-assignment" style="background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);">
                                        <i class="fas fa-edit"></i> View
                                    </a>
                                <?php else: ?>
                                    <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-start-assignment">
                                        <i class="fas fa-arrow-right"></i> Start
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div style="position: sticky; top: 20px; height: fit-content;">
                <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h3 style="margin-top: 0;">Lesson Status</h3>
                    <?php if ($lesson_progress && $lesson_progress['completed']): ?>
                    <div style="text-align: center; padding: 20px; background: #e8f5e9; border-radius: 5px; color: #2e7d32;">
                        <i class="fas fa-check-circle" style="font-size: 32px;"></i>
                        <p style="margin: 10px 0 0 0; font-weight: 600;">Completed</p>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; background: #fff3e0; border-radius: 5px; color: #e65100;">
                        <i class="fas fa-hourglass-start" style="font-size: 32px;"></i>
                        <p style="margin: 10px 0 0 0; font-weight: 600;">In Progress</p>
                    </div>
                    <?php endif; ?>

                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">

                    <h4 style="margin-bottom: 15px;">Quick Links</h4>
                    <a href="course-detail.php?id=<?php echo $lesson['course_id']; ?>" class="btn-continue" style="display: block; width: 100%; text-align: center; padding: 10px;">
                        <i class="fas fa-book"></i> Course Overview
                    </a>
                    <a href="assignments.php" class="btn-continue" style="display: block; width: 100%; text-align: center; padding: 10px; margin-top: 10px;">
                        <i class="fas fa-tasks"></i> All Assignments
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Handle mark complete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'])) {
        $now = date('Y-m-d H:i:s');
        if ($lesson_progress) {
            mysqli_query($conn, "UPDATE lesson_progress SET completed = 1, completion_date = '$now' WHERE user_id = {$user['id']} AND lesson_id = $lesson_id");
        } else {
            mysqli_query($conn, "INSERT INTO lesson_progress (user_id, lesson_id, completed, completion_date) VALUES ({$user['id']}, $lesson_id, 1, '$now')");
        }
        // Update course enrollment progress
        $completed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM lesson_progress WHERE user_id = {$user['id']} AND lesson_id IN (SELECT id FROM lessons WHERE course_id = {$lesson['course_id']}) AND completed = 1"))['count'];
        $total_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM lessons WHERE course_id = {$lesson['course_id']}"))['count'];
        $progress = round(($completed_count / $total_count) * 100);
        mysqli_query($conn, "UPDATE enrollments SET progress = $progress WHERE user_id = {$user['id']} AND course_id = {$lesson['course_id']}");
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    ?>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
