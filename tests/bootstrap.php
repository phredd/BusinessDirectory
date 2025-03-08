<?php
/**
 * Bootstrap pour les tests PHPUnit
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement de test
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = '10.1.0.10';
$_ENV['DB_NAME'] = 'demo1';
$_ENV['DB_USER'] = 'demo1';
$_ENV['DB_PASSWORD'] = 'free4u$';

// Configurer une base de données de test
function setupTestDatabase() {
    try {
        // Connexion à MySQL sans sélectionner de base de données
        $pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Supprimer la base de données de test si elle existe
        $pdo->exec('DROP DATABASE IF EXISTS ' . $_ENV['DB_NAME']);
        
        // Créer une nouvelle base de données de test
        $pdo->exec('CREATE DATABASE ' . $_ENV['DB_NAME']);
        
        // Sélectionner la base de données de test
        $pdo->exec('USE ' . $_ENV['DB_NAME']);
        
        // Charger le schéma de base de données
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
            echo "Base de données de test initialisée avec succès.\n";
        } else {
            echo "ERREUR: Fichier de schéma introuvable: $schemaFile\n";
        }
    } catch (PDOException $e) {
        echo "ERREUR lors de la configuration de la base de données de test: " . $e->getMessage() . "\n";
    }
}

// Initialiser la base de données de test
setupTestDatabase();
