<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$title = $title ?? 'Mars Haven Control';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="../assets/css/all.css">
    <script src="../assets/js/sound_system.js" defer></script>
</head>
<body>
    <nav class="navtop">
        <div class="navleft">
            <span class="brand">Mars Haven</span>
        </div>
    <div class="navright">
        <?php if (isset($_SESSION['username'])): ?>
        <span class="user-info"><?php echo htmlspecialchars($_SESSION['username']); ?>
    (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
    <?php endif; ?>
    <a href="../index.php?console=1">Console</a>

    <button type="button" id="sound_toggle" class="sound_toggle" aria-pressed="false">Sound: On</button>

    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="../logout.php" class="navout">Logout</a>
    <?php endif; ?>
    </div>
    </nav>

    <div class="content">
        