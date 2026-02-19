<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$user = getCurrentUser();
$conn = getDbConnection();

// Handle add/edit lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $course_id = (int) $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $lesson_order = (int) $_POST['lesson_order'];
    $duration_minutes = (int) $_POST['duration_minutes'];
    $video_url = mysqli_real_escape_string($conn, $_POST['video_url']);

    if ($action === 'add') {
        $insert_query = "
            INSERT INTO lessons 
            (course_id, title, description, content, lesson_order, duration_minutes, video_url)
            VALUES ($course_id, '$title', '$description', '$content', $lesson_order, $duration_minutes, '$video_url')
        ";
        mysqli_query($conn, $insert_query);
        $success = "Lesson added successfully!";
    } elseif ($action === 'edit') {
        $lesson_id = (int) $_POST['lesson_id'];
        $update_query = "
            UPDATE lessons 
            SET title = '$title', description = '$description', content = '$content',
                lesson_order = $lesson_order, duration_minutes = $duration_minutes, video_url = '$video_url'
            WHERE id = $lesson_id
        ";
        mysqli_query($conn, $update_query);
        $success = "Lesson updated successfully!";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $lesson_id = (int) $_GET['delete'];
    mysqli_query($conn, "DELETE FROM lesson_progress WHERE lesson_id = $lesson_id");
    mysqli_query($conn, "DELETE FROM assignments WHERE lesson_id = $lesson_id");
    mysqli_query($conn, "DELETE FROM lessons WHERE id = $lesson_id");
    $success = "Lesson deleted successfully!";
}

// Get courses for dropdown
$courses_query = "SELECT id, title FROM courses ORDER BY title ASC";
$courses_result = mysqli_query($conn, $courses_query);
$courses = mysqli_fetch_all($courses_result, MYSQLI_ASSOC);

// Get lessons with course info
$lessons_query = "
    SELECT l.*, c.title as course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    ORDER BY c.title ASC, l.lesson_order ASC
";
$lessons_result = mysqli_query($conn, $lessons_query);
$lessons = mysqli_fetch_all($lessons_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lessons - Admin</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;
        }
        .form-group textarea { min-height: 150px; }
        .lessons-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .lessons-table th, .lessons-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .lessons-table th { background: var(--pw-100); font-weight: 600; }
        .action-btn { padding: 5px 10px; margin: 2px; border-radius: 3px; cursor: pointer; }
        .edit-btn { background: #2196F3; color: white; }
        .delete-btn { background: #f44336; color: white; }
        .success { background: #4CAF50; color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <nav>
        <a href="../../index.php" class="logo">
            <img src="../../images/logo2.png" alt="">
        </a>
        <ul class="menu">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="lessons.php" class="active">Lessons</a></li>
            <li><a href="enrollments.php">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-book"></i> Manage Lessons</h1>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2>Add New Lesson</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Course</label>
                    <select name="course_id" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Lesson Title</label>
                    <input type="text" name="title" required placeholder="e.g., Introduction to Variables">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of the lesson"></textarea>
                </div>

                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" placeholder="Full lesson content (HTML/Text)" required></textarea>
                </div>

                <div class="form-group">
                    <label>Lesson Order</label>
                    <input type="number" name="lesson_order" value="1" required>
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" value="30" required>
                </div>

                <div class="form-group">
                    <label>Video URL (optional)</label>
                    <input type="url" name="video_url" placeholder="https://youtube.com/...">
                </div>

                <button type="submit" class="main-btn" style="width: auto; padding: 10px 30px;">Add Lesson</button>
            </form>
        </div>

        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2>All Lessons</h2>
            <?php if (empty($lessons)): ?>
                <p>No lessons created yet. Create one above!</p>
            <?php else: ?>
                <table class="lessons-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Title</th>
                            <th>Order</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lesson['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                                <td><?php echo $lesson['lesson_order']; ?></td>
                                <td><?php echo $lesson['duration_minutes']; ?> min</td>
                                <td>
                                    <a href="?delete=<?php echo $lesson['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this lesson?');">Delete</a>
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
