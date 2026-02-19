<?php
require_once __DIR__ . '/../includes/auth_helpers.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('dashboard/user/'));
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$username || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = getDbConnection();
        $username = mysqli_real_escape_string($conn, $username);
        $email = mysqli_real_escape_string($conn, $email);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hash = mysqli_real_escape_string($conn, $hash);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' OR username = '$username' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $error = 'Email or username already registered.';
        } else {
            $q = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hash', 'user')";
            if (mysqli_query($conn, $q)) {
                $success = 'Registration successful. <a href="login.php">Login here</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - D-zone solution</title>
    <link rel="stylesheet" href="../css/style.css"/>
    <style>
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(180deg, var(--pw-100) 0%, #ffffff 100%); }
        .auth-box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
        .auth-box h1 { margin: 0 0 24px; font-size: 1.5rem; color: var(--text); }
        .auth-box .form-group { margin-bottom: 16px; }
        .auth-box label { display: block; margin-bottom: 6px; font-size: 0.9rem; color: var(--muted); }
        .auth-box input[type="text"], .auth-box input[type="email"], .auth-box input[type="password"] { width: 100%; padding: 12px; border: 1px solid #e2e2e2; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        .auth-box .error { color: #c0392b; font-size: 0.9rem; margin-bottom: 12px; }
        .auth-box .success { color: #27ae60; font-size: 0.9rem; margin-bottom: 12px; }
        .auth-box .main-btn { width: 100%; justify-content: center; margin-top: 8px; cursor: pointer; }
        .auth-box .link { display: block; text-align: center; margin-top: 16px; color: var(--pw-500); font-size: 0.9rem; }
        .auth-box .link:hover { text-decoration: underline; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Register</h1>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?php echo $success; ?></p><?php endif; ?>
        <?php if (!$success): ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required minlength="3">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="main-btn">Register</button>
        </form>
        <a href="login.php" class="link">Already have an account? Login</a>
        <?php endif; ?>
        <a href="../index.php" class="link">Back to Home</a>
    </div>
</body>
</html>
