<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once("/etc/iptv-proxy/config.php");
session_start();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = str_replace('/api/', '', parse_url($request_uri, PHP_URL_PATH));
$path = trim($path, '/');

// Auth helper
function requireAuth() {
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Database helper
function getDB() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed']));
    }
    return $conn;
}

// Routes
switch ($path) {
    case 'auth':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            global $admin_user, $admin_pass;
            if ($data['username'] === $admin_user && $data['password'] === $admin_pass) {
                $_SESSION['admin'] = true;
                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        } elseif ($method === 'GET') {
            echo json_encode(['authenticated' => isset($_SESSION['admin']) && $_SESSION['admin'] === true]);
        } elseif ($method === 'DELETE') {
            session_destroy();
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'categories':
        requireAuth();
        $m3u_file = '/var/www/html/final.m3u';
        if (!file_exists($m3u_file)) {
            echo json_encode(['success' => true, 'categories' => []]);
            break;
        }
        exec("grep -oP 'group-title=\"\\K[^\"]+' $m3u_file | sort -u 2>/dev/null", $categories);
        if (empty($categories)) {
            // Fallback method
            $categories = [];
            $handle = fopen($m3u_file, 'r');
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/group-title="([^"]+)"/', $line, $matches)) {
                    $categories[$matches[1]] = true;
                }
            }
            fclose($handle);
            $categories = array_keys($categories);
        }
        echo json_encode(['success' => true, 'categories' => $categories]);
        break;
        
    case 'channels':
        requireAuth();
        $category = $_GET['category'] ?? '';
        $m3u_file = '/var/www/html/final.m3u';
        $channels = [];
        $current = '';
        if (file_exists($m3u_file)) {
            $handle = fopen($m3u_file, 'r');
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (strpos($line, '#EXTINF') === 0 && preg_match('/group-title="([^"]+)"/', $line, $matches)) {
                    $current = $matches[1];
                } elseif (strpos($line, 'http://') === 0 && $current === $category) {
                    $channels[] = ['url' => $line, 'name' => basename($line)];
                }
            }
            fclose($handle);
        }
        echo json_encode(['success' => true, 'channels' => $channels]);
        break;
        
    case 'users':
        requireAuth();
        $conn = getDB();
        
        if ($method === 'GET') {
            $result = $conn->query("SELECT id, username, plain_password as password, expiry_date, created_at FROM users ORDER BY created_at DESC");
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            echo json_encode(['success' => true, 'users' => $users]);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'];
            $plain_password = $data['password'];
            $hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$data['expiry_days']} days"));
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, plain_password, expiry_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed, $plain_password, $expiry_date);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();
                
                if (!empty($data['channels'])) {
                    $stmt = $conn->prepare("INSERT INTO user_content (user_id, content_url) VALUES (?, ?)");
                    foreach ($data['channels'] as $url) {
                        $stmt->bind_param("is", $user_id, $url);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
                echo json_encode(['success' => true, 'user_id' => $user_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
            }
        }
        $conn->close();
        break;
        
    case 'user':
        requireAuth();
        $conn = getDB();
        $user_id = $_GET['id'];
        
        if ($method === 'DELETE') {
            $conn->query("DELETE FROM user_content WHERE user_id = $user_id");
            $conn->query("DELETE FROM users WHERE id = $user_id");
            echo json_encode(['success' => true]);
        } elseif ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            $conn->query("DELETE FROM user_content WHERE user_id = $user_id");
            if (!empty($data['channels'])) {
                $stmt = $conn->prepare("INSERT INTO user_content (user_id, content_url) VALUES (?, ?)");
                foreach ($data['channels'] as $url) {
                    $stmt->bind_param("is", $user_id, $url);
                    $stmt->execute();
                }
                $stmt->close();
            }
            echo json_encode(['success' => true]);
        }
        $conn->close();
        break;
        
    case 'user-channels':
        requireAuth();
        $user_id = $_GET['user_id'];
        $conn = getDB();
        $stmt = $conn->prepare("SELECT content_url FROM user_content WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $urls = [];
        while ($row = $result->fetch_assoc()) {
            $urls[] = $row['content_url'];
        }
        echo json_encode(['success' => true, 'channels' => $urls]);
        $conn->close();
        break;
        
    case 'user-password':
        requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        $conn = getDB();
        $new_password = $data['password'];
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $hashed, $new_password, $data['user_id']);
        $stmt->execute();
        echo json_encode(['success' => true]);
        $conn->close();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found: ' . $path]);
}
?>
