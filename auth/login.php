<?php
require_once __DIR__ . '/../includes/auth_helpers.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '../dashboard/admin/' : '../index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Please enter email and password.';
    } else {
        $conn = getDbConnection();
        $email = mysqli_real_escape_string($conn, $email);
        $res = mysqli_query($conn, "SELECT id, username, password, role FROM users WHERE email = '$email' LIMIT 1");
        if ($res && mysqli_num_rows($res) === 1) {
            $user = mysqli_fetch_assoc($res);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $redirect = $_GET['redirect'] ?? '';
                if ($redirect && strpos($redirect, '..') === false) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . ($user['role'] === 'admin' ? '../dashboard/admin/' : '../index.php'));
                }
                exit;
            }
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - D-zone solution</title>
    <link rel="stylesheet" href="../css/style.css"/>
    <style>
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(180deg, var(--pw-100) 0%, #ffffff 100%); }
        .auth-box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
        .auth-box h1 { margin: 0 0 24px; font-size: 1.5rem; color: var(--text); }
        .auth-box .form-group { margin-bottom: 16px; }
        .auth-box label { display: block; margin-bottom: 6px; font-size: 0.9rem; color: var(--muted); }
        .auth-box input[type="text"], .auth-box input[type="email"], .auth-box input[type="password"] { width: 100%; padding: 12px; border: 1px solid #e2e2e2; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        .auth-box .error { color: #c0392b; font-size: 0.9rem; margin-bottom: 12px; }
        .auth-box .main-btn { width: 100%; justify-content: center; margin-top: 8px; cursor: pointer; }
        .auth-box .link { display: block; text-align: center; margin-top: 16px; color: var(--pw-500); font-size: 0.9rem; }
        .auth-box .link:hover { text-decoration: underline; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Login</h1>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="main-btn">Login</button>
        </form>
        <a href="register.php" class="link">Don't have an account? Register</a>
        <a href="../index.php" class="link">Back to Home</a>
    </div>
</body>
</html>
