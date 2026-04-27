<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
require_once("/etc/iptv-proxy/config.php");

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM user_content WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: dashboard.php');
    exit;
}

// Handle password change
if (isset($_POST['change_password']) && is_numeric($_POST['user_id'])) {
    $id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    if (strlen($new_password) >= 4) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $hashed, $new_password, $id);
        $stmt->execute();
        $stmt->close();
        $password_msg = "Password changed successfully!";
    } else {
        $password_msg = "Password must be at least 4 characters";
    }
}

$users = $conn->query("SELECT id, username, plain_password, expiry_date FROM users ORDER BY created_at DESC");
$base_url = 'http://178.238.227.140';
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
        .add-form { background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        a { margin: 0 5px; }
        .delete { color: red; }
        .edit { color: blue; }
        .change-pw { color: orange; cursor: pointer; text-decoration: underline; }
        .pw-form { display: none; margin-top: 5px; }
        .pw-form input { padding: 3px; margin-right: 5px; }
        .message { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .user-url { font-size: 11px; word-break: break-all; background: #f0f0f0; padding: 5px; border-radius: 3px; margin-top: 5px; }
        .copy-btn { background: #007bff; color: white; border: none; padding: 2px 8px; border-radius: 3px; cursor: pointer; margin-left: 5px; font-size: 11px; }
    </style>
    <script>
        function showPwForm(userId) {
            var form = document.getElementById('pw-form-' + userId);
            if (form.style.display === 'none') {
                form.style.display = 'inline-block';
            } else {
                form.style.display = 'none';
            }
        }
        
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(function() {
                var originalText = btn.innerHTML;
                btn.innerHTML = 'Copied!';
                setTimeout(function() {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }
    </script>
</head>
<body>
    <h2>IPTV User Management</h2>
    
    <?php if (isset($password_msg)): ?>
        <div class="message"><?= htmlspecialchars($password_msg) ?></div>
    <?php endif; ?>
    
    <p><a href="create_user.php">+ Create New User</a></p>
    
    <h3>Existing Users</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Password</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th>Actions</th>
            <th>User URL (for IPTV)</th>
        </tr>
        <?php while($row = $users->fetch_assoc()): 
            $user_url = $base_url . "/get.php?username=" . urlencode($row['username']) . "&password=" . urlencode($row['plain_password']) . "&type=m3u_plus&output=ts";
        ?>
        <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= htmlspecialchars($row['plain_password']) ?></td>
            <td><?= htmlspecialchars($row['expiry_date']) ?></td>
            <td><?= (strtotime($row['expiry_date']) > time()) ? 'Active' : 'Expired' ?></td>
            <td>
                <a href="create_user.php?user_id=<?= urlencode($row['id']) ?>&username=<?= urlencode($row['username']) ?>" class="edit">Edit Package</a>
                <a href="dashboard.php?delete=<?= urlencode($row['id']) ?>" class="delete" onclick="return confirm('Delete user <?= htmlspecialchars($row['username']) ?>?')">Delete User</a>
                <span class="change-pw" onclick="showPwForm(<?= $row['id'] ?>)">Change Password</span>
                <div id="pw-form-<?= $row['id'] ?>" class="pw-form">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                        <input type="text" name="new_password" placeholder="New password" required>
                        <button type="submit" name="change_password">Save</button>
                        <button type="button" onclick="document.getElementById('pw-form-<?= $row['id'] ?>').style.display='none'">Cancel</button>
                    </form>
                </div>
             </td>
            <td>
                <div class="user-url">
                    <code style="font-size: 11px; word-break: break-all;"><?= htmlspecialchars($user_url) ?></code>
                    <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($user_url) ?>', this)">Copy</button>
                </div>
             </td>
         </tr>
        <?php endwhile; ?>
     </table>
    <br>
    <a href="logout.php">Logout</a>
</body>
</html>
