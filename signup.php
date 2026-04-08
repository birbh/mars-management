<?php
session_start();
include 'config/db.php';
include 'lib/db_tools.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if (isset($_POST['submit'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowed_roles = ['admin', 'user', 'astronaut'];

    if ($csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif ($username === '' || $email === '' || $password === '' || $confirm_password === '' || $role === '') {
        $error = 'All fields are required.';
    } elseif (strlen($username) > 255 || strlen($email) > 255) {
        $error = 'Username or email is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = 'Invalid role selected.';
    } else {
        $check_sql = 'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1';
        $existing_user = db_fetch_one($conn, $check_sql, 'ss', [$username, $email]);

        if ($existing_user) {
            $error = 'Username or email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = 'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)';
            $stmt = db_run_stmt($conn, $insert_sql, 'ssss', [$username, $email, $password_hash, $role]);

            if ($stmt) {
                $stmt->close();
                header('Location: login.php?signup=success');
                exit();
            }

            $error = 'Signup failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mars Haven - Signup</title>
<link rel="stylesheet" href="assets/css/signup.css">
<script src="assets/js/sound_system.js" defer></script>
</head>
<body>
<main class="signup_shell">
    <section class="signup_box">
        <h2>Create Account</h2>
        <p class="signup_sub">Create your Mars Haven access and choose your role.</p>

        <?php if ($error !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="post" action="" class="signup_form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label for="username">Username</label>
            <input id="username" type="text" placeholder="username" name="username" maxlength="255" required>

            <label for="email">Email</label>
            <input id="email" type="email" placeholder="email" name="email" maxlength="255" required>

            <label for="password">Password</label>
            <input id="password" type="password" placeholder="password" name="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" type="password" name="confirm_password" required>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="">Select role</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
                <option value="astronaut">Astronaut</option>
            </select>

            <input type="submit" name="submit" value="Sign Up">
        </form>

        <p class="login_link">Already have an account? <a href="login.php">Log in here</a>.</p>
    </section>
</main>
</body>
</html>
