<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireAdmin();
$user = getCurrentUser();

$conn = getDbConnection();

// Get statistics
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'];
$total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM courses"))['count'];
$total_lessons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM lessons"))['count'];
$total_enrollments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments"))['count'];

$usersResult = mysqli_query($conn, "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $usersResult ? mysqli_fetch_all($usersResult, MYSQLI_ASSOC) : [];

// Get recent system activity
$system_activity_query = "
    (
        SELECT 
            'enrollment' as activity_type,
            CONCAT(u.username, ' enrolled in ', c.title) as activity_description,
            e.enrollment_date as activity_date,
            'user-check' as icon,
            u.username,
            u.id as user_id
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        ORDER BY e.enrollment_date DESC
        LIMIT 5
    )
    UNION
    (
        SELECT 
            'assignment_submission' as activity_type,
            CONCAT(u.username, ' submitted assignment: ', a.title) as activity_description,
            asub.submitted_at as activity_date,
            'check' as icon,
            u.username,
            u.id as user_id
        FROM assignment_submissions asub
        JOIN users u ON asub.user_id = u.id
        JOIN assignments a ON asub.assignment_id = a.id
        ORDER BY asub.submitted_at DESC
        LIMIT 5
    )
    UNION
    (
        SELECT 
            'lesson_completed' as activity_type,
            CONCAT(u.username, ' completed lesson: ', l.title) as activity_description,
            lp.completion_date as activity_date,
            'graduation-cap' as icon,
            u.username,
            u.id as user_id
        FROM lesson_progress lp
        JOIN users u ON lp.user_id = u.id
        JOIN lessons l ON lp.lesson_id = l.id
        WHERE lp.completed = 1
        ORDER BY lp.completion_date DESC
        LIMIT 5
    )
    ORDER BY activity_date DESC
    LIMIT 10
";
$activity_result = mysqli_query($conn, $system_activity_query);
$system_activities = mysqli_fetch_all($activity_result, MYSQLI_ASSOC);

// Function to format time difference
function time_ago_admin($datetime) {
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
    <title>Admin Dashboard - D-zone solution</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .admin-nav { display: flex; gap: 20px; margin-bottom: 30px; border-bottom: 2px solid var(--pw-200); padding-bottom: 15px; }
        .admin-nav a { color: var(--text); text-decoration: none; font-weight: 600; padding: 10px 0; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .admin-nav a.active { color: var(--pw-500); border-bottom-color: var(--pw-500); }
        .admin-nav a:hover { color: var(--pw-500); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .stat-card h3 { margin: 0; color: var(--muted); font-size: 14px; font-weight: 600; text-transform: uppercase; }
        .stat-card .number { margin: 10px 0 0 0; font-size: 32px; font-weight: 700; color: var(--pw-500); }
        .dashboard-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .dashboard-card h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e2e2; }
        th { background: var(--pw-100); font-weight: 600; color: var(--text); }
        tr:hover { background: var(--pw-100); }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-admin { background: #e3f2fd; color: #1976d2; }
        .badge-user { background: #f3e5f5; color: #7b1fa2; }
        .quick-links { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; }
        .quick-links a { padding: 10px 20px; background: var(--pw-500); color: white; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .quick-links a:hover { background: var(--pw-600); }
    </style>
</head>
<body>
    <nav>
        <a href="../../index.php" class="logo">
            <img src="../../images/logo2.png" alt="">
        </a>
        <ul class="menu">
            <li><a href="index.php" class="active">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="lessons.php">Lessons</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments.php">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-chart-line"></i> Admin Dashboard</h1>
        <p style="color: var(--muted); margin-bottom: 30px;">Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong> • Full control panel</p>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p class="number"><?php echo $total_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>Admins</h3>
                <p class="number"><?php echo $total_admins; ?></p>
            </div>
            <div class="stat-card">
                <h3>Courses</h3>
                <p class="number"><?php echo $total_courses; ?></p>
            </div>
            <div class="stat-card">
                <h3>Lessons</h3>
                <p class="number"><?php echo $total_lessons; ?></p>
            </div>
            <div class="stat-card">
                <h3>Enrollments</h3>
                <p class="number"><?php echo $total_enrollments; ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <h2><i class="fas fa-cog"></i> Management Panels</h2>
            <p style="color: var(--muted);">Quick access to manage different aspects of the platform</p>
            <div class="quick-links">
                <a href="users.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="courses.php"><i class="fas fa-book"></i> Manage Courses</a>
                <a href="lessons.php"><i class="fas fa-graduation-cap"></i> Manage Lessons</a>
                <a href="assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
                <a href="enrollments.php"><i class="fas fa-clipboard-list"></i> Manage Enrollments</a>
            </div>
        </div>

        <div class="dashboard-card">
            <h2><i class="fas fa-stream"></i> Recent System Activity</h2>
            <?php if (empty($system_activities)): ?>
                <p style="text-align: center; color: #999; padding: 20px;">No recent activity yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($system_activities as $activity): 
                        $activity_color = match($activity['activity_type']) {
                            'enrollment' => '#2196F3',
                            'assignment_submission' => '#FF9800',
                            'lesson_completed' => '#4CAF50',
                            default => 'var(--pw-500)'
                        };
                        $bg_color = match($activity['activity_type']) {
                            'enrollment' => 'rgba(33, 150, 243, 0.1)',
                            'assignment_submission' => 'rgba(255, 152, 0, 0.1)',
                            'lesson_completed' => 'rgba(76, 175, 80, 0.1)',
                            default => 'var(--pw-100)'
                        };
                    ?>
                    <div style="display: flex; gap: 12px; padding: 12px; background: <?php echo $bg_color; ?>; border-radius: 8px; border-left: 4px solid <?php echo $activity_color; ?>;">
                        <div style="width: 36px; height: 36px; background: <?php echo $activity_color; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-<?php echo htmlspecialchars($activity['icon']); ?>" style="color: white; font-size: 14px;"></i>
                        </div>
                        <div style="flex: 1;">
                            <p style="margin: 0; font-weight: 600; color: var(--text); font-size: 13px;">
                                <?php echo htmlspecialchars($activity['activity_description']); ?>
                            </p>
                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #999;">
                                <?php echo time_ago_admin($activity['activity_date']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-card">
            <h2>Recent Users</h2>
            <?php if (empty($recent_users)): ?>
                <p style="text-align: center; color: #999;">No users found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 15px;">
                    <a href="users.php" style="color: var(--pw-500); font-weight: 600;">View all users →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
