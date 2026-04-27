<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'session' => $_SESSION]);
    exit;
}

// Get CPU usage
$cpu_load = sys_getloadavg();
$cpu_usage = round($cpu_load[0] * 100, 2);

// Get memory usage
$meminfo = file_get_contents('/proc/meminfo');
preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
$mem_total = $matches[1] ?? 0;
preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
$mem_available = $matches[1] ?? 0;
$mem_used = $mem_total - $mem_available;
$mem_usage_percent = $mem_total > 0 ? round(($mem_used / $mem_total) * 100, 2) : 0;

// Get disk usage
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
$disk_usage_percent = $disk_total > 0 ? round(($disk_used / $disk_total) * 100, 2) : 0;

// Format bytes function
function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

// Get bandwidth usage (from /proc/net/dev)
$interfaces = ['eth0', 'ens3', 'ens4', 'ens5', 'ens192'];
$rx_bytes = 0;
$tx_bytes = 0;
foreach ($interfaces as $iface) {
    $net_data = shell_exec("cat /proc/net/dev | grep '$iface:'");
    if ($net_data) {
        preg_match('/:\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $net_data, $matches);
        if (isset($matches[1])) $rx_bytes += (int)$matches[1];
        if (isset($matches[9])) $tx_bytes += (int)$matches[9];
    }
}

// Get uptime
$uptime_seconds = (int)shell_exec("cut -d. -f1 /proc/uptime");
$uptime_days = floor($uptime_seconds / 86400);
$uptime_hours = floor(($uptime_seconds % 86400) / 3600);
$uptime_minutes = floor(($uptime_seconds % 3600) / 60);

// Get active connections
$active_connections = (int)shell_exec("netstat -an | grep :80 | grep ESTABLISHED | wc -l");

// Get service status
$services = [
    'apache2' => trim(shell_exec("systemctl is-active apache2")) ?: 'inactive',
    'mysql' => trim(shell_exec("systemctl is-active mysql")) ?: 'inactive',
    'xuione' => trim(shell_exec("systemctl is-active xuione")) ?: 'inactive'
];

echo json_encode([
    'success' => true,
    'cpu' => [
        'usage_percent' => $cpu_usage,
        'load_1min' => round($cpu_load[0], 2),
        'load_5min' => round($cpu_load[1], 2),
        'load_15min' => round($cpu_load[2], 2)
    ],
    'memory' => [
        'total' => formatBytes($mem_total * 1024),
        'used' => formatBytes($mem_used * 1024),
        'available' => formatBytes($mem_available * 1024),
        'usage_percent' => $mem_usage_percent
    ],
    'disk' => [
        'total' => formatBytes($disk_total),
        'used' => formatBytes($disk_used),
        'free' => formatBytes($disk_free),
        'usage_percent' => $disk_usage_percent
    ],
    'network' => [
        'rx_bytes' => formatBytes($rx_bytes),
        'tx_bytes' => formatBytes($tx_bytes)
    ],
    'system' => [
        'uptime' => "{$uptime_days}d {$uptime_hours}h {$uptime_minutes}m",
        'active_connections' => $active_connections,
        'services' => $services
    ]
]);
?>
