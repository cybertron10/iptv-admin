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

// Force download
header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');

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
    } elseif (strpos($line, 'https://') === 0 && $current_extinf) {
        if (in_array($line, $urls)) {
            // Replace datahub11 URL with local proxy URL
            $new_url = str_replace(
                'https://datahub11.com:443/live/DCme2Ya8Jx/downright5homework/',
                'http://178.238.227.140:8080/live/',
                $line
            );
            $new_url = str_replace(
                'https://datahub11.com:443/movie/DCme2Ya8Jx/downright5homework/',
                'http://178.238.227.140:8080/movie/',
                $new_url
            );
            $new_url = str_replace(
                'https://datahub11.com:443/series/DCme2Ya8Jx/downright5homework/',
                'http://178.238.227.140:8080/series/',
                $new_url
            );
            echo $current_extinf . "\n";
            echo $new_url . "\n";
        }
        $current_extinf = '';
    }
}
fclose($handle);
?>
