<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}
require_once("/etc/iptv-proxy/config.php");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Package Editor</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        .category { margin: 10px 0; padding: 10px; background: #f0f0f0; }
        .channels { margin-left: 30px; display: none; }
        button { padding: 10px; margin: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Package Editor</h1>
    <p>Loading playlist... (161MB file may take 30-60 seconds)</p>
    <div id="content"></div>
    <a href="dashboard.php">Back to Dashboard</a>
</div>
<script>
fetch('/final.m3u')
    .then(res => res.text())
    .then(data => {
        let categories = {};
        let lines = data.split('\n');
        let currentCat = 'Uncategorized';
        
        for (let line of lines) {
            if (line.includes('#EXTINF') && line.includes('group-title=')) {
                let match = line.match(/group-title="([^"]+)"/);
                if (match) currentCat = match[1];
                if (!categories[currentCat]) categories[currentCat] = [];
            } else if (line.startsWith('http')) {
                categories[currentCat].push(line);
            }
        }
        
        let html = '<div><button onclick="checkAll(true)">Select All</button> <button onclick="checkAll(false)">Deselect All</button></div>';
        for (let [cat, urls] of Object.entries(categories)) {
            html += `<div class="category"><label><input type="checkbox" class="cat-checkbox" data-cat="${cat}"> <strong>${cat}</strong> (${urls.length})</label>`;
            html += `<div class="channels" id="channels-${cat.replace(/[^a-z0-9]/gi, '_')}">`;
            urls.forEach(url => {
                html += `<div><input type="checkbox" class="channel" data-url="${url}"> ${url.split('/').pop()}</div>`;
            });
            html += `</div></div>`;
        }
        document.getElementById('content').innerHTML = html;
    });
</script>
</body>
</html>
