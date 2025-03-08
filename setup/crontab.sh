#!/bin/bash

# Script pour configurer l'exécution automatique de l'importation des données
WWW_PATH="/var/www/phredd.fr/demo1"
BIN_PATH="/usr/bin"

# Permissions d'exécution pour le script d'importation
$BIN_PATH/chmod +x $WWW_PATH/import.php

# Définir le chemin absolu vers PHP et le script
PHP_BIN=$($BIN_PATH/which php)
IMPORT_SCRIPT=$WWW_PATH/import.php
LOG_FILE=$WWW_PATH/logs/import.log

# Créer un répertoire pour les logs s'il n'existe pas
mkdir -p /var/www/html/annuaire-entreprises/logs

# Vérifier que le fichier crontab existe
CRONTAB_FILE="/var/spool/cron/crontabs/www-data"
if [ ! -f "$CRONTAB_FILE" ]; then
    touch "$CRONTAB_FILE"
    chown www-data:crontab "$CRONTAB_FILE"
    chmod 600 "$CRONTAB_FILE"
fi

# Ajouter la tâche cron à l'utilisateur www-data
(crontab -u www-data -l 2>/dev/null || echo "") | grep -v "$IMPORT_SCRIPT" > /tmp/crontab.tmp
echo "# Importation des données d'entreprises tous les jours à 3h du matin" >> /tmp/crontab.tmp
echo "0 3 * * * $PHP_BIN $IMPORT_SCRIPT > $LOG_FILE 2>&1" >> /tmp/crontab.tmp
crontab -u www-data /tmp/crontab.tmp
rm /tmp/crontab.tmp

echo "Configuration crontab terminée. L'importation s'exécutera tous les jours à 3h du matin."
echo "Les logs seront disponibles dans $LOG_FILE"

# Ajouter une rotation des logs avec logrotate
if [ ! -f /etc/logrotate.d/annuaire-entreprises ]; then
    cat > /etc/logrotate.d/annuaire-entreprises << EOF
$LOG_FILE {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
    echo "Configuration de logrotate terminée."
fi

# Exécuter une première importation
echo "Lancement d'une première importation..."
sudo -u www-data $PHP_BIN $IMPORT_SCRIPT

echo "Terminé!"
