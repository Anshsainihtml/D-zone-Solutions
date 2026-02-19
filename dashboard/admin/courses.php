<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$user = getCurrentUser();
$conn = getDbConnection();

// Handle add/edit course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $duration_months = (int) $_POST['duration_months'];
    $instructor_id = (int) $_POST['instructor_id'];
    $cover_image = mysqli_real_escape_string($conn, $_POST['cover_image']);

    if ($action === 'add') {
        $insert_query = "
            INSERT INTO courses (title, description, instructor_id, level, duration_months, cover_image)
            VALUES ('$title', '$description', $instructor_id, '$level', $duration_months, '$cover_image')
        ";
        if (mysqli_query($conn, $insert_query)) {
            $success = "Course created successfully!";
        } else {
            $error = "Failed to create course: " . mysqli_error($conn);
        }
    } elseif ($action === 'edit') {
        $course_id = (int) $_POST['course_id'];
        $update_query = "
            UPDATE courses 
            SET title = '$title', description = '$description', level = '$level',
                duration_months = $duration_months, instructor_id = $instructor_id, cover_image = '$cover_image'
            WHERE id = $course_id
        ";
        if (mysqli_query($conn, $update_query)) {
            $success = "Course updated successfully!";
        } else {
            $error = "Failed to update course: " . mysqli_error($conn);
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $course_id = (int) $_GET['delete'];
    // Delete related data in correct order (respecting foreign key constraints)
    $lessons_query = "SELECT id FROM lessons WHERE course_id = $course_id";
    $lessons = mysqli_fetch_all(mysqli_query($conn, $lessons_query), MYSQLI_ASSOC);
    
    foreach ($lessons as $lesson) {
        // First delete assignment submissions
        mysqli_query($conn, "DELETE FROM assignment_submissions WHERE assignment_id IN (SELECT id FROM assignments WHERE lesson_id = {$lesson['id']})");
        // Then delete assignments
        mysqli_query($conn, "DELETE FROM assignments WHERE lesson_id = {$lesson['id']}");
        // Then delete lesson progress
        mysqli_query($conn, "DELETE FROM lesson_progress WHERE lesson_id = {$lesson['id']}");
    }
    
    mysqli_query($conn, "DELETE FROM lessons WHERE course_id = $course_id");
    mysqli_query($conn, "DELETE FROM enrollments WHERE course_id = $course_id");
    mysqli_query($conn, "DELETE FROM courses WHERE id = $course_id");
    $success = "Course deleted successfully!";
}

// Get all instructors
$instructors_query = "SELECT id, username FROM users WHERE role = 'admin' OR role = 'user' ORDER BY username ASC";
$instructors_result = mysqli_query($conn, $instructors_query);
$instructors = mysqli_fetch_all($instructors_result, MYSQLI_ASSOC);

// Get all courses
$courses_query = "
    SELECT c.*, u.username as instructor_name, 
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    ORDER BY c.created_at DESC
";
$courses_result = mysqli_query($conn, $courses_query);
$courses = mysqli_fetch_all($courses_result, MYSQLI_ASSOC);

$editing = null;
if (isset($_GET['edit'])) {
    $course_id = (int) $_GET['edit'];
    foreach ($courses as $course) {
        if ($course['id'] == $course_id) {
            $editing = $course;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Admin</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }
        .courses-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .courses-table th { background: var(--pw-100); padding: 15px; text-align: left; font-weight: 600; }
        .courses-table td { padding: 15px; border-bottom: 1px solid var(--pw-200); }
        .courses-table tr:hover { background: var(--pw-100); }
        .action-btn { padding: 8px 12px; margin: 2px; border-radius: 5px; cursor: pointer; border: none; font-weight: 600; text-decoration: none; display: inline-block; }
        .edit-btn { background: #2196F3; color: white; }
        .edit-btn:hover { background: #0b7dda; }
        .delete-btn { background: #f44336; color: white; }
        .delete-btn:hover { background: #da190b; }
        .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .level-badge { display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .level-beginner { background: #c8e6c9; color: #2e7d32; }
        .level-intermediate { background: #fff9c4; color: #f57f17; }
        .level-advanced { background: #ffccbc; color: #d84315; }
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
            <li><a href="courses.php" class="active">Courses</a></li>
            <li><a href="lessons.php">Lessons</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="enrollments.php">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-book"></i> Manage Courses</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><?php echo $editing ? 'Edit Course' : 'Create New Course'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="course_id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Course Title</label>
                    <input type="text" name="title" value="<?php echo $editing ? htmlspecialchars($editing['title']) : ''; ?>" required placeholder="e.g., SIGMA Σ">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required placeholder="Course description..."><?php echo $editing ? htmlspecialchars($editing['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Instructor</label>
                    <select name="instructor_id" required>
                        <option value="">Select an instructor</option>
                        <?php foreach ($instructors as $instr): ?>
                            <option value="<?php echo $instr['id']; ?>" <?php echo ($editing && $editing['instructor_id'] == $instr['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($instr['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Level</label>
                    <select name="level" required>
                        <option value="beginner" <?php echo ($editing && $editing['level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo ($editing && $editing['level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo ($editing && $editing['level'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Duration (months)</label>
                    <input type="number" name="duration_months" value="<?php echo $editing ? $editing['duration_months'] : ''; ?>" required min="1">
                </div>

                <div class="form-group">
                    <label>Cover Image URL</label>
                    <input type="url" name="cover_image" id="cover_image_input" value="<?php echo $editing ? htmlspecialchars($editing['cover_image']) : ''; ?>" placeholder="https://example.com/image.jpg" onchange="updateImagePreview()">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Enter full URL (https://...) or relative path (../../images/image.png)
                    </small>
                    <div id="image_preview" style="margin-top: 10px;">
                        <?php if ($editing && !empty($editing['cover_image'])): 
                            $previewUrl = $editing['cover_image'];
                            if (!filter_var($previewUrl, FILTER_VALIDATE_URL) && substr($previewUrl, 0, 1) !== '/') {
                                $previewUrl = '../../images/' . basename($previewUrl);
                            }
                        ?>
                            <img src="<?php echo htmlspecialchars($previewUrl); ?>" 
                                 alt="Preview" 
                                 style="max-width: 300px; max-height: 200px; border-radius: 5px; border: 1px solid #ddd; margin-top: 10px;"
                                 onerror="this.style.display='none';">
                        <?php endif; ?>
                    </div>
                </div>
                
                <script>
                function updateImagePreview() {
                    const input = document.getElementById('cover_image_input');
                    const preview = document.getElementById('image_preview');
                    const imageUrl = input.value.trim();
                    
                    if (imageUrl) {
                        preview.innerHTML = '<img src="' + imageUrl + '" alt="Preview" style="max-width: 300px; max-height: 200px; border-radius: 5px; border: 1px solid #ddd; margin-top: 10px;" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<p style=\'color:red;\'>Image not found</p>\';">';
                    } else {
                        preview.innerHTML = '';
                    }
                }
                </script>

                <button type="submit" class="main-btn" style="width: auto; padding: 10px 30px;">
                    <?php echo $editing ? 'Update Course' : 'Create Course'; ?>
                </button>
                <?php if ($editing): ?>
                    <a href="?courses.php" class="main-btn" style="width: auto; padding: 10px 30px; background: #999; text-decoration: none;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h2>All Courses</h2>
            <?php if (empty($courses)): ?>
                <p style="text-align: center; color: #999;">No courses found.</p>
            <?php else: ?>
                <table class="courses-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Level</th>
                            <th>Duration</th>
                            <th>Lessons</th>
                            <th>Enrollments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): 
                            $imageUrl = !empty($course['cover_image']) ? $course['cover_image'] : '../../images/course-placeholder.png';
                            if (!empty($course['cover_image']) && !filter_var($course['cover_image'], FILTER_VALIDATE_URL) && substr($course['cover_image'], 0, 1) !== '/') {
                                $imageUrl = '../../images/' . basename($course['cover_image']);
                            }
                        ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                         alt="<?php echo htmlspecialchars($course['title']); ?>" 
                                         style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;"
                                         onerror="this.src='../../images/course-placeholder.png'">
                                </td>
                                <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                                <td><span class="level-badge level-<?php echo $course['level']; ?>"><?php echo ucfirst($course['level']); ?></span></td>
                                <td><?php echo $course['duration_months']; ?> months</td>
                                <td><?php echo $course['lesson_count']; ?></td>
                                <td><?php echo $course['enrollment_count']; ?></td>
                                <td>
                                    <a href="?edit=<?php echo $course['id']; ?>" class="action-btn edit-btn">Edit</a>
                                    <a href="?delete=<?php echo $course['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this course and all related data?');">Delete</a>
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
