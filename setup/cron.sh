#!/bin/bash
# Cron-Job: Sammelgenehmigungen benachrichtigen wenn Anmeldeschluss erreicht
# Eintragen mit: crontab -e (als www-data)
# */15 * * * * /var/www/fortbildungsmanager/setup/cron.sh >> /var/log/fbm_cron.log 2>&1

PHP=$(which php)
SCRIPT="/var/www/fortbildungsmanager/setup/cron_tasks.php"

if [ -f "$SCRIPT" ]; then
    $PHP "$SCRIPT"
fi
