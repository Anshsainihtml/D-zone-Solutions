<?php
/**
 * Seed sample lessons for SIGMA course
 * Run this once to populate lessons in the database
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

// Check if SIGMA course exists in DB, otherwise use fallback id
$query = "SELECT id FROM courses WHERE title LIKE '%SIGMA%' LIMIT 1";
$result = mysqli_query($conn, $query);
$course = mysqli_fetch_assoc($result);

if (!$course) {
    echo "SIGMA course not found in database. Please create it first.\n";
    exit;
}

$course_id = $course['id'];

// Sample lessons for SIGMA course
$lessons = [
    [
        'title' => 'Introduction to Data Structures',
        'description' => 'Understand the fundamentals of data structures and why they matter',
        'content' => '<h3>What are Data Structures?</h3><p>Data structures are specialized formats for organizing, processing, and storing data efficiently.</p><h4>Key Topics:</h4><ul><li>Arrays and Lists</li><li>Stacks and Queues</li><li>Trees and Graphs</li><li>Hash Tables</li></ul>',
        'lesson_order' => 1,
        'duration_minutes' => 45,
        'video_url' => ''
    ],
    [
        'title' => 'Arrays and Linked Lists',
        'description' => 'Master the most fundamental data structures',
        'content' => '<h3>Arrays vs Linked Lists</h3><p>Arrays provide O(1) access but O(n) insertion. Linked lists offer O(n) access but O(1) insertion if you have the pointer.</p>',
        'lesson_order' => 2,
        'duration_minutes' => 60,
        'video_url' => ''
    ],
    [
        'title' => 'Stacks and Queues',
        'description' => 'LIFO and FIFO data structures with real-world applications',
        'content' => '<h3>Stacks (LIFO)</h3><p>Last In, First Out - used by browsers for back button, undo functionality, etc.</p><h3>Queues (FIFO)</h3><p>First In, First Out - used in scheduling, BFS, printer queues, etc.</p>',
        'lesson_order' => 3,
        'duration_minutes' => 50,
        'video_url' => ''
    ],
    [
        'title' => 'Trees and Binary Search Trees',
        'description' => 'Hierarchical data structures and searching techniques',
        'content' => '<h3>Tree Basics</h3><p>Trees are hierarchical data structures with a root and branches.</p><h3>BST Properties</h3><p>Left subtree < Root < Right subtree. Enables O(log n) search in balanced trees.</p>',
        'lesson_order' => 4,
        'duration_minutes' => 70,
        'video_url' => ''
    ],
    [
        'title' => 'Graphs and Graph Traversal',
        'description' => 'Learn to work with complex network structures',
        'content' => '<h3>Graph Basics</h3><p>Graphs consist of vertices (nodes) and edges connecting them.</p><h3>Traversal Methods</h3><p>BFS (Breadth-First Search) and DFS (Depth-First Search) are fundamental for solving graph problems.</p>',
        'lesson_order' => 5,
        'duration_minutes' => 65,
        'video_url' => ''
    ],
    [
        'title' => 'Introduction to Web Development',
        'description' => 'Getting started with HTML, CSS, and JavaScript',
        'content' => '<h3>Web Fundamentals</h3><p>The web is built on three pillars: HTML (structure), CSS (styling), and JavaScript (interactivity).</p><h4>What You\'ll Learn:</h4><ul><li>HTML semantic markup</li><li>CSS Flexbox and Grid</li><li>JavaScript DOM manipulation</li></ul>',
        'lesson_order' => 6,
        'duration_minutes' => 55,
        'video_url' => ''
    ],
    [
        'title' => 'Building with React',
        'description' => 'Modern frontend development with React components',
        'content' => '<h3>React Fundamentals</h3><p>React is a JavaScript library for building user interfaces with reusable components.</p><h4>Core Concepts:</h4><ul><li>Components and Props</li><li>State and Hooks</li><li>Lifecycle methods</li><li>Virtual DOM</li></ul>',
        'lesson_order' => 7,
        'duration_minutes' => 80,
        'video_url' => ''
    ],
    [
        'title' => 'Backend Development with Node.js',
        'description' => 'Server-side JavaScript with Express framework',
        'content' => '<h3>Node.js Basics</h3><p>Run JavaScript on the server side for handling databases, authentication, and business logic.</p><h4>Topics:</h4><ul><li>Express.js framework</li><li>Routing and middleware</li><li>RESTful APIs</li><li>Error handling</li></ul>',
        'lesson_order' => 8,
        'duration_minutes' => 90,
        'video_url' => ''
    ],
    [
        'title' => 'Database Design and SQL',
        'description' => 'Relational databases, schemas, and queries',
        'content' => '<h3>SQL Fundamentals</h3><p>Learn to design efficient database schemas and write optimized queries.</p><h4>Key Topics:</h4><ul><li>Normalization</li><li>ACID properties</li><li>Indexing</li><li>JOIN operations</li></ul>',
        'lesson_order' => 9,
        'duration_minutes' => 75,
        'video_url' => ''
    ],
    [
        'title' => 'Version Control with Git & GitHub',
        'description' => 'Collaborate effectively with Git and GitHub',
        'content' => '<h3>Git Basics</h3><p>Version control is essential for team projects and maintaining code history.</p><h4>Workflows:</h4><ul><li>Commits and branches</li><li>Pull requests</li><li>Merge strategies</li><li>GitHub collaboration</li></ul>',
        'lesson_order' => 10,
        'duration_minutes' => 40,
        'video_url' => ''
    ]
];

$added = 0;
foreach ($lessons as $lesson) {
    $title = mysqli_real_escape_string($conn, $lesson['title']);
    $description = mysqli_real_escape_string($conn, $lesson['description']);
    $content = mysqli_real_escape_string($conn, $lesson['content']);
    $lesson_order = $lesson['lesson_order'];
    $duration_minutes = $lesson['duration_minutes'];
    $video_url = mysqli_real_escape_string($conn, $lesson['video_url']);

    $insert_query = "
        INSERT INTO lessons (course_id, title, description, content, lesson_order, duration_minutes, video_url)
        VALUES ($course_id, '$title', '$description', '$content', $lesson_order, $duration_minutes, '$video_url')
    ";

    if (mysqli_query($conn, $insert_query)) {
        $added++;
        echo "✓ Added: $title\n";
    } else {
        echo "✗ Failed: $title - " . mysqli_error($conn) . "\n";
    }
}

echo "\nTotal lessons added: $added\n";
mysqli_close($conn);
?>
