# Guide d'installation et de déploiement

Ce document explique comment installer et déployer l'application d'annuaire d'entreprises locales.

## Prérequis

- Serveur Linux (Ubuntu 20.04+ recommandé)
- PHP 8.0+ avec extensions (PDO, cURL, DOM, JSON, etc.)
- MariaDB 10.5+ ou MySQL 8.0+
- Nginx ou Apache
- Composer (pour les dépendances PHP)
- Git (pour le déploiement)

## Installation des dépendances

```bash
# Mettre à jour les paquets
sudo apt update
sudo apt upgrade -y

# Installer PHP et ses extensions
sudo apt install -y php php-cli php-fpm php-mysql php-curl php-xml php-json php-mbstring php-zip unzip

# Installer MariaDB
sudo apt install -y mariadb-server

# Installer Nginx
sudo apt install -y nginx

# Installer Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Installer Git
sudo apt install -y git
```

## Cloner le dépôt

```bash
# Aller dans le répertoire de déploiement
cd /var/www/html

# Cloner le dépôt (à remplacer par votre URL de dépôt)
sudo git clone https://votre-repo/annuaire-entreprises.git
cd annuaire-entreprises

# Définir les permissions appropriées
sudo chown -R www-data:www-data .
```

## Configuration de la base de données

```bash
# Sécuriser l'installation de MariaDB
sudo mysql_secure_installation

# Se connecter à MariaDB
sudo mysql -u root -p

# Créer la base de données et l'utilisateur
CREATE DATABASE annuaire_entreprises CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'annuaire_user'@'localhost' IDENTIFIED BY 'mot_de_passe_securise';
GRANT ALL PRIVILEGES ON annuaire_entreprises.* TO 'annuaire_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Importer le schéma de base de données
sudo mysql -u root -p annuaire_entreprises < database_schema.sql
```

## Configuration de l'application

```bash
# Copier le fichier de configuration d'exemple
cp config/database.php.example config/database.php

# Éditer le fichier de configuration
sudo nano config/database.php
```

Mettez à jour les informations de connexion à la base de données dans le fichier `config/database.php` :

```php
<?php
// Informations de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'annuaire_entreprises');
define('DB_USER', 'annuaire_user');
define('DB_PASSWORD', 'mot_de_passe_securise');
```

## Installation des dépendances PHP

```bash
# Installer les dépendances avec Composer
composer install
```

## Configuration du serveur web (Nginx)

Créez un fichier de configuration Nginx pour votre site :

```bash
sudo nano /etc/nginx/sites-available/annuaire-entreprises
```

Ajoutez la configuration suivante :

```nginx
server {
    listen 80;
    server_name votre-domaine.com www.votre-domaine.com;
    root /var/www/html/annuaire-entreprises;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/api(/.*)?$ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Activez le site et redémarrez Nginx :

```bash
sudo ln -s /etc/nginx/sites-available/annuaire-entreprises /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo systemctl restart nginx
```

## Configuration de l'importation automatique

Exécutez le script de configuration du crontab :

```bash
sudo bash crontab-setup.sh
```

## Test de l'application

Accédez à votre site web via votre navigateur :

```
http://votre-domaine.com
```

## Dépannage

### Vérification des logs

```bash
# Logs Nginx
sudo tail -f /var/log/nginx/error.log

# Logs PHP
sudo tail -f /var/log/php8.0-fpm.log

# Logs d'importation
tail -f /var/www/html/annuaire-entreprises/logs/import.log
```

### Redémarrage des services

```bash
# Redémarrer PHP-FPM
sudo systemctl restart php8.0-fpm

# Redémarrer Nginx
sudo systemctl restart nginx

# Redémarrer MariaDB
sudo systemctl restart mariadb
```

## Mise à jour de l'application

Pour mettre à jour l'application avec les dernières modifications :

```bash
cd /var/www/html/annuaire-entreprises
sudo git pull
sudo chown -R www-data:www-data .
```

## Sécurité

- Assurez-vous de définir des mots de passe forts pour la base de données
- Configurez un pare-feu avec `ufw` ou `iptables`
- Ajoutez HTTPS avec Let's Encrypt
- Restreignez les accès SSH
- Mettez régulièrement à jour votre système et vos dépendances

