<?php
require_once("/etc/iptv-proxy/config.php");

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

if (empty($username) || empty($password)) {
    die("Username and password required");
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Database error");
}

$stmt = $conn->prepare("SELECT id, password, plain_password, expiry_date, is_active FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid username");
}

$user = $result->fetch_assoc();

// Verify password
$password_valid = false;
if (password_verify($password, $user['password'])) {
    $password_valid = true;
} elseif ($user['plain_password'] === $password) {
    $password_valid = true;
}

if (!$password_valid) {
    die("Invalid password");
}

if (strtotime($user['expiry_date']) < time()) {
    die("Account expired");
}

if (!$user['is_active']) {
    die("Account inactive");
}

$content_stmt = $conn->prepare("SELECT content_url FROM user_content WHERE user_id = ?");
$content_stmt->bind_param("i", $user['id']);
$content_stmt->execute();
$content_result = $content_stmt->get_result();

$urls = [];
while ($row = $content_result->fetch_assoc()) {
    $urls[] = $row['content_url'];
}
$content_stmt->close();
$conn->close();

if (empty($urls)) {
    die("No content assigned");
}

header('Content-Type: audio/mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u"');

$full_m3u = '/var/www/html/final.m3u';
if (!file_exists($full_m3u)) {
    die("Playlist file not found");
}

echo "#EXTM3U\n";
$handle = fopen($full_m3u, 'r');
$current_extinf = '';

while (($line = fgets($handle)) !== false) {
    $line = rtrim($line);
    if (strpos($line, '#EXTINF') === 0) {
        $current_extinf = $line;
    } elseif (strpos($line, 'http://') === 0 && $current_extinf) {
        if (in_array($line, $urls)) {
            echo $current_extinf . "\n";
            echo $line . "\n";
        }
        $current_extinf = '';
    }
}
fclose($handle);
?>
