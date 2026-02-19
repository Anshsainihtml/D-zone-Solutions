<?php
/**
 * Authentication helpers: session, current user, role checks.
 * Include this at the top of any page that needs auth.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Get current logged-in user array or null.
 */
function getCurrentUser() {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $conn = getDbConnection();
    $id = (int) $_SESSION['user_id'];
    $res = mysqli_query($conn, "SELECT id, username, email, role, created_at FROM users WHERE id = $id LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) {
        return null;
    }
    return mysqli_fetch_assoc($res);
}

function isLoggedIn() {
    return getCurrentUser() !== null;
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Redirect to login if not logged in.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $base = getBaseUrl();
        if ($base !== '') {
            $loginUrl = $base . '/auth/login.php';
        } else {
            $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
            $depth = $path ? count(explode('/', $path)) : 0;
            $loginUrl = str_repeat('../', $depth) . 'auth/login.php';
        }
        header('Location: ' . $loginUrl . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

/**
 * Redirect to login or to home if not admin.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $base = getBaseUrl();
        if ($base !== '') {
            header('Location: ' . $base . '/index.php');
        } else {
            $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
            $depth = $path ? count(explode('/', $path)) : 0;
            header('Location: ' . str_repeat('../', $depth) . 'index.php');
        }
        exit;
    }
}

function getBaseUrl() {
    return rtrim(BASE_PATH, '/');
}

/** Build URL from project root (e.g. baseUrl('auth/login.php')). */
function baseUrl($path = '') {
    $base = rtrim(BASE_PATH, '/');
    $path = ltrim($path, '/');
    return $base . ($path ? '/' . $path : '');
}
