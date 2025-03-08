# Architecture du site web d'annuaire d'entreprises locales

## Vue d'ensemble
Le système sera composé des éléments suivants :
1. Base de données MariaDB pour stocker les informations des entreprises
2. Script PHP d'importation programmé via crontab pour récupérer les données
3. API backend en PHP pour interagir avec la base de données
4. Interface frontend avec carte interactive
5. Système d'authentification et de gestion des utilisateurs

## Schéma de la base de données
- Tables principales : entreprises, contacts, adresses, activites, sites_web
- Tables de relations et métadonnées pour les sources d'importation

## Flux de données
1. Récupération périodique des données via des scripts d'importation (scraping et API)
2. Normalisation et dédoublonnage des informations
3. Stockage dans la base de données
4. Exposition via une API REST
5. Affichage sur l'interface frontend avec carte interactive

## Technologies utilisées
- Backend : PHP 8.2, Laravel/Symfony
- Base de données : MariaDB 10.5+
- Frontend : HTML5, CSS3, JavaScript, Vue.js/React
- Cartographie : Leaflet.js avec OpenStreetMap
- Serveur : Nginx, PHP-FPM
- Système de tâches : Crontab ou Laravel Scheduler

