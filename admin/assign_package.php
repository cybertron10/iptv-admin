<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
require_once("/etc/iptv-proxy/config.php");

$user_id = (int)$_GET['id'];
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$user = $conn->query("SELECT username, package_id FROM users WHERE id = $user_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $package_id = (int)$_POST['package_id'];
    $conn->query("UPDATE users SET package_id = $package_id WHERE id = $user_id");
    echo "<script>alert('Package assigned to user'); window.location.href='index.html';</script>";
    exit;
}

$packages = $conn->query("SELECT id, name FROM packages ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Package</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 500px; margin: 100px auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        select, button { width: 100%; padding: 12px; margin: 10px 0; border: 2px solid #e2e8f0; border-radius: 8px; }
        button { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; cursor: pointer; }
        a { display: inline-block; margin-top: 10px; color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <h2>Assign Package to: <?php echo htmlspecialchars($user['username']); ?></h2>
    <form method="post">
        <select name="package_id" required>
            <option value="">-- Select Package --</option>
            <?php while($pkg = $packages->fetch_assoc()): ?>
            <option value="<?= $pkg['id'] ?>" <?= ($user['package_id'] == $pkg['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pkg['name']) ?>
            </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Assign Package</button>
        <a href="index.html">Cancel</a>
    </form>
</div>
</body>
</html>
