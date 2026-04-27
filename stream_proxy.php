<?php
// Disable any active output buffers and increase limits
while (ob_get_level()) {
    ob_end_clean();
}
set_time_limit(0); // Unlimited execution time

$request_uri = $_SERVER['REQUEST_URI'];
if (preg_match('/\/(live|movie|series)\/(.+)/', $request_uri, $matches)) {
    $type = $matches[1];
    $file = $matches[2];
    // Use HTTP instead of HTTPS to avoid SSL issues
    $original_url = "http://datahub11.com:80/{$type}/DCme2Ya8Jx/downright5homework/{$file}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $original_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    
    // Set the header based on stream type
    if ($type == 'live') {
        header('Content-Type: video/mp2t');
    } else {
        header('Content-Type: video/mp4');
    }
    
    // Stream the data directly to the client
    $output = fopen('php://output', 'wb');
    curl_setopt($ch, CURLOPT_FILE, $output);
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        http_response_code(500);
        echo "cURL Error: " . curl_error($ch);
    }
    
    fclose($output);
    curl_close($ch);
    exit;
}
?>
