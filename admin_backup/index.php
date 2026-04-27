<?php
require_once("/etc/iptv-proxy/config.php");
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['username'] == $admin_user && $_POST['password'] == $admin_pass) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials!';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - IPTV Manager</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        input { padding: 10px; margin: 10px; width: 200px; }
        button { padding: 10px 20px; background: blue; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>IPTV Admin Login</h2>
    <?php if($error) echo "<p style='color:red'>$error</p>"; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
