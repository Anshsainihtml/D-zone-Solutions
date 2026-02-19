<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$memberDays = max(1, (int) ((time() - strtotime($user['created_at'])) / 86400));

// Get enrollments and courses
$conn = getDbConnection();
$enrollments_query = "
    SELECT 
        c.id as course_id,
        c.title,
        c.description,
        c.level,
        c.duration_months,
        c.cover_image,
        u.username as instructor_name,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
        (SELECT COUNT(*) FROM lesson_progress 
         WHERE user_id = {$user['id']} AND lesson_id IN 
         (SELECT id FROM lessons WHERE course_id = c.id) AND completed = 1) as completed_lessons,
        latest_enrollment.enrollment_date
    FROM (
        SELECT course_id, MAX(enrollment_date) as enrollment_date
        FROM enrollments
        WHERE user_id = {$user['id']} AND approval_status = 'approved'
        GROUP BY course_id
    ) latest_enrollment
    JOIN courses c ON latest_enrollment.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    ORDER BY latest_enrollment.enrollment_date DESC
";
$enrollments = mysqli_query($conn, $enrollments_query);
$enrolled_courses = mysqli_fetch_all($enrollments, MYSQLI_ASSOC);

// Calculate progress for each course
foreach ($enrolled_courses as &$course) {
    if ($course['total_lessons'] > 0) {
        $course['progress'] = round(($course['completed_lessons'] / $course['total_lessons']) * 100);
    } else {
        $course['progress'] = 0;
    }
}
unset($course);

// Get pending assignments (not submitted yet)
$assignments_query = "
    SELECT 
        a.id,
        a.title,
        a.due_date,
        l.title as lesson_title,
        c.title as course_title,
        c.id as course_id,
        (SELECT COUNT(*) FROM assignment_submissions 
         WHERE assignment_id = a.id AND user_id = {$user['id']}) as submitted,
        DATEDIFF(a.due_date, NOW()) as days_until_due
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = {$user['id']}
    AND e.approval_status = 'approved'
    AND a.due_date > NOW()
    AND (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND user_id = {$user['id']}) = 0
    ORDER BY a.due_date ASC
    LIMIT 5
";
$assignments_result = mysqli_query($conn, $assignments_query);
$pending_assignments = mysqli_fetch_all($assignments_result, MYSQLI_ASSOC);

// Get current learning stats
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM enrollments WHERE user_id = {$user['id']} AND approval_status = 'approved') as total_courses,
        (SELECT COUNT(*) FROM lesson_progress WHERE user_id = {$user['id']} AND completed = 1) as completed_lessons,
        (SELECT COUNT(*) FROM lessons l 
         JOIN enrollments e ON e.course_id = l.course_id 
         WHERE e.user_id = {$user['id']} AND e.approval_status = 'approved') as total_lessons,
        (SELECT COUNT(*) FROM assignment_submissions 
         WHERE user_id = {$user['id']}) as graded_assignments
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Get recent activity (lesson completions, assignments, enrollments)
$recent_activity_query = "
    (
        SELECT 
            'lesson_completed' as activity_type,
            c.title as course_title,
            l.title as lesson_title,
            lp.completion_date as activity_date,
            CONCAT('Completed lesson: ', l.title) as activity_description,
            'check-circle' as icon,
            lp.completion_date as sort_date
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id AND e.user_id = lp.user_id
        WHERE lp.user_id = {$user['id']} AND lp.completed = 1 AND e.approval_status = 'approved'
    )
    UNION
    (
        SELECT 
            'assignment_graded' as activity_type,
            c.title as course_title,
            a.title as lesson_title,
            asub.graded_at as activity_date,
            CONCAT('Scored ', asub.grade, '% on ', a.title) as activity_description,
            'star' as icon,
            asub.graded_at as sort_date
        FROM assignment_submissions asub
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN lessons l ON a.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id AND e.user_id = asub.user_id
        WHERE asub.user_id = {$user['id']} AND asub.grade IS NOT NULL AND e.approval_status = 'approved'
    )
    UNION
    (
        SELECT 
            'course_enrolled' as activity_type,
            c.title as course_title,
            c.title as lesson_title,
            e.enrollment_date as activity_date,
            CONCAT('Enrolled in: ', c.title) as activity_description,
            'book' as icon,
            e.enrollment_date as sort_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = {$user['id']} AND e.approval_status = 'approved'
    )
    ORDER BY sort_date DESC
    LIMIT 10
";
$activity_result = mysqli_query($conn, $recent_activity_query);
$recent_activities = mysqli_fetch_all($activity_result, MYSQLI_ASSOC);

