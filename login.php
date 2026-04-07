<?php
session_start();
include 'config/db.php';
include 'lib/db_tools.php';

$error = '';

if (isset($_POST['submit'])) {
$login_input = trim($_POST['login']);
$pass_input = $_POST['password'];

$sql = 'SELECT id, username, role, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1';
$user_row = db_fetch_one($conn, $sql, 'ss', [$login_input, $login_input]);

$is_valid_pass = false;
$needs_rehash = false;
if ($user_row) {
$stored_hash = $user_row['password_hash'];

if (password_verify($pass_input, $stored_hash)) {
    $is_valid_pass = true;
} elseif (hash('sha256', $pass_input) === $stored_hash) {
    // Legacy support :: older SHA-256 seeded users.
    $is_valid_pass = true;
    $needs_rehash = true;
}
}

if ($user_row && $is_valid_pass) {
if ($needs_rehash) {
    $new_hash = password_hash($pass_input, PASSWORD_DEFAULT);
    $rehash_stmt = db_run_stmt(
        $conn,
        'UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1',
        'si',
        [$new_hash, (int) $user_row['id']]
    );
    if ($rehash_stmt) {
        $rehash_stmt->close();
    }
}


session_regenerate_id(true);
$_SESSION['login_started_at']=time();
$_SESSION['last_activity_at']=time();

$_SESSION['user_id'] = $user_row['id'];
$_SESSION['username'] = $user_row['username'];
$_SESSION['role'] = $user_row['role'];
header('Location: dashboard/' . $user_row['role'] . '.php');
exit();
}


$error = 'Invalid login credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mars Haven - Login</title>
<link rel="stylesheet" href="assets/css/login.css">
<script src="assets/js/sound_system.js" defer></script>
</head>
<body>
<main class="login_shell">
    <?php if(isset($_GET['reason']) && $_GET['reason'] === 'session_expired'):   ?>  
        <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 10px; margin-bottom: 20px; text-align: center; border: 1px solid #ef4444; border-radius: 4px;"> 
        <div class="session_expired">
            <p>Your session has expired. Please log in again.</p>
        </div>
    <?php endif; ?>
    <section class="login_box">
        <h2>Mars Haven Control System</h2>
        <p class="login_sub">authenticated access required</p>

    <form method="post" action="" class="login_form">
        <label for="login">Username or email</label>
        <input id="login" type="text" placeholder="username or email" name="login" required>

        <label for="password">Password</label>
        <input id="password" type="password" placeholder="password" name="password" required>

        <input type="submit" name="submit" value="login">
        <a class="home_btn" href="index.php">Back to console</a>
    </form>
    <p class="instructions">Please refer to <a href="README.md" target="_blank">README.md</a> for instructions about the login process.(credentials are provided in readme.)</p>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
</section>
</main>
</body>
</html>



 