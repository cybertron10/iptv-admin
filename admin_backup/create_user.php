<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
require_once("/etc/iptv-proxy/config.php");

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed");
}

$edit_mode = isset($_GET['user_id']) && is_numeric($_GET['user_id']);
$user_id = $edit_mode ? (int)$_GET['user_id'] : null;
$username = $edit_mode ? $_GET['username'] : '';

$existing_selected = [];
if ($edit_mode && $user_id) {
    $stmt = $conn->prepare("SELECT content_url FROM user_content WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existing_selected[$row['content_url']] = true;
    }
    $stmt->close();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_urls = isset($_POST['channels']) ? explode(',', $_POST['channels']) : [];
    $selected_urls = array_filter($selected_urls);
    
    if ($edit_mode && $user_id) {
        $conn->query("DELETE FROM user_content WHERE user_id = $user_id");
        if (!empty($selected_urls)) {
            $stmt = $conn->prepare("INSERT INTO user_content (user_id, content_url) VALUES (?, ?)");
            foreach ($selected_urls as $url) {
                $stmt->bind_param("is", $user_id, $url);
                $stmt->execute();
            }
            $stmt->close();
        }
        echo "<script>alert('User updated with " . count($selected_urls) . " channels'); window.location.href='dashboard.php';</script>";
        exit;
    } else {
        $new_username = trim($_POST['username']);
        $plain_password = $_POST['password'];
        $expiry_days = (int)$_POST['expiry_days'];
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        
        if (empty($new_username) || strlen($new_username) < 3) {
            $message = "Username must be at least 3 characters";
        } elseif (strlen($plain_password) < 4) {
            $message = "Password must be at least 4 characters";
        } elseif (empty($selected_urls)) {
            $message = "Please select at least one channel";
        } else {
            $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, plain_password, expiry_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $new_username, $hashed_password, $plain_password, $expiry_date);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO user_content (user_id, content_url) VALUES (?, ?)");
                foreach ($selected_urls as $url) {
                    $stmt->bind_param("is", $new_user_id, $url);
                    $stmt->execute();
                }
                $stmt->close();
                
                echo "<script>alert('User created with " . count($selected_urls) . " channels'); window.location.href='dashboard.php';</script>";
                exit;
            } else {
                $message = "Username already exists";
            }
        }
    }
}