// Function to format time difference
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    if ($diff < 2592000) return floor($diff/604800) . ' weeks ago';
    return floor($diff/2592000) . ' months ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Dashboard - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime(__DIR__ . '/../../css/style.css'); ?>"/>
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../../css/dashboard.css'); ?>"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-icon{
             background: linear-gradient(135deg, var(--pw-500) 0%, var(--pw-600) 100%);
        }
    </style>
</head>

<body>
    <nav>
        <div class="logo">
            <a href="../../index.php">
                <img src="../../images/logo2.png" alt="">
            </a>
        </div>
        <ul>
            <li><a href="index.php" class="active">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments-status.php">My Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>! 👋</h1>
                <p>Continue your learning journey</p>
            </div>
          
        </div>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Courses Enrolled</p>
                    <h2 class="stat-value"><?php echo $stats['total_courses'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Lessons Completed</p>
                    <h2 class="stat-value"><?php echo ($stats['completed_lessons'] ?? 0) . '/' . ($stats['total_lessons'] ?? 0); ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" >
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Assignments Done</p>
                    <h2 class="stat-value"><?php echo $stats['graded_assignments'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Learning Streak</p>
                    <h2 class="stat-value">5 days</h2>
                </div>
            </div>
        </div>

        <!-- Pending Assignments -->
        <?php if (!empty($pending_assignments)): ?>
        <div class="section">
            <h2><i class="fas fa-clipboard-list"></i> Pending Assignments</h2>
            <div class="assignments-list">
                <?php foreach ($pending_assignments as $assignment): ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                        <span class="course-badge"><?php echo htmlspecialchars($assignment['course_title']); ?></span>
                    </div>
                    <p class="assignment-lesson">From: <?php echo htmlspecialchars($assignment['lesson_title']); ?></p>
                    <div class="assignment-footer">
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

        <!-- Courses Grid -->
        <div class="section">
            <h2><i class="fas fa-laptop-code"></i> My Courses</h2>
            <?php if (empty($enrolled_courses)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>You haven't enrolled in any courses yet.</p>
                <a href="courses.php" class="btn-primary">Browse Courses</a>
            </div>
            <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                <div class="course-card">
                    <div class="course-cover">
                        <img src="<?php echo $course['cover_image'] ?? '../../images/logo.png' ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                       
                        <span class="level-badge <?php echo strtolower($course['level']); ?>"><?php echo ucfirst($course['level']); ?></span>
                    </div>
                    <div class="course-content">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p style="font-size: 13px; color: var(--pw-500); margin: 5px 0;">By <?php echo htmlspecialchars($course['instructor_name']); ?></p>
                        <p class="course-description"><?php echo substr(htmlspecialchars($course['description']), 0, 80) . '...'; ?></p>

                        <div class="progress-section">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course['progress'] ?? 0; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $course['progress'] ?? 0; ?>%</span>
                        </div>

                        <div class="course-stats">
                            <span><i class="fas fa-book"></i> <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> Lessons</span>
                            <span><i class="fas fa-clock"></i> <?php echo $course['duration_months']; ?> Months</span>
                        </div>

                        <a href="course-detail.php?id=<?php echo $course['course_id']; ?>" class="btn-continue">
                            Continue Learning →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="section">
            <h2><i class="fas fa-history"></i> Recent Activity</h2>
            <div class="activity-list">
                <?php if (empty($recent_activities)): ?>
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px; color: #ddd;"></i>
                        <p>No activity yet. Start learning to see your progress!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): 
                        $icon_color = match($activity['activity_type']) {
                            'lesson_completed' => '#4CAF50',
                            'assignment_graded' => '#FF9800',
                            'course_enrolled' => '#2196F3',
                            default => 'var(--pw-500)'
                        };
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: rgba(<?php 
                            echo match($activity['activity_type']) {
                                'lesson_completed' => '76, 175, 80',
                                'assignment_graded' => '255, 152, 0',
                                'course_enrolled' => '33, 150, 243',
                                default => '108, 99, 255'
                            };
                        ?>, 0.15);">
                            <i class="fas fa-<?php echo htmlspecialchars($activity['icon']); ?>" style="color: <?php echo $icon_color; ?>;"></i>
                        </div>
                        <div class="activity-content">
                            <p class="activity-title"><?php echo htmlspecialchars($activity['activity_description']); ?></p>
                            <p class="activity-course" style="font-size: 12px; color: #999; margin: 4px 0 0 0;">
                                <?php echo htmlspecialchars($activity['course_title']); ?>
                            </p>
                            <p class="activity-time"><?php echo time_ago($activity['activity_date']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
