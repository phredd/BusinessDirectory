<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseConnectionTest extends TestCase
{
    private $pdo;
    
    protected function setUp(): void
    {
        // Charger la configuration de la base de données
        require_once __DIR__ . '/../../config/database.php';
        
        // Utiliser la fonction getDbConnection si elle existe
        if (function_exists('getDbConnection')) {
            $this->pdo = getDbConnection();
        } else {
            // Créer une nouvelle connexion
            $this->pdo = new PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
    }
    
    public function testDatabaseConnection()
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
        
        // Vérifier que la connexion est valide en exécutant une requête simple
        $stmt = $this->pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        $this->assertEquals(1, $result['test']);
    }
    
    public function testDatabaseTables()
    {
        // Vérifier que les tables nécessaires existent
        $requiredTables = [
            'entreprises',
            'adresses',
            'contacts',
            'dirigeants',
            'activites',
            'entreprises_activites',
            'sites_web',
            'import_logs'
        ];
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($requiredTables as $table) {
            $this->assertContains($table, $existingTables, "La table '$table' n'existe pas dans la base de données");
        }
    }
}
