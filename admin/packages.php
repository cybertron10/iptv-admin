<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
require_once("/etc/iptv-proxy/config.php");

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Create packages table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    file_size VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['package_file'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $file = $_FILES['package_file'];
    
    if ($file['error'] === 0 && pathinfo($file['name'], PATHINFO_EXTENSION) == 'm3u') {
        $filename = "package_" . time() . ".m3u";
        $filepath = "/var/www/html/packages/" . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $size = round(filesize($filepath) / 1024 / 1024, 2) . ' MB';
            $stmt = $conn->prepare("INSERT INTO packages (name, description, filename, file_size) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $description, $filename, $size);
            $stmt->execute();
            echo "<script>alert('Package uploaded successfully'); window.location.href='packages.php';</script>";
            exit;
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $result = $conn->query("SELECT filename FROM packages WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        $filepath = "/var/www/html/packages/" . $row['filename'];
        if (file_exists($filepath)) unlink($filepath);
    }
    $conn->query("DELETE FROM packages WHERE id = $id");
    header('Location: packages.php');
    exit;
}

// Handle download default M3U
if (isset($_GET['download_default'])) {
    $default_m3u = 'http://datahub11.com/get.php?username=DCme2Ya8Jx&password=downright5homework&type=m3u_plus&output=ts';
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Disposition: attachment; filename="default_playlist.m3u"');
    readfile($default_m3u);
    exit;
}

$packages = $conn->query("SELECT * FROM packages ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Package Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1, h2 { margin-bottom: 20px; color: #1a1a2e; }
        .card { background: #f8f9fc; padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, textarea { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; }
        input[type="file"] { padding: 8px; }
        button { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-right: 10px; }
        .btn-default { background: #28a745; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8f9fc; }
        .delete { color: #ef4444; text-decoration: none; }
        .back { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.html" class="back">← Back to Dashboard</a>
    <h1>📦 Package Manager</h1>
    
    <div class="card">
        <h2>Upload New Package</h2>
        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
                <label>Package Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>M3U File</label>
                <input type="file" name="package_file" accept=".m3u" required>
            </div>
            <button type="submit" id="uploadBtn">Upload Package</button>
    <div class="progress" style="display:none; margin-top:10px;"><div class="progress-bar" style="width:0%; height:20px; background:#28a745; border-radius:5px; text-align:center; line-height:20px; color:white; font-size:12px;">0%</div></div>
        </form>
    </div>
    
    <div class="card">
        <h2>Download Default M3U</h2>
        <p>Download the original M3U from datahub11 to edit and create custom packages.</p>
        <a href="?download_default=1" class="btn-default" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; margin-top: 10px;">⬇️ Download Default M3U</a>
    </div>
    
    <h2>Existing Packages</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Description</th><th>Size</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php while($row = $packages->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= $row['file_size'] ?></td>
            <td><?= $row['created_at'] ?></td>
            <td><a href="?delete=<?= $row['id'] ?>" class="delete" onclick="return confirm('Delete this package?')">Delete</a></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    var fileInput = document.querySelector('input[type="file"]');
    if (!fileInput.files.length) return;
    
    var progress = document.querySelector('.progress');
    var progressBar = document.querySelector('.progress-bar');
    progress.style.display = 'block';
    
    var formData = new FormData(this);
    var xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
    });
    
    xhr.onload = function() {
        if (xhr.responseText.includes('alert')) {
            eval(xhr.responseText.match(/alert\(['"]([^'"]+)['"]\)/)[0]);
            location.reload();
        }
    };
    
    xhr.open('POST', '', true);
    xhr.send(formData);
    e.preventDefault();
});
</script>
