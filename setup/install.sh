#!/bin/bash
# Fortbildungsmanager – Schnell-Installation auf Debian 13
# Ausführen als root: bash install.sh

set -e

echo "=== Fortbildungsmanager Installation ==="
echo

# Pakete installieren
apt-get update
apt-get install -y apache2 mariadb-server php php-mysql php-mbstring php-json \
                   php-curl libapache2-mod-php

# Apache-Module aktivieren
a2enmod rewrite headers

# MariaDB starten
systemctl enable mariadb
systemctl start mariadb

# Datenbank & Benutzer anlegen
echo "Datenbankname und Zugangsdaten festlegen:"
read -rp "Datenbankname [fortbildungsmanager]: " DB_NAME
DB_NAME=${DB_NAME:-fortbildungsmanager}
read -rp "Datenbankbenutzer [fbm_user]: " DB_USER
DB_USER=${DB_USER:-fbm_user}
read -rsp "Datenbankpasswort: " DB_PASS
echo

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Datenbank und Benutzer angelegt."

# Anwendung deployen
DEST="/var/www/fortbildungsmanager"
mkdir -p "$DEST"
cp -r ./* "$DEST/"
chown -R www-data:www-data "$DEST"
chmod -R 755 "$DEST"
chmod -R 775 "$DEST/config"

# Local config schreiben
read -rp "Basis-URL (z.B. https://meine-schule.de/fortbildungsmanager): " APP_URL
APP_URL=${APP_URL:-http://localhost/fortbildungsmanager}
APP_SEC=$(openssl rand -hex 24)

cat > "$DEST/config/local.php" <<PHP
<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');
define('APP_URL', '${APP_URL}');
define('APP_SECRET', '${APP_SEC}');
PHP

chown www-data:www-data "$DEST/config/local.php"
chmod 640 "$DEST/config/local.php"

# Apache VirtualHost
read -rp "Hostname (z.B. fortbildungsmanager.schule.de): " SERVER_NAME
SERVER_NAME=${SERVER_NAME:-localhost}

cat > /etc/apache2/sites-available/fortbildungsmanager.conf <<APACHE
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    DocumentRoot ${DEST}
    <Directory ${DEST}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Directory ${DEST}/config>
        Require all denied
    </Directory>
    <Directory ${DEST}/src>
        Require all denied
    </Directory>
    <Directory ${DEST}/sql>
        Require all denied
    </Directory>
    ErrorLog  \${APACHE_LOG_DIR}/fortbildungsmanager_error.log
    CustomLog \${APACHE_LOG_DIR}/fortbildungsmanager_access.log combined
</VirtualHost>
APACHE

a2ensite fortbildungsmanager
systemctl reload apache2

echo
echo "=== Installation abgeschlossen ==="
echo "Bitte rufen Sie jetzt den Installationsassistenten auf:"
echo "  ${APP_URL}/install"
