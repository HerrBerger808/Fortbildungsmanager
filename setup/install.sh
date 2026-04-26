#!/bin/bash
# Fortbildungsmanager – Schnell-Installation auf Debian 13 (Subdomain-Betrieb)
# Ausführen als root: bash install.sh
#
# Voraussetzung: DNS-Eintrag für fobi.meinedomain.de zeigt bereits auf diesen Server.

set -e

echo "=== Fortbildungsmanager Installation (Subdomain) ==="
echo

# ── Pakete installieren ───────────────────────────────────────────────
apt-get update
apt-get install -y apache2 mariadb-server php php-mysql php-mbstring php-json \
                   php-curl libapache2-mod-php certbot python3-certbot-apache

# Apache-Module aktivieren
a2enmod rewrite headers ssl

# MariaDB starten
systemctl enable mariadb
systemctl start mariadb

# ── Datenbank anlegen ─────────────────────────────────────────────────
echo
echo "--- Datenbankzugangsdaten ---"
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

# ── Subdomain / URL abfragen ──────────────────────────────────────────
echo
echo "--- Subdomain-Konfiguration ---"
read -rp "Subdomain (z.B. fobi.meinedomain.de): " SERVER_NAME
SERVER_NAME=${SERVER_NAME:-fobi.meinedomain.de}

# DEST = DocumentRoot direkt auf den App-Ordner (kein Unterordner!)
DEST="/var/www/fobi"
read -rp "Zielverzeichnis [${DEST}]: " DEST_INPUT
DEST=${DEST_INPUT:-$DEST}

APP_URL="https://${SERVER_NAME}"
APP_SEC=$(openssl rand -hex 24)

# ── Anwendung deployen ────────────────────────────────────────────────
mkdir -p "$DEST"
# Kopiere alle Dateien; public/ wird der DocumentRoot, alles andere bleibt darüber.
rsync -a --exclude='setup/' "$(dirname "$0")/../" "$DEST/"
chown -R www-data:www-data "$DEST"
chmod -R 755 "$DEST"
chmod -R 775 "$DEST/config"   # config/ muss für den Installer schreibbar sein

# ── Local config schreiben ────────────────────────────────────────────
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

# ── Apache VirtualHost schreiben ──────────────────────────────────────
VHOST="/etc/apache2/sites-available/fobi.conf"

cat > "$VHOST" <<APACHE
# HTTP → HTTPS Weiterleitung
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>
APACHE

# Einfachen HTTP-VirtualHost aktivieren damit certbot arbeiten kann
a2ensite fobi
systemctl reload apache2

# ── SSL-Zertifikat (Let's Encrypt) ────────────────────────────────────
echo
read -rp "SSL-Zertifikat jetzt per Let's Encrypt einrichten? (j/N): " DO_SSL
if [[ "$DO_SSL" =~ ^[jJ]$ ]]; then
    certbot --apache -d "$SERVER_NAME" --non-interactive --agree-tos \
            --redirect --email "admin@${SERVER_NAME#*.}"
    echo "SSL eingerichtet. Certbot hat den VirtualHost automatisch angepasst."
else
    # Manuellen HTTPS-Block anhängen (Zertifikat später einrichten)
    cat >> "$VHOST" <<APACHE

# HTTPS – Zertifikat noch einrichten (certbot --apache -d ${SERVER_NAME})
<VirtualHost *:443>
    ServerName ${SERVER_NAME}
    DocumentRoot ${DEST}/public
    SSLEngine on
    # SSLCertificateFile    /etc/letsencrypt/live/${SERVER_NAME}/fullchain.pem
    # SSLCertificateKeyFile /etc/letsencrypt/live/${SERVER_NAME}/privkey.pem
    <Directory ${DEST}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # config/, src/, sql/ etc. liegen ausserhalb public/ – zur Sicherheit:
    <Directory ${DEST}/config>
        Require all denied
    </Directory>
    <Directory ${DEST}/src>
        Require all denied
    </Directory>
    <Directory ${DEST}/sql>
        Require all denied
    </Directory>
    <Directory ${DEST}/setup>
        Require all denied
    </Directory>
    ErrorLog  \${APACHE_LOG_DIR}/fobi_error.log
    CustomLog \${APACHE_LOG_DIR}/fobi_access.log combined
</VirtualHost>
APACHE
    APP_URL="http://${SERVER_NAME}"
    # Fallback: local.php ohne https
    sed -i "s|https://|http://|" "$DEST/config/local.php"
    echo "HTTPS-Block wurde vorbereitet. Zertifikat später einrichten mit:"
    echo "  certbot --apache -d ${SERVER_NAME}"
fi

systemctl reload apache2

# ── Cron-Job einrichten (Bulk-Approval-Reminder) ──────────────────────
CRON_CMD="*/15 * * * * www-data /usr/bin/php ${DEST}/setup/cron_tasks.php >> /var/log/fobi_cron.log 2>&1"
echo "$CRON_CMD" > /etc/cron.d/fortbildungsmanager
chmod 644 /etc/cron.d/fortbildungsmanager
echo "Cron-Job eingerichtet."

# ── Fertig ────────────────────────────────────────────────────────────
echo
echo "=== Installation abgeschlossen ==="
echo
echo "Bitte jetzt den Installationsassistenten aufrufen:"
echo "  ${APP_URL}/install"
echo
echo "Danach den Installer und das Setup-Verzeichnis entfernen:"
echo "  rm -f  ${DEST}/public/install.php"
echo "  rm -rf ${DEST}/setup"
