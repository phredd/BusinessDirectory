<?php
/**
 * Configuration de la base de données
 */

// Informations de connexion à la base de données
define('DB_HOST', '10.1.0.10');
define('DB_NAME', 'demo1');
define('DB_USER', 'demo1');
define('DB_PASSWORD', 'free4u$');

// Options PDO
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);

/**
 * Fonction pour obtenir une connexion PDO à la base de données
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, DB_OPTIONS);
        } catch (PDOException $e) {
            // Journaliser l'erreur et renvoyer une exception plus générique
            error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
            throw new Exception('Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.');
        }
    }
    
    return $pdo;
}

