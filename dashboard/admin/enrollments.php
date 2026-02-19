<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$user = getCurrentUser();
$conn = getDbConnection();

// Handle approve action
if (isset($_GET['approve'])) {
    $enrollment_id = (int) $_GET['approve'];
    $update_query = "
        UPDATE enrollments 
        SET approval_status = 'approved', approved_by = {$user['id']}, approved_at = NOW(), rejection_reason = NULL
        WHERE id = $enrollment_id
    ";
    if (mysqli_query($conn, $update_query)) {
        $success = "Enrollment approved successfully!";
    } else {
        $error = "Failed to approve enrollment: " . mysqli_error($conn);
    }
}

// Handle reject action
if (isset($_POST['reject_enrollment'])) {
    $enrollment_id = (int) $_POST['enrollment_id'];
    $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason']);
    $update_query = "
        UPDATE enrollments 
        SET approval_status = 'rejected', approved_by = {$user['id']}, approved_at = NOW(), rejection_reason = '$reason'
        WHERE id = $enrollment_id
    ";
    if (mysqli_query($conn, $update_query)) {
        $success = "Enrollment rejected successfully!";
    } else {
        $error = "Failed to reject enrollment: " . mysqli_error($conn);
    }
}

// Handle remove action
if (isset($_GET['remove'])) {
    $enrollment_id = (int) $_GET['remove'];
    $delete_query = "DELETE FROM enrollments WHERE id = $enrollment_id";
    if (mysqli_query($conn, $delete_query)) {
        $success = "Enrollment removed successfully!";
    } else {
        $error = "Failed to remove enrollment: " . mysqli_error($conn);
    }
}

// Get filter parameter
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        e.id,
        e.enrollment_date,
        e.approval_status,
        e.approved_at,
        e.rejection_reason,
        u.username as user_name,
        u.email as user_email,
        c.title as course_title,
        a.username as approved_by_name
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users a ON e.approved_by = a.id
    WHERE 1=1
";

if ($status_filter !== 'all') {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND e.approval_status = '$status_filter'";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR c.title LIKE '%$search%')";
}

