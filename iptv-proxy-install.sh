#!/bin/bash
# IPTV Proxy Server - Installation Script
# Run as root on Ubuntu 22.04

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}IPTV Proxy Server Installation${NC}"

# Install dependencies
apt update && apt install -y apache2 php libapache2-mod-php php-cli php-curl php-mysql mariadb-server curl wget git ufw

# Enable Apache modules
a2enmod rewrite php8.1 headers
systemctl restart apache2

# Setup firewall
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && echo "y" | ufw enable

# Clone repository
cd /var/www/html
git clone https://github.com/cybertron10/iptv-admin.git .
chown -R www-data:www-data /var/www/html

# Setup database
mysql -e "CREATE DATABASE IF NOT EXISTS iptv_users;"
mysql -e "CREATE TABLE iptv_users.users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), plain_password VARCHAR(255), expiry_date DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, is_active BOOLEAN DEFAULT TRUE);"
mysql -e "CREATE TABLE iptv_users.user_content (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, content_url TEXT);"

# Create config
mkdir -p /etc/iptv-proxy
cat > /etc/iptv-proxy/config.php << 'CONFIG'
<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'iptv_users';
$admin_user = 'admin';
$admin_pass = 'admin123';
?>
CONFIG

# Download M3U
wget -q -O /var/www/html/final.m3u "http://datahub11.com/get.php?username=DCme2Ya8Jx&password=downright5homework&type=m3u_plus&output=ts"

systemctl restart apache2

echo -e "${GREEN}Done!${NC}"
echo "Admin: http://$(curl -s ifconfig.me)/admin/"
echo "Login: admin / admin123"
