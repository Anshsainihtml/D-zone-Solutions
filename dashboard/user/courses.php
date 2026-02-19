<?php
session_start();
require_once __DIR__ . '/../../includes/auth_helpers.php';
$user = getCurrentUser();

$conn = getDbConnection();

// Check for enrollment notifications
$showSuccessNotification = false;
$enrolledCourseId = null;
$showErrorNotification = false;

if (isset($_SESSION['enrollment_success']) && $_SESSION['enrollment_success']) {
    $showSuccessNotification = true;
    $enrolledCourseId = $_SESSION['enrolled_course_id'] ?? null;
    unset($_SESSION['enrollment_success']);
    unset($_SESSION['enrolled_course_id']);
}

if (isset($_GET['error']) && $_GET['error'] === 'enroll_failed') {
    $showErrorNotification = true;
}

if (isset($_GET['success']) && $_GET['success'] === 'enrolled') {
    $showSuccessNotification = isset($_SESSION['enrollment_success']) || true;
}

// Get filter parameters
$level = $_GET['level'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT DISTINCT 
    c.id,
    c.title,
    c.description,
    c.level,
    c.duration_months,
    c.cover_image,
    u.username as instructor_name,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
    " . ($user ? "(SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND user_id = {$user['id']} AND approval_status = 'approved') as is_enrolled" : "0 as is_enrolled") . "
FROM courses c
JOIN users u ON c.instructor_id = u.id
WHERE 1=1";

if ($level !== 'all') {
    $level = mysqli_real_escape_string($conn, $level);
    $query .= " AND c.level = '$level'";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (c.title LIKE '%$search%' OR c.description LIKE '%$search%')";
}

$query .= " ORDER BY c.created_at DESC";

$result = mysqli_query($conn, $query);
$courses = mysqli_fetch_all($result, MYSQLI_ASSOC);

// If SIGMA course isn't present in DB, append a fallback course so it shows on the browse page
$foundSigma = false;
foreach ($courses as $c) {
    if (isset($c['title']) && strcasecmp(trim($c['title']), 'SIGMA Σ') === 0) {
        $foundSigma = true;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            pointer-events: none;
        }

        .toast-notification {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            pointer-events: auto;
            min-width: 300px;
            animation: slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .toast-notification.success {
            border-left: 4px solid #4CAF50;
        }

        .toast-notification.error {
            border-left: 4px solid #f44336;
        }

        .toast-notification.icon {
            font-size: 20px;
            font-weight: bold;
            min-width: 24px;
        }

        .toast-notification.success .icon {
            color: #4CAF50;
        }

        .toast-notification.error .icon {
            color: #f44336;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            color: #333;
            margin: 0 0 4px 0;
            font-size: 14px;
        }

        .toast-message {
            color: #666;
            margin: 0;
            font-size: 13px;
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 18px;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .toast-close:hover {
            color: #333;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast-notification.removing {
            animation: slideOutRight 0.3s ease-out forwards;
        }

        /* Enrolled Badge Styles */
        .enrolled-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .enrolled-badge i {
            font-size: 12px;
        }

        /* Course Card Enrolled State */
        .course-card.enrolled {
            border: 1px solid rgba(76, 175, 80, 0.2);
            background: linear-gradient(135deg, #ffffff 0%, rgba(76, 175, 80, 0.02) 100%);
        }

        .course-card.enrolled .course-cover {
            background: linear-gradient(135deg, #f0f8f4 0%, #e8f5e9 100%);
        }
    </style>
</head>
<body>
    <!-- Modern Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>
    <nav>
       <a href="../../index.php">
                <img src="../../images/logo2.png" alt="">
            </a>
        <ul>
            <?php if ($user): ?>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="courses.php" class="active">Courses</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments-status.php">My Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
            <?php else: ?>
            <li><a href="courses.php" class="active">Browse Courses</a></li>
            <li><a href="../../auth/login.php">Login</a></li>
            <li><a href="../../auth/register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-book"></i> Browse Courses</h1>
        </div>

        <!-- Filter Section -->
        <div class="section">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search courses..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; flex: 1; min-width: 200px;"
                >
                <select name="level" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                    <option value="all">All Levels</option>
                    <option value="beginner" <?php echo $level === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                    <option value="intermediate" <?php echo $level === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                    <option value="advanced" <?php echo $level === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                </select>
                <button type="submit" class="btn-submit">Filter</button>
            </form>
        </div>

        <!-- Courses Grid -->
        <div class="section">
            <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>No courses found. Try adjusting your filters.</p>
            </div>
            <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                <div class="course-card <?php echo ($user && $course['is_enrolled']) ? 'enrolled' : ''; ?>">
                    <div class="course-cover">
                        <img src="<?php echo htmlspecialchars($course['cover_image'] ?? '../../images/course-placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                       
                        <span class="level-badge <?php echo strtolower($course['level']); ?>"><?php echo ucfirst($course['level']); ?></span>
                    </div>
                    <div class="course-content">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p style="font-size: 13px; color: var(--pw-500); margin: 5px 0;">By <?php echo htmlspecialchars($course['instructor_name']); ?></p>
                        <p class="course-description"><?php echo substr(htmlspecialchars($course['description']), 0, 80) . '...'; ?></p>

                        <div class="course-stats">
                            <span><i class="fas fa-book"></i> <?php echo $course['total_lessons']; ?> Lessons</span>
                            <span><i class="fas fa-clock"></i> <?php echo $course['duration_months']; ?> Months</span>
                            <span><i class="fas fa-users"></i> <?php echo $course['enrollment_count']; ?> Enrolled</span>
                        </div>

                        <?php if ($user && $course['is_enrolled']): ?>
                            <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn-continue">
                                Continue Learning →
                            </a>
                        <?php elseif ($user): ?>
                            <a href="enroll.php?id=<?php echo $course['id']; ?>" class="btn-continue">
                                Enroll Now →
                            </a>
                        <?php else: ?>
                            <a href="../../auth/login.php" class="btn-continue">
                                Sign in to Enroll →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>

    <script>
        // Modern Toast Notification System
        function showToast(title, message, type = 'success', duration = 5000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            
            const iconClass = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="icon">
                    <i class="${iconClass}"></i>
                </div>
                <div class="toast-content">
                    <p class="toast-title">${title}</p>
                    <p class="toast-message">${message}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.classList.add('removing');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, duration);
            }
        }

        // Show enrollment success notification
        <?php if ($showSuccessNotification): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(
                    '⏳ Enrollment Pending',
                    'Your enrollment request has been submitted. Waiting for admin approval to start learning.',
                    'success',
                    5000
                );
            });
        <?php endif; ?>

        // Show enrollment error notification
        <?php if ($showErrorNotification): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(
                    'Oops!',
                    'Failed to enroll in the course. Please try again.',
                    'error',
                    5000
                );
            });
        <?php endif; ?>
    </script>
</body>
</html>