$query .= " ORDER BY e.enrollment_date DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    $error = "Database query failed: " . mysqli_error($conn);
    $enrollments = [];
} else {
    $enrollments = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Get statistics
$stats = [
    'total' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments"))['count'],
    'pending' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments WHERE approval_status = 'pending'"))['count'],
    'approved' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments WHERE approval_status = 'approved'"))['count'],
    'rejected' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM enrollments WHERE approval_status = 'rejected'"))['count'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Enrollments - Admin</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1400px; margin: 40px auto; padding: 20px; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-badge { padding: 15px; background: var(--pw-100); border-radius: 10px; text-align: center; }
        .stat-badge h4 { margin: 0 0 5px 0; color: var(--muted); font-size: 13px; text-transform: uppercase; }
        .stat-badge .number { font-size: 28px; font-weight: 700; color: var(--pw-500); margin: 0; }
        .stat-badge.pending .number { color: #FF9800; }
        .stat-badge.approved .number { color: #4CAF50; }
        .stat-badge.rejected .number { color: #f44336; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select, .filter-section button { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-section input { flex: 1; min-width: 200px; }
        .filter-section button { background: var(--pw-500); color: white; border: none; cursor: pointer; font-weight: 600; }
        .filter-section button:hover { background: var(--pw-600); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e2e2; font-size: 14px; }
        th { background: var(--pw-100); font-weight: 600; color: var(--text); }
        tr:hover { background: var(--pw-100); }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 8px; }
        .action-buttons a, .action-buttons button { padding: 6px 12px; border-radius: 5px; font-size: 12px; border: none; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-approve { background: #4CAF50; color: white; }
        .btn-approve:hover { background: #45a049; }
        .btn-reject { background: #ff9800; color: white; }
        .btn-reject:hover { background: #e68900; }
        .btn-remove { background: #f44336; color: white; }
        .btn-remove:hover { background: #da190b; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .empty-state { text-align: center; padding: 40px; color: var(--muted); }
        .empty-state i { font-size: 48px; color: #ddd; margin-bottom: 10px; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%; }
        .modal-content h3 { margin-top: 0; color: var(--pw-500); }
        .modal-content textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; font-family: Arial, sans-serif; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; }
        .modal-buttons .btn-cancel { background: #e0e0e0; color: #333; }
        .modal-buttons .btn-submit { background: #f44336; color: white; }
        .user-info { max-width: 200px; }
        .user-name { font-weight: 600; color: var(--text); }
        .user-email { font-size: 12px; color: var(--muted); }
        @media (max-width: 768px) {
            .table-container { overflow-x: auto; }
            .filter-section { flex-direction: column; }
            .filter-section input, .filter-section select { width: 100%; }
        }
    </style>
</head>
<body>
    <nav>
        <a href="../../index.php" class="logo">
            <img src="../../images/logo2.png" alt="">
        </a>
        <ul class="menu">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="lessons.php">Lessons</a></li>
            <li><a href="enrollments.php" class="active">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-clipboard-list"></i> Manage Course Enrollments</h1>
        <p style="color: var(--muted); margin-bottom: 20px;">Review and approve/reject student course enrollments</p>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-badge">
                <h4>Total</h4>
                <p class="number"><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-badge pending">
                <h4>Pending</h4>
                <p class="number"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="stat-badge approved">
                <h4>Approved</h4>
                <p class="number"><?php echo $stats['approved']; ?></p>
            </div>
            <div class="stat-badge rejected">
                <h4>Rejected</h4>
                <p class="number"><?php echo $stats['rejected']; ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="section">
            <form method="GET" class="filter-section">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search by user name, email, or course..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit"><i class="fas fa-search"></i> Filter</button>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="section">
            <?php if (empty($enrollments)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No enrollments found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($enrollment['user_name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($enrollment['user_email']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $enrollment['approval_status']; ?>">
                                            <?php echo ucfirst($enrollment['approval_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['approved_by_name']): ?>
                                            <small><?php echo htmlspecialchars($enrollment['approved_by_name']); ?><br><?php echo date('M d, Y', strtotime($enrollment['approved_at'])); ?></small>
                                        <?php else: ?>
                                            <small style="color: #999;">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($enrollment['approval_status'] === 'pending'): ?>
                                                <a href="?approve=<?php echo $enrollment['id']; ?>" class="btn-approve" title="Approve enrollment">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <button class="btn-reject" onclick="openRejectModal(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars(addslashes($enrollment['user_name'])); ?>')" title="Reject enrollment">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php elseif ($enrollment['approval_status'] === 'rejected'): ?>
                                                <a href="?approve=<?php echo $enrollment['id']; ?>" class="btn-approve" title="Approve this re-enrollment request">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <button class="btn-reject" onclick="openRejectModal(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars(addslashes($enrollment['user_name'])); ?>')" title="Reject enrollment">
                                                    <i class="fas fa-times"></i> Reject Again
                                                </button>
                                            <?php endif; ?>
                                            <a href="?remove=<?php echo $enrollment['id']; ?>" class="btn-remove" onclick="return confirm('Remove this enrollment?');" title="Remove enrollment">
                                                <i class="fas fa-trash"></i> Remove
                                            </a>
                                        </div>
                                        <?php if ($enrollment['rejection_reason']): ?>
                                            <small style="display: block; margin-top: 8px; color: #f44336;"><strong>Reason:</strong> <?php echo htmlspecialchars($enrollment['rejection_reason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <h3><i class="fas fa-times-circle"></i> Reject Enrollment</h3>
            <form method="POST">
                <input type="hidden" name="enrollment_id" id="enrollmentId">
                <p style="margin-bottom: 15px;">Are you sure you want to reject this enrollment? You can provide a reason below.</p>
                <textarea 
                    name="rejection_reason" 
                    placeholder="Enter reason for rejection (optional)..." 
                    rows="4"
                ></textarea>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject_enrollment" class="btn-submit">Reject Enrollment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(enrollmentId, studentName) {
            document.getElementById('enrollmentId').value = enrollmentId;
            document.getElementById('rejectModal').classList.add('active');
            document.querySelector('.modal-content p').textContent = `Are you sure you want to reject the enrollment for ${studentName}? You can provide a reason below.`;
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>
