<?php
// Disable any active output buffers and increase limits
while (ob_get_level()) {
    ob_end_clean();
}
set_time_limit(0); // Unlimited execution time

$request_uri = $_SERVER['REQUEST_URI'];

// New pattern: /username/password/filename
if (preg_match('/\/([^\/]+)\/([^\/]+)\/(.+)/', $request_uri, $matches)) {
    $username = $matches[1];
    $password = $matches[2];
    $file = $matches[3];
    
    // Verify credentials
    require_once("/etc/iptv-proxy/config.php");
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) die("Database error");
    
    $stmt = $conn->prepare("SELECT id, password, plain_password, expiry_date, is_active FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        die("Unauthorized");
    }
    $user = $result->fetch_assoc();
    
    $password_valid = false;
    if (password_verify($password, $user['password'])) $password_valid = true;
    elseif ($user['plain_password'] === $password) $password_valid = true;
    
    if (!$password_valid || strtotime($user['expiry_date']) < time() || !$user['is_active']) {
        http_response_code(401);
        die("Unauthorized");
    }
    $conn->close();
    
    // Determine type from file extension
    if (preg_match('/\.ts$/', $file)) {
        $type = 'live';
        $content_type = 'video/mp2t';
    } else {
        $type = 'movie';
        $content_type = 'video/mp4';
    }
    
    // Construct original URL
    $original_url = "http://datahub11.com:80/{$type}/DCme2Ya8Jx/downright5homework/{$file}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $original_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    
    header('Content-Type: ' . $content_type);
    
    $output = fopen('php://output', 'wb');
    curl_setopt($ch, CURLOPT_FILE, $output);
    curl_exec($ch);
    fclose($output);
    curl_close($ch);
    exit;
}

http_response_code(404);
echo "Not found";
?>
