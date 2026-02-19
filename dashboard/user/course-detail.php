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

// Get course details
$course_query = "
    SELECT c.*, u.username as instructor_name
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = $course_id
";
$course_result = mysqli_query($conn, $course_query);
$course = mysqli_fetch_assoc($course_result);

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Check if user is enrolled AND approved by admin
$enrollment_query = "SELECT * FROM enrollments WHERE user_id = {$user['id']} AND course_id = $course_id AND approval_status = 'approved'";
$enrollment_result = mysqli_query($conn, $enrollment_query);
$enrollment = mysqli_fetch_assoc($enrollment_result);

if (!$enrollment) {
    header('Location: courses.php');
    exit;
}

// Get lessons
$lessons_query = "
    SELECT 
        l.*,
        (SELECT COUNT(*) FROM lesson_progress WHERE lesson_id = l.id AND user_id = {$user['id']} AND completed = 1) as is_completed
    FROM lessons l
    WHERE l.course_id = $course_id
    ORDER BY l.lesson_order ASC
";
$lessons_result = mysqli_query($conn, $lessons_query);
$lessons = mysqli_fetch_all($lessons_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern Course Detail Styles */
        .course-header {
            background: linear-gradient(135deg, var(--pw-500) 0%, var(--pw-600) 100%);
            color: white;
            padding: 0;
            margin: -20px -20px 30px -20px;
            overflow: hidden;
        }

        .course-header-content {
            padding: 40px 20px;
            position: relative;
            z-index: 2;
        }

        .course-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
        }

        .level-badge-header {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .course-header h1 {
            font-size: 36px;
            margin: 0 0 15px 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .course-header-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            opacity: 0.95;
        }

        .meta-item i {
            font-size: 18px;
        }

        .progress-section-header {
            margin-top: 20px;
        }

        .progress-bar-modern {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
            margin-top: 10px;
        }

        .progress-fill-modern {
            height: 100%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.7) 0%, white 100%);
            border-radius: 10px;
            transition: width 0.4s ease;
        }

        .progress-text-header {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 600;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        /* Lesson Sidebar */
        .lessons-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .sidebar-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--pw-100);
            background: linear-gradient(135deg, var(--pw-100) 0%, rgba(108, 99, 255, 0.05) 100%);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lessons-list {
            padding: 12px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .lesson-item {
            padding: 14px;
            margin-bottom: 8px;
            background: var(--pw-100);
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
        }

        .lesson-item:hover {
            background: white;
            border-color: var(--pw-500);
            box-shadow: 0 4px 12px rgba(108, 99, 255, 0.12);
            transform: translateX(4px);
        }

        .lesson-item.completed {
            background: rgba(76, 175, 80, 0.08);
            border-color: #4CAF50;
        }

        .lesson-number {
            background: var(--pw-500);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .lesson-item.completed .lesson-number {
            background: #4CAF50;
        }

        .lesson-details {
            flex: 1;
            min-width: 0;
        }

        .lesson-title {
            font-weight: 600;
            font-size: 14px;
            margin: 0 0 6px 0;
            word-break: break-word;
        }

        .lesson-duration {
            font-size: 12px;
            color: #999;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .lesson-item.completed::after {
            content: "✓";
            position: absolute;
            top: -8px;
            right: -8px;
            background: #4CAF50;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        /* Main Content Area */
        .course-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }

        .course-description {
            font-size: 16px;
            line-height: 1.7;
            color: var(--text);
            margin-bottom: 30px;
        }

        /* Info Cards Grid */
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid var(--pw-100);
        }

        .info-card {
            background: linear-gradient(135deg, var(--pw-100) 0%, rgba(108, 99, 255, 0.05) 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--pw-200);
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(108, 99, 255, 0.12);
            border-color: var(--pw-500);
        }

        .info-card-icon {
            font-size: 28px;
            color: var(--pw-500);
            margin-bottom: 10px;
        }

        .info-card-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-card-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .instructor-card {
            background: linear-gradient(135deg, var(--pw-100) 0%, rgba(108, 99, 255, 0.05) 100%);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--pw-200);
            margin-top: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .instructor-avatar {
            width: 60px;
            height: 60px;
            background: var(--pw-500);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .instructor-info h4 {
            margin: 0 0 4px 0;
            color: var(--text);
            font-size: 16px;
        }

        .instructor-info p {
            margin: 0;
            color: #999;
            font-size: 13px;
        }

        /* Empty State */
        .empty-lessons {
            text-align: center;
            padding: 30px 20px;
            color: #999;
        }

        .empty-lessons i {
            font-size: 36px;
            color: #ddd;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .lessons-sidebar {
                position: static;
                max-height: none;
            }

            .course-header h1 {
                font-size: 28px;
            }
        }

        @media (max-width: 640px) {
            .course-header-content {
                padding: 30px 16px;
            }

            .course-header h1 {
                font-size: 24px;
            }

            .course-header-meta {
                flex-direction: column;
                gap: 12px;
            }

            .info-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .course-content {
                padding: 20px;
            }
        }

        /* Scrollbar Styling for Lessons */
        .lessons-list::-webkit-scrollbar {
            width: 6px;
        }

        .lessons-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .lessons-list::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }

        .lessons-list::-webkit-scrollbar-thumb:hover {
            background: #999;
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
        <!-- Modern Course Header -->
        <div class="course-header">
            <div class="course-header-content">
                <div class="course-header-top">
                    <a href="courses.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <span class="level-badge-header">
                        <i class="fas fa-signal"></i> <?php echo ucfirst($course['level']); ?>
                    </span>
                </div>

                <h1><?php echo htmlspecialchars($course['title']); ?></h1>

                <div class="course-header-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($course['instructor_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-book"></i>
                        <span><?php echo count($lessons); ?> Lessons</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $course['duration_months']; ?> Months</span>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-section-header">
                    <div class="progress-text-header">
                        Your Progress: <strong><?php echo $enrollment['progress']; ?>%</strong>
                    </div>
                    <div class="progress-bar-modern">
                        <div class="progress-fill-modern" style="width: <?php echo $enrollment['progress']; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">
            <!-- Left Sidebar - Lessons -->
            <div class="lessons-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h3><i class="fas fa-graduation-cap"></i> Course Content</h3>
                    </div>
                    <div class="lessons-list">
                        <?php if (empty($lessons)): ?>
                            <div class="empty-lessons">
                                <i class="fas fa-inbox"></i>
                                <p>No lessons yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <a href="lesson.php?id=<?php echo $lesson['id']; ?>" class="lesson-item <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>">
                                    <div class="lesson-number"><?php echo $index + 1; ?></div>
                                    <div class="lesson-details">
                                        <p class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></p>
                                        <?php if ($lesson['duration_minutes']): ?>
                                            <p class="lesson-duration">
                                                <i class="fas fa-hourglass-end"></i>
                                                <?php echo $lesson['duration_minutes']; ?> min
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Content Area -->
            <div>
                <div class="course-content">
                    <p class="course-description">
                        <strong>About This Course:</strong><br>
                        <?php echo htmlspecialchars($course['description']); ?>
                    </p>

                    <!-- Quick Statistics -->
                    <div class="info-cards-grid">
                        <div class="info-card">
                            <div class="info-card-icon"><i class="fas fa-graduation-cap"></i></div>
                            <div class="info-card-label">Lessons</div>
                            <div class="info-card-value"><?php echo count($lessons); ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-icon"><i class="fas fa-clock"></i></div>
                            <div class="info-card-label">Duration</div>
                            <div class="info-card-value"><?php echo $course['duration_months']; ?> months</div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-icon"><i class="fas fa-trophy"></i></div>
                            <div class="info-card-label">Progress</div>
                            <div class="info-card-value"><?php echo $enrollment['progress']; ?>%</div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-icon"><i class="fas fa-star"></i></div>
                            <div class="info-card-label">Level</div>
                            <div class="info-card-value"><?php echo ucfirst(substr($course['level'], 0, 3)); ?></div>
                        </div>
                    </div>

                   
                </div>
            </div>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
