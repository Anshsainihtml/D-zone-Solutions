<?php
session_start();
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
$user = getCurrentUser();

$conn = getDbConnection();

// Get all user enrollments with their status
$enrollments_query = "
    SELECT 
        e.id,
        e.approval_status,
        e.enrollment_date,
        e.rejection_reason,
        e.approved_at,
        c.id as course_id,
        c.title as course_title,
        c.description,
        c.cover_image,
        u.username as instructor_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.instructor_id = u.id
    WHERE e.user_id = {$user['id']}
    ORDER BY e.enrollment_date DESC
";

$result = mysqli_query($conn, $enrollments_query);
if (!$result) {
    die("Database error: " . mysqli_error($conn));
}
$enrollments = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Separate enrollments by status
$pending = [];
$approved = [];
$rejected = [];

foreach ($enrollments as $enrollment) {
    if ($enrollment['approval_status'] === 'pending') {
        $pending[] = $enrollment;
    } elseif ($enrollment['approval_status'] === 'approved') {
        $approved[] = $enrollment;
    } elseif ($enrollment['approval_status'] === 'rejected') {
        $rejected[] = $enrollment;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Enrollment Status - Computer Coaching</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text);
        }
        
        .enrollment-card {
            background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%);
            border-left: 4px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            gap: 20px;
        }
        
        .enrollment-card.pending {
            border-left-color: #FF9800;
            background: linear-gradient(135deg, #fff8f0 0%, #ffffff 100%);
        }
        
        .enrollment-card.approved {
            border-left-color: #4CAF50;
            background: linear-gradient(135deg, #f0f8f4 0%, #ffffff 100%);
        }
        
        .enrollment-card.rejected {
            border-left-color: #f44336;
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
        }
        
        .enrollment-card-image {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .enrollment-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .enrollment-card-content {
            flex: 1;
        }
        
        .enrollment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            gap: 15px;
        }
        
        .enrollment-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 5px 0;
        }
        
        .enrollment-card-instructor {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .enrollment-card-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--muted);
        }
        
        .rejection-reason {
            background: #fff9e6;
            border: 1px solid #ffe699;
            border-radius: 6px;
            padding: 12px;
            margin-top: 12px;
            font-size: 13px;
            color: #333;
        }
        
        .rejection-reason-label {
            font-weight: 600;
            color: #f44336;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .btn-continue {
            background: var(--pw-500);
            color: white;
        }
        
        .btn-continue:hover {
            background: var(--pw-600);
        }
        
        .btn-rebrowse {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-rebrowse:hover {
            background: #bbdefb;
        }
        
        .btn-reenroll {
            background: #fff3e0;
            color: #e65100;
        }
        
        .btn-reenroll:hover {
            background: #ffe0b2;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .enrollment-card {
                flex-direction: column;
            }
            
            .enrollment-card-image {
                width: 100%;
                height: 150px;
            }
            
            .enrollment-card-header {
                flex-direction: column;
            }
            
            .enrollment-card-meta {
                flex-wrap: wrap;
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
            <li><a href="courses.php">Courses</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments-status.php" class="active">My Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-graduation-cap"></i> My Enrollment Status</h1>
        </div>

        <!-- Approved Enrollments -->
        <?php if (!empty($approved)): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-check-circle" style="color: #4CAF50; font-size: 24px;"></i>
                Approved Enrollments (<?php echo count($approved); ?>)
            </div>
            <?php foreach ($approved as $enrollment): ?>
                <div class="enrollment-card approved">
                    <div class="enrollment-card-image">
                        <img src="<?php echo htmlspecialchars($enrollment['cover_image'] ?? '../../images/course-placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($enrollment['course_title']); ?>">
                    </div>
                    <div class="enrollment-card-content">
                        <div class="enrollment-card-header">
                            <div>
                                <h3 class="enrollment-card-title"><?php echo htmlspecialchars($enrollment['course_title']); ?></h3>
                                <p class="enrollment-card-instructor">By <?php echo htmlspecialchars($enrollment['instructor_name']); ?></p>
                            </div>
                            <span class="status-badge approved">
                                <i class="fas fa-check"></i> Approved
                            </span>
                        </div>
                        <div class="enrollment-card-meta">
                            <span><i class="fas fa-calendar"></i> Approved: <?php echo date('M d, Y', strtotime($enrollment['approved_at'])); ?></span>
                        </div>
                        <div class="action-buttons">
                            <a href="course-detail.php?id=<?php echo $enrollment['course_id']; ?>" class="btn-action btn-continue">
                                <i class="fas fa-play"></i> Continue Learning
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pending Enrollments -->
        <?php if (!empty($pending)): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-hourglass-half" style="color: #FF9800; font-size: 24px;"></i>
                Pending Approval (<?php echo count($pending); ?>)
            </div>
            <?php foreach ($pending as $enrollment): ?>
                <div class="enrollment-card pending">
                    <div class="enrollment-card-image">
                        <img src="<?php echo htmlspecialchars($enrollment['cover_image'] ?? '../../images/course-placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($enrollment['course_title']); ?>">
                    </div>
                    <div class="enrollment-card-content">
                        <div class="enrollment-card-header">
                            <div>
                                <h3 class="enrollment-card-title"><?php echo htmlspecialchars($enrollment['course_title']); ?></h3>
                                <p class="enrollment-card-instructor">By <?php echo htmlspecialchars($enrollment['instructor_name']); ?></p>
                            </div>
                            <span class="status-badge pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        </div>
                        <div class="enrollment-card-meta">
                            <span><i class="fas fa-calendar"></i> Requested: <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></span>
                        </div>
                        <p style="font-size: 13px; color: var(--muted); margin: 10px 0 0 0;">Your enrollment request is waiting for admin approval. You'll be able to access course content once approved.</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Rejected Enrollments -->
        <?php if (!empty($rejected)): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-times-circle" style="color: #f44336; font-size: 24px;"></i>
                Rejected Enrollments (<?php echo count($rejected); ?>)
            </div>
            <?php foreach ($rejected as $enrollment): ?>
                <div class="enrollment-card rejected">
                    <div class="enrollment-card-image">
                        <img src="<?php echo htmlspecialchars($enrollment['cover_image'] ?? '../../images/course-placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($enrollment['course_title']); ?>">
                    </div>
                    <div class="enrollment-card-content">
                        <div class="enrollment-card-header">
                            <div>
                                <h3 class="enrollment-card-title"><?php echo htmlspecialchars($enrollment['course_title']); ?></h3>
                                <p class="enrollment-card-instructor">By <?php echo htmlspecialchars($enrollment['instructor_name']); ?></p>
                            </div>
                            <span class="status-badge rejected">
                                <i class="fas fa-times"></i> Rejected
                            </span>
                        </div>
                        <div class="enrollment-card-meta">
                            <span><i class="fas fa-calendar"></i> Date: <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></span>
                        </div>
                        
                        <?php if (!empty($enrollment['rejection_reason'])): ?>
                        <div class="rejection-reason">
                            <div class="rejection-reason-label">
                                <i class="fas fa-comment"></i> Reason for Rejection:
                            </div>
                            <p style="margin: 8px 0 0 0; color: #555;">
                                <?php echo htmlspecialchars($enrollment['rejection_reason']); ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="rejection-reason">
                            <div class="rejection-reason-label">
                                <i class="fas fa-comment"></i> No Reason Provided
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="enroll.php?id=<?php echo $enrollment['course_id']; ?>" class="btn-action btn-reenroll">
                                <i class="fas fa-refresh"></i> Re-Enroll
                            </a>
                            <a href="courses.php" class="btn-action btn-rebrowse">
                                <i class="fas fa-search"></i> Browse Courses
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- No Enrollments -->
        <?php if (empty($enrollments)): ?>
        <div class="section">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>You haven't enrolled in any courses yet.</p>
                <a href="courses.php" style="padding: 10px 20px; background: var(--pw-500); color: white; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 15px;">
                    Browse Courses
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
