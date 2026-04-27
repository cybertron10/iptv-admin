# IPTV Proxy Server

Complete IPTV management system.

## Quick Install
bash <(curl -s https://raw.githubusercontent.com/cybertron10/iptv-admin/main/iptv-proxy-install.sh)

## Admin Panel
http://YOUR_IP/admin/
Default: admin / admin123

## License
MIT

## Complete Installation on New Server

### Step 1: Update system and install dependencies
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php mariadb-server curl wget git ufw
```

### Step 2: Clone repository
```bash
cd /var/www/html
sudo git clone https://github.com/cybertron10/iptv-admin.git .
```

### Step 3: Setup database
\`\`\`bash
sudo mysql -e "CREATE DATABASE iptv_users;"
sudo mysql -e "CREATE TABLE iptv_users.users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), plain_password VARCHAR(255), expiry_date DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, is_active BOOLEAN DEFAULT TRUE);"
sudo mysql -e "CREATE TABLE iptv_users.user_content (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, content_url TEXT);"
\`\`\`

### Step 4: Configure Apache

\`\`\`bash
sudo a2enmod rewrite php8.1 headers
sudo systemctl restart apache2
\`\`\`

### Step 5: Create config file

\`\`\`bash
sudo mkdir -p /etc/iptv-proxy
sudo cat > /etc/iptv-proxy/config.php << 'CONFIG'
<?php
\$db_host = 'localhost';
\$db_user = 'root';
\$db_pass = '';
\$db_name = 'iptv_users';
\$admin_user = 'admin';
\$admin_pass = 'admin123';
?>
CONFIG
\`\`\`

### Step 6: Download M3U file

\`\`\`bash
sudo wget -O /var/www/html/final.m3u "http://datahub11.com/get.php?username=DCme2Ya8Jx&password=downright5homework&type=m3u_plus&output=ts"
\`\`\`

### Step 7: Set permissions

\`\`\`bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo systemctl restart apache2
\`\`\`

## Using the One-Click Install Script

For automatic installation, run this command:

\`\`\`bash
wget -O install.sh https://raw.githubusercontent.com/cybertron10/iptv-admin/main/iptv-proxy-install.sh
chmod +x install.sh
sudo ./install.sh
\`\`\`

## Admin Access

After installation, access the admin panel at:

\`\`\`
http://YOUR_SERVER_IP/admin/
\`\`\`

Default login:
- Username: \`admin\`
- Password: \`admin123\` (change immediately)

## User Playlist URL

\`\`\`
http://YOUR_SERVER_IP/get.php?username=USER&password=PASS&type=m3u_plus&output=ts
\`\`\`

## Troubleshooting

Check Apache logs:
\`\`\`bash
sudo tail -f /var/log/apache2/error.log
\`\`\`

Test stream:
\`\`\`bash
curl -I http://localhost/live/966.ts
\`\`\`

## Security Notes

1. Change default passwords immediately
2. Use Cloudflare for HTTPS
3. Set up firewall: \`sudo ufw allow 22,80,443/tcp\`
4. Regularly update the system

## License

MIT