// Extract categories using grep
$m3u_file = '/var/www/html/final.m3u';
exec("grep -oP 'group-title=\"\\K[^\"]+' $m3u_file | sort -u", $categories);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $edit_mode ? 'Edit User' : 'Create User' ?></title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f0f0f0; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .form-section { background: #e8e8e8; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-section input, .form-section button { padding: 8px; margin: 5px; }
        .category { margin: 5px 0; padding: 8px; background: #e8e8e8; border-radius: 5px; }
        .category-header { font-weight: bold; cursor: pointer; padding: 5px; background: #d0d0d0; border-radius: 3px; }
        .category-header:hover { background: #b0b0b0; }
        .channels { margin-left: 20px; margin-top: 5px; display: none; max-height: 300px; overflow-y: auto; font-size: 12px; padding: 5px; }
        .channel { margin: 2px 0; padding: 2px; }
        .channel input { margin-right: 8px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .stats { margin: 10px 0; padding: 10px; background: #d4edda; border-radius: 5px; font-weight: bold; }
        .search-box { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
        .message { padding: 10px; margin: 10px 0; background: #f8d7da; color: #721c24; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1><?= $edit_mode ? 'Edit User Package: ' . htmlspecialchars($username) : 'Create New User' ?></h1>
    
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="form-section">
        <form method="post" id="userForm">
            <?php if (!$edit_mode): ?>
                <input type="text" name="username" placeholder="Username (min 3 chars)" required>
                <input type="password" name="password" placeholder="Password (min 4 chars)" required>
                <input type="number" name="expiry_days" placeholder="Days valid" value="30" min="1" max="365" required>
            <?php else: ?>
                <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
            <?php endif; ?>
            <input type="hidden" name="channels" id="selectedChannels" value="">
            <button type="submit" name="create_user"><?= $edit_mode ? 'Update User' : 'Create User' ?></button>
            <a href="dashboard.php">Cancel</a>
        </form>
    </div>
    
    <input type="text" id="searchBox" class="search-box" placeholder="Search categories..." onkeyup="filterCategories()">
    <div>
        <button type="button" onclick="selectAllVisible()">Select All Visible Channels</button>
        <button type="button" onclick="deselectAllVisible()">Deselect All Visible Channels</button>
    </div>
    <div class="stats" id="stats">Selected Channels: 0</div>
    <div id="categories"></div>
</div>

<script>
// Pass categories from PHP to JavaScript
const categoriesList = <?php echo json_encode($categories); ?>;
console.log('Categories loaded:', categoriesList);

let selectedChannelUrls = new Set();

function displayCategories() {
    const container = document.getElementById('categories');
    container.innerHTML = '';
    const searchTerm = document.getElementById('searchBox').value.toLowerCase();
    
    let visibleCount = 0;
    
    for (const catName of categoriesList) {
        if (searchTerm && !catName.toLowerCase().includes(searchTerm)) {
            continue;
        }
        visibleCount++;
        
        const safeId = 'cat_' + btoa(catName).replace(/[^a-zA-Z0-9]/g, '_');
        
        const catDiv = document.createElement('div');
        catDiv.className = 'category';
        catDiv.innerHTML = `
            <div class="category-header" onclick="toggleCategory('${safeId}')">
                📁 ${escapeHtml(catName)}
            </div>
            <div class="channels" id="${safeId}">
                <div style="padding: 5px; color: #666;">Click to load channels...</div>
            </div>
        `;
        container.appendChild(catDiv);
    }
    
    if (visibleCount === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center;">No categories match your search.</div>';
    }
}

async function toggleCategory(catId) {
    const channelsDiv = document.getElementById(catId);
    if (!channelsDiv) return;
    
    if (channelsDiv.style.display === 'none') {
        channelsDiv.style.display = 'block';
        // Load channels if not already loaded
        if (channelsDiv.innerHTML.trim() === '' || channelsDiv.innerHTML.includes('Click to load')) {
            channelsDiv.innerHTML = '<div style="padding: 5px;">Loading channels...</div>';
            await loadChannelsForCategory(catId);
        }
    } else {
        channelsDiv.style.display = 'none';
    }
}

async function loadChannelsForCategory(catId) {
    // Extract category name from the parent div
    const catDiv = document.getElementById(catId).parentElement;
    const headerDiv = catDiv.querySelector('.category-header');
    let catName = headerDiv.innerText.replace('📁', '').trim();
    
    try {
        const response = await fetch(`api.php?action=get_channels&categories=${encodeURIComponent(catName)}`);
        const data = await response.json();
        
        if (data.urls && data.urls.length > 0) {
            let html = '';
            data.urls.forEach(url => {
                const channelName = url.split('/').pop();
                const isChecked = selectedChannelUrls.has(url);
                html += `<div class="channel">
                    <input type="checkbox" class="channel-checkbox" data-url="${escapeHtml(url)}" onchange="updateSelectedCount()" ${isChecked ? 'checked' : ''}>
                    <span>${escapeHtml(channelName)}</span>
                </div>`;
            });
            document.getElementById(catId).innerHTML = html;
        } else {
            document.getElementById(catId).innerHTML = '<div style="padding: 5px; color: #999;">No channels in this category</div>';
        }
    } catch (error) {
        console.error('Error loading channels:', error);
        document.getElementById(catId).innerHTML = '<div style="padding: 5px; color: red;">Error loading channels</div>';
    }
}

function updateSelectedCount() {
    selectedChannelUrls.clear();
    document.querySelectorAll('.channel-checkbox:checked').forEach(cb => {
        selectedChannelUrls.add(cb.getAttribute('data-url'));
    });
    document.getElementById('selectedChannels').value = Array.from(selectedChannelUrls).join(',');
    document.getElementById('stats').innerHTML = `Selected Channels: ${selectedChannelUrls.size}`;
}

function selectAllVisible() {
    document.querySelectorAll('.channel-checkbox:visible').forEach(cb => {
        cb.checked = true;
        selectedChannelUrls.add(cb.getAttribute('data-url'));
    });
    updateSelectedCount();
}

function deselectAllVisible() {
    document.querySelectorAll('.channel-checkbox:visible').forEach(cb => {
        cb.checked = false;
        selectedChannelUrls.delete(cb.getAttribute('data-url'));
    });
    updateSelectedCount();
}

function filterCategories() {
    displayCategories();
}

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Initialize the page
displayCategories();
</script>
</body>
</html>
