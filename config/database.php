<?php
/**
 * Database configuration - update these for your environment.
 * XAMPP default: host=localhost, user=root, password='', database=youtubedata
 *
 * If the site runs in a subfolder (e.g. /myproject/), set BASE_PATH to that path:
 *   define('BASE_PATH', '/myproject');
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'youtubedata');

// Base URL path ('' if site is at document root; e.g. '/Website-Built-Using-PHP-MYSQL-Youtube-Taversity' if in subfolder)
define('BASE_PATH', '');

function getDbConnection() {
    static $connection = null;
    if ($connection === null) {
        $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME,3307);
        if (!$connection) {
            die('Database connection failed: ' . mysqli_connect_error());
        }
        mysqli_set_charset($connection, 'utf8mb4');
    }
    return $connection;
}
