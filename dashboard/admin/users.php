<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$user = getCurrentUser();
$conn = getDbConnection();

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'change_role') {
        $user_id = (int) $_POST['user_id'];
        $new_role = mysqli_real_escape_string($conn, $_POST['role']);
        
        if ($new_role === 'admin' || $new_role === 'user') {
            $update_query = "UPDATE users SET role = '$new_role' WHERE id = $user_id";
            if (mysqli_query($conn, $update_query)) {
                $success = "User role updated successfully!";
            } else {
                $error = "Failed to update role.";
            }
        }
    } elseif ($action === 'delete_user') {
        $user_id = (int) $_POST['user_id'];
        // Don't allow deleting yourself
        if ($user_id !== $user['id']) {
            $delete_query = "DELETE FROM users WHERE id = $user_id";
            if (mysqli_query($conn, $delete_query)) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        } else {
            $error = "You cannot delete your own account!";
        }
    }
}

// Get all users
$users_query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$users_result = mysqli_query($conn, $users_query);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .admin-nav { display: flex; gap: 20px; margin-bottom: 30px; border-bottom: 2px solid var(--pw-200); padding-bottom: 15px; }
        .admin-nav a { color: var(--text); text-decoration: none; font-weight: 600; padding: 10px 0; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .admin-nav a.active { color: var(--pw-500); border-bottom-color: var(--pw-500); }
        .admin-nav a:hover { color: var(--pw-500); }
        .users-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .users-table th { background: var(--pw-100); padding: 15px; text-align: left; font-weight: 600; color: var(--text); }
        .users-table td { padding: 15px; border-bottom: 1px solid var(--pw-200); }
        .users-table tr:hover { background: var(--pw-100); }
        .role-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .role-admin { background: #e3f2fd; color: #1976d2; }
        .role-user { background: #f3e5f5; color: #7b1fa2; }
        .action-form { display: inline; }
        .action-btn { padding: 8px 12px; margin: 2px; border-radius: 5px; cursor: pointer; border: none; font-weight: 600; transition: all 0.3s; }
        .promote-btn { background: #4CAF50; color: white; }
        .promote-btn:hover { background: #45a049; }
        .demote-btn { background: #ff9800; color: white; }
        .demote-btn:hover { background: #e68900; }
        .delete-btn { background: #f44336; color: white; }
        .delete-btn:hover { background: #da190b; }
        .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .stat-card h3 { margin: 0; color: var(--muted); font-size: 14px; font-weight: 600; text-transform: uppercase; }
        .stat-card .number { margin: 10px 0 0 0; font-size: 32px; font-weight: 700; color: var(--pw-500); }
    </style>
</head>
<body>
    <nav>
        <a href="../../index.php" class="logo">
            <img src="../../images/logo2.png" alt="">
        </a>
        <ul class="menu">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="users.php" class="active">Users</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="lessons.php">Lessons</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments.php">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-users"></i> Manage Users</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p class="number"><?php echo count($users); ?></p>
            </div>
            <div class="stat-card">
                <h3>Admins</h3>
                <p class="number"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></p>
            </div>
            <div class="stat-card">
                <h3>Regular Users</h3>
                <p class="number"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'user')); ?></p>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <?php if (empty($users)): ?>
                <p style="text-align: center; color: #999;">No users found.</p>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <form class="action-form" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="role" value="<?php echo $u['role'] === 'admin' ? 'user' : 'admin'; ?>">
                                            <button type="submit" class="action-btn <?php echo $u['role'] === 'admin' ? 'demote-btn' : 'promote-btn'; ?>">
                                                <?php echo $u['role'] === 'admin' ? 'Demote' : 'Promote'; ?>
                                            </button>
                                        </form>
                                        <form class="action-form" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="action-btn delete-btn" onclick="return confirm('Delete this user?');">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">You (Admin)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
