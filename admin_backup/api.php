<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}
require_once("/etc/iptv-proxy/config.php");

$action = $_GET['action'] ?? '';

if ($action === 'get_channels') {
    $category = $_GET['categories'] ?? '';
    $m3u_file = '/var/www/html/final.m3u';
    $urls = [];
    $current_category = '';
    
    $handle = fopen($m3u_file, 'r');
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (strpos($line, '#EXTINF') === 0 && preg_match('/group-title="([^"]+)"/', $line, $matches)) {
            $current_category = trim($matches[1]);
        } elseif (strpos($line, 'http://') === 0 && $current_category) {
            if ($current_category === $category) {
                $urls[] = $line;
            }
            $current_category = '';
        }
    }
    fclose($handle);
    
    header('Content-Type: application/json');
    echo json_encode(['urls' => $urls, 'category' => $category, 'count' => count($urls)]);
    exit;
}
?>
