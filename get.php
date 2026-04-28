<?php
require_once("/etc/iptv-proxy/config.php");

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

if (empty($username) || empty($password)) {
    die("Username and password required");
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("Database error");

$stmt = $conn->prepare("
    SELECT u.id, u.password, u.plain_password, u.expiry_date, u.is_active, 
           p.filename as package_file
    FROM users u 
    LEFT JOIN packages p ON u.package_id = p.id 
    WHERE u.username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("Invalid username");
$user = $result->fetch_assoc();

$password_valid = false;
if (password_verify($password, $user['password'])) $password_valid = true;
elseif ($user['plain_password'] === $password) $password_valid = true;

if (!$password_valid) die("Invalid password");
if (strtotime($user['expiry_date']) < time()) die("Account expired");
if (!$user['is_active']) die("Account inactive");

if (empty($user['package_file'])) {
    die("No package assigned to this user");
}

$file_path = "/var/www/html/packages/" . $user['package_file'];
if (!file_exists($file_path)) {
    die("Package file not found");
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');

$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$handle = fopen($file_path, 'r');

while (($line = fgets($handle)) !== false) {
    if (strpos($line, '#EXTINF') === 0) {
        echo $line;
    } elseif (preg_match('/https?:\/\/[^\s]+/', $line, $matches)) {
        $new_url = str_replace(
            'https://datahub11.com:443/live/DCme2Ya8Jx/downright5homework/',
            $base_url . '/live/' . $username . '/' . $password . '/',
            $line
        );
        $new_url = str_replace(
            'https://datahub11.com:443/movie/DCme2Ya8Jx/downright5homework/',
            $base_url . '/movie/' . $username . '/' . $password . '/',
            $new_url
        );
        echo $new_url;
    } else {
        echo $line;
    }
}
fclose($handle);
?>
