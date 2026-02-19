<?php
require_once __DIR__ . '/../../includes/auth_helpers.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$user = getCurrentUser();
$conn = getDbConnection();

// Handle add/edit assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $lesson_id = (int) $_POST['lesson_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $due_date = !empty($_POST['due_date']) ? mysqli_real_escape_string($conn, $_POST['due_date']) : null;
    $max_points = (int) $_POST['max_points'];

    if ($action === 'add') {
        $due_date_sql = $due_date ? "'$due_date'" : 'NULL';
        $insert_query = "
            INSERT INTO assignments (lesson_id, title, description, due_date, max_points)
            VALUES ($lesson_id, '$title', '$description', $due_date_sql, $max_points)
        ";
        if (mysqli_query($conn, $insert_query)) {
            $success = "Assignment created successfully!";
        } else {
            $error = "Failed to create assignment: " . mysqli_error($conn);
        }
    } elseif ($action === 'edit') {
        $assignment_id = (int) $_POST['assignment_id'];
        $due_date_sql = $due_date ? "'$due_date'" : 'NULL';
        $update_query = "
            UPDATE assignments 
            SET lesson_id = $lesson_id, title = '$title', description = '$description', 
                due_date = $due_date_sql, max_points = $max_points
            WHERE id = $assignment_id
        ";
        if (mysqli_query($conn, $update_query)) {
            $success = "Assignment updated successfully!";
        } else {
            $error = "Failed to update assignment: " . mysqli_error($conn);
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $assignment_id = (int) $_GET['delete'];
    // Delete submissions first
    mysqli_query($conn, "DELETE FROM assignment_submissions WHERE assignment_id = $assignment_id");
    // Delete assignment
    if (mysqli_query($conn, "DELETE FROM assignments WHERE id = $assignment_id")) {
        $success = "Assignment deleted successfully!";
    } else {
        $error = "Failed to delete assignment: " . mysqli_error($conn);
    }
}

// Get filter parameters
$course_filter = $_GET['course'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get all courses
$courses_query = "SELECT id, title FROM courses ORDER BY title ASC";
$courses_result = mysqli_query($conn, $courses_query);
$courses = mysqli_fetch_all($courses_result, MYSQLI_ASSOC);

// Get all assignments
$query = "
    SELECT 
        a.id,
        a.lesson_id,
        a.title,
        a.description,
        a.due_date,
        a.max_points,
        a.created_at,
        l.title as lesson_title,
        l.course_id,
        c.title as course_title,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND grade IS NOT NULL) as graded_count
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE 1=1
";

if ($course_filter !== 'all') {
    $course_filter = (int) $course_filter;
    $query .= " AND c.id = $course_filter";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (a.title LIKE '%$search%' OR l.title LIKE '%$search%')";
}

$query .= " ORDER BY a.created_at DESC";

$result = mysqli_query($conn, $query);
$assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get editing assignment if requested
$editing = null;
$editing_course_id = null;
if (isset($_GET['edit'])) {
    $assignment_id = (int) $_GET['edit'];
    foreach ($assignments as $assignment) {
        if ($assignment['id'] == $assignment_id) {
            $editing = $assignment;
            $editing_course_id = $assignment['course_id'];
            break;
        }
    }
}

// Get all lessons for the form
$lessons_query = "
    SELECT l.id, l.title, c.title as course_title
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
    <title>Manage Assignments - Admin</title>
    <link rel="stylesheet" href="../../css/style.css"/>
    <link rel="stylesheet" href="../../css/dashboard.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container { max-width: 1400px; margin: 40px auto; padding: 20px; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; color: var(--text); font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .btn-submit { background: var(--pw-500); color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-submit:hover { background: var(--pw-600); }
        .btn-reset { background: #999; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-left: 10px; }
        .btn-reset:hover { background: #777; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-section input, .filter-section select, .filter-section button { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-section input { flex: 1; min-width: 200px; }
        .filter-section button { background: var(--pw-500); color: white; border: none; cursor: pointer; font-weight: 600; }
        .filter-section button:hover { background: var(--pw-600); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e2e2; font-size: 14px; }
        th { background: var(--pw-100); font-weight: 600; color: var(--text); }
        tr:hover { background: var(--pw-100); }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .action-buttons { display: flex; gap: 8px; }
        .action-buttons a, .action-buttons button { padding: 6px 12px; border-radius: 5px; font-size: 12px; border: none; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-edit:hover { background: #0b7dda; }
        .btn-delete { background: #f44336; color: white; }
        .btn-delete:hover { background: #da190b; }
        .empty-state { text-align: center; padding: 40px; color: var(--muted); }
        .empty-state i { font-size: 48px; color: #ddd; margin-bottom: 10px; }
        .form-section-title { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: var(--pw-100); padding: 15px; border-radius: 8px; text-align: center; }
        .stat-card-label { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
        .stat-card-value { font-size: 24px; font-weight: 700; color: var(--pw-500); }
        .course-label { font-size: 12px; color: #999; font-weight: 600; }
        .lesson-label { font-weight: 600; color: var(--text); }
    </style>
    <script>
        function filterLessonsByCoursee() {
            const courseSelect = document.getElementById('course_id');
            const lessonSelect = document.getElementById('lesson_id');
            const selectedCourse = courseSelect.value;
            const allOptions = lessonSelect.querySelectorAll('option');
            
            // Keep the default option visible
            const defaultOption = lessonSelect.querySelector('option[value=""]');
            if (defaultOption) {
                defaultOption.style.display = 'block';
            }
            
            // Filter lesson options based on selected course
            allOptions.forEach(option => {
                if (option.value === '') {
                    return; // Skip default option
                }
                
                const courseId = option.getAttribute('data-course');
                if (selectedCourse === '') {
                    option.style.display = 'block';
                } else if (courseId === selectedCourse) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Reset lesson selection if current selection is hidden
            if (lessonSelect.value) {
                const selectedOption = lessonSelect.querySelector(`option[value="${lessonSelect.value}"]`);
                if (selectedOption && selectedOption.style.display === 'none') {
                    lessonSelect.value = '';
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course_id');
            if (courseSelect) {
                courseSelect.addEventListener('change', filterLessonsByCoursee);
                // Run once on load to set initial state
                filterLessonsByCoursee();
            }
        });
    </script>
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
            <li><a href="assignments.php" class="active">Assignments</a></li>
            <li><a href="enrollments.php">Enrollments</a></li>
            <li><a href="../../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="admin-container">
        <h1><i class="fas fa-tasks"></i> Manage Assignments</h1>
        <p style="color: var(--muted); margin-bottom: 20px;">Create and manage course assignments for lessons</p>

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

        <!-- Create/Edit Assignment Form -->
        <div class="section">
            <h2 class="form-section-title">
                <i class="fas fa-plus-circle"></i> <?php echo $editing ? 'Edit Assignment' : 'Create New Assignment'; ?>
            </h2>

            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="assignment_id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="course_id">Select Course <span style="color: red;">*</span></label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select a Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                    <?php echo ($editing && isset($editing_course_id) && $editing_course_id == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="lesson_id">Select Lesson <span style="color: red;">*</span></label>
                        <select id="lesson_id" name="lesson_id" required>
                            <option value="">-- Select a Lesson --</option>
                            <?php 
                            foreach ($lessons as $lesson):
                            ?>
                                <option value="<?php echo $lesson['id']; ?>" 
                                    data-course="<?php echo $lesson['course_id']; ?>"
                                    <?php echo ($editing && $editing['lesson_id'] == $lesson['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Assignment Title <span style="color: red;">*</span></label>
                        <input type="text" id="title" name="title" 
                            value="<?php echo $editing ? htmlspecialchars($editing['title']) : ''; ?>" 
                            placeholder="e.g., Quiz on Variables" required>
                    </div>

                    <div class="form-group">
                        <label for="max_points">Maximum Points</label>
                        <input type="number" id="max_points" name="max_points" 
                            value="<?php echo $editing ? $editing['max_points'] : '100'; ?>" 
                            min="0" max="1000">
                    </div>

                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="datetime-local" id="due_date" name="due_date" 
                            value="<?php echo $editing && $editing['due_date'] ? date('Y-m-d\TH:i', strtotime($editing['due_date'])) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                        placeholder="Describe the assignment requirements..."><?php echo $editing ? htmlspecialchars($editing['description']) : ''; ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editing ? 'Update Assignment' : 'Create Assignment'; ?>
                    </button>
                    <?php if ($editing): ?>
                        <a href="assignments.php" class="btn-reset">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="section">
            <form method="GET" class="filter-section">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search by assignment or lesson name..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <select name="course">
                    <option value="all" <?php echo $course_filter === 'all' ? 'selected' : ''; ?>>All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-search"></i> Filter</button>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="section">
            <h2 style="margin-top: 0;">All Assignments</h2>

            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No assignments found. Create your first assignment!</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Assignment Title</th>
                                <th>Lesson</th>
                                <th>Course</th>
                                <th>Due Date</th>
                                <th>Points</th>
                                <th>Submissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                        <?php if ($assignment['description']): ?>
                                            <div style="font-size: 12px; color: #999; margin-top: 4px;">
                                                <?php echo substr(htmlspecialchars($assignment['description']), 0, 50) . '...'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="lesson-label"><?php echo htmlspecialchars($assignment['lesson_title']); ?></span>
                                    </td>
                                    <td>
                                        <span class="course-label"><?php echo htmlspecialchars($assignment['course_title']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($assignment['due_date']): ?>
                                            <?php echo date('M d, Y H:i', strtotime($assignment['due_date'])); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">No deadline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $assignment['max_points']; ?> pts</td>
                                    <td>
                                        <span class="badge badge-active">
                                            <?php echo $assignment['graded_count']; ?>/<?php echo $assignment['submission_count']; ?> Graded
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $assignment['id']; ?>" class="btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $assignment['id']; ?>" class="btn-delete" onclick="return confirm('Delete this assignment and all submissions?');" title="Delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 40px; background: var(--pw-100);">
        <p>&copy; 2026 Computer Coaching Platform. All rights reserved.</p>
    </footer>
</body>
</html>
