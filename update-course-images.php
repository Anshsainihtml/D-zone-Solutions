<?php
/**
 * Script to update course cover images in the database
 * Usage: Run this file in browser or via command line
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Array of course images - Update this with your course IDs and image URLs
$courseImages = [
    // Format: course_id => image_url
    // Example:
    // 1 => 'https://example.com/images/course1.jpg',
    // 2 => 'https://example.com/images/course2.jpg',
    // 3 => '../../images/course-placeholder.png', // For relative paths
    
    // Add your course IDs and image URLs here:
    // 1 => 'https://via.placeholder.com/400x300?text=Course+1',
    // 2 => 'https://via.placeholder.com/400x300?text=Course+2',
];

// If running via browser (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'update' && !empty($courseImages)) {
        $updated = 0;
        $errors = [];
        
        foreach ($courseImages as $courseId => $imageUrl) {
            $courseId = (int) $courseId;
            $imageUrl = mysqli_real_escape_string($conn, $imageUrl);
            
            $updateQuery = "UPDATE courses SET cover_image = '$imageUrl' WHERE id = $courseId";
            
            if (mysqli_query($conn, $updateQuery)) {
                $updated++;
            } else {
                $errors[] = "Course ID $courseId: " . mysqli_error($conn);
            }
        }
        
        echo "<h2>Update Results</h2>";
        echo "<p style='color: green;'>Successfully updated $updated course(s)</p>";
        if (!empty($errors)) {
            echo "<h3>Errors:</h3><ul>";
            foreach ($errors as $error) {
                echo "<li style='color: red;'>$error</li>";
            }
            echo "</ul>";
        }
        echo "<p><a href='dashboard/admin/courses.php'>Go to Admin Panel</a></p>";
        exit;
    }
}

// Get all courses
$coursesQuery = "SELECT id, title, cover_image FROM courses ORDER BY id";
$coursesResult = mysqli_query($conn, $coursesQuery);
$courses = mysqli_fetch_all($coursesResult, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Course Images</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .course-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            margin: 10px 0;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .course-info {
            flex: 1;
        }
        .course-info strong {
            color: #333;
            font-size: 16px;
        }
        .course-info small {
            color: #666;
            display: block;
            margin-top: 5px;
        }
        input[type="text"] {
            flex: 2;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-update-all {
            background: #2196F3;
            margin-top: 20px;
            padding: 15px 30px;
            font-size: 16px;
        }
        .btn-update-all:hover {
            background: #0b7dda;
        }
        .instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
        .instructions h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 Update Course Cover Images</h1>
        
        <div class="instructions">
            <h3>Instructions:</h3>
            <ul>
                <li>Enter image URLs for each course below</li>
                <li>You can use full URLs (https://example.com/image.jpg) or relative paths (../../images/image.png)</li>
                <li>Leave empty to keep current image or set to NULL</li>
                <li>Click "Update All" to save all changes at once</li>
            </ul>
        </div>

        <form method="POST" id="updateForm">
            <?php foreach ($courses as $course): ?>
            <div class="course-item">
                <div class="course-info">
                    <strong>Course #<?php echo $course['id']; ?>: <?php echo htmlspecialchars($course['title']); ?></strong>
                    <small>Current: <?php echo htmlspecialchars($course['cover_image'] ?: 'No image set'); ?></small>
                </div>
                <input 
                    type="text" 
                    name="images[<?php echo $course['id']; ?>]" 
                    value="<?php echo htmlspecialchars($course['cover_image']); ?>"
                    placeholder="Enter image URL..."
                >
            </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-update-all">💾 Update All Courses</button>
        </form>
    </div>

    <?php
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['images'])) {
        $updated = 0;
        $errors = [];
        
        foreach ($_POST['images'] as $courseId => $imageUrl) {
            $courseId = (int) $courseId;
            $imageUrl = trim($imageUrl);
            
            // If empty, set to NULL
            if (empty($imageUrl)) {
                $imageUrl = 'NULL';
                $updateQuery = "UPDATE courses SET cover_image = NULL WHERE id = $courseId";
            } else {
                $imageUrl = mysqli_real_escape_string($conn, $imageUrl);
                $updateQuery = "UPDATE courses SET cover_image = '$imageUrl' WHERE id = $courseId";
            }
            
            if (mysqli_query($conn, $updateQuery)) {
                $updated++;
            } else {
                $errors[] = "Course ID $courseId: " . mysqli_error($conn);
            }
        }
        
        echo "<div class='container' style='margin-top: 20px;'>";
        echo "<h2 style='color: #4CAF50;'>✅ Update Complete!</h2>";
        echo "<p><strong>Successfully updated $updated course(s)</strong></p>";
        
        if (!empty($errors)) {
            echo "<h3 style='color: #f44336;'>Errors:</h3><ul>";
            foreach ($errors as $error) {
                echo "<li style='color: #f44336;'>$error</li>";
            }
            echo "</ul>";
        }
        
        echo "<p><a href='dashboard/admin/courses.php' class='btn'>Go to Admin Panel</a></p>";
        echo "<p><a href='update-course-images.php' class='btn' style='background: #666;'>Refresh Page</a></p>";
        echo "</div>";
    }
    ?>
</body>
</html>
