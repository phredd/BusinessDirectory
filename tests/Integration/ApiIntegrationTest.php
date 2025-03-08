<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class ApiIntegrationTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        // Connexion à la base de données de test
        $this->db = new \PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
        
        // Vider les tables pour avoir un état propre
        $this->db->exec('TRUNCATE TABLE entreprises');
        $this->db->exec('TRUNCATE TABLE adresses');
        $this->db->exec('TRUNCATE TABLE contacts');
        $this->db->exec('TRUNCATE TABLE activites');
        $this->db->exec('TRUNCATE TABLE entreprises_activites');
        
        // Insérer des données de test
        $this->seedTestData();
    }
    
    /**
     * Insère des données de test dans la base de données
     */
    private function seedTestData()
    {
        // Activités
        $this->db->exec("INSERT INTO activites (id, libelle) VALUES 
            (1, 'Restaurant'),
            (2, 'Coiffeur'),
            (3, 'Boulangerie')
        ");
        
        // Entreprises
        $this->db->exec("INSERT INTO entreprises (id, nom, siret, source, source_id) VALUES 
            (1, 'Restaurant Le Test', '12345678901234', 'test', 'test1'),
            (2, 'Coiffeur Style', '23456789012345', 'test', 'test2'),
            (3, 'Boulangerie du Coin', '34567890123456', 'test', 'test3')
        ");
        
        // Adresses
        $this->db->exec("INSERT INTO adresses (entreprise_id, type, adresse, code_postal, ville, latitude, longitude) VALUES 
            (1, 'siege', '1 rue du Test', '75001', 'Paris', 48.8566, 2.3522),
            (2, 'siege', '2 rue du Style', '75002', 'Paris', 48.8656, 2.3514),
            (3, 'siege', '3 rue du Pain', '75003', 'Paris', 48.8632, 2.3598)
        ");
        
        // Contacts
        $this->db->exec("INSERT INTO contacts (entreprise_id, type, valeur) VALUES 
            (1, 'telephone', '0123456789'),
            (1, 'email', 'contact@restaurant-test.fr'),
            (2, 'telephone', '0123456788'),
            (3, 'telephone', '0123456787')
        ");
        
        // Associations entreprises-activités
        $this->db->exec("INSERT INTO entreprises_activites (entreprise_id, activite_id) VALUES 
            (1, 1),
            (2, 2),
            (3, 3)
        ");
    }
    
    /**
     * Test l'API directement en incluant le fichier PHP
     */
    private function callApi($uri, $method = 'GET', $params = [])
    {
        // Sauvegarder les variables globales
        $originalGet = $_GET;
        $originalServer = $_SERVER;
        
        // Configurer les variables pour le test
        $_GET = $params;
        $_SERVER['REQUEST_URI'] = '/api' . $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        
        // Démarrer la capture de sortie
        ob_start();
        
        // Inclure le fichier API
        $apiPath = __DIR__ . '/../../public/api/index.php';
        if (file_exists($apiPath)) {
            include $apiPath;
        } else {
            throw new \Exception("Le fichier API n'existe pas à l'emplacement: $apiPath");
        }
        
        // Récupérer la sortie
        $output = ob_get_clean();
        
        // Restaurer les variables globales
        $_GET = $originalGet;
        $_SERVER = $originalServer;
        
        // Analyser la sortie comme JSON
        return json_decode($output, true);
    }
    
    public function testGetActivites()
    {
        $response = $this->callApi('/activites');
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(3, $response['data']);
        
        // Vérifier la structure et le contenu
        $this->assertEquals(1, $response['data'][0]['id']);
        $this->assertEquals('Restaurant', $response['data'][0]['libelle']);
    }
    
    public function testGetEntreprisesAutour()
    {
        $response = $this->callApi('/autour', 'GET', [
            'lat' => 48.8566,
            'lng' => 2.3522,
            'rayon' => 5
        ]);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertGreaterThanOrEqual(3, count($response['data']));
        
        // Vérifier que les distances sont calculées
        $this->assertArrayHasKey('distance', $response['data'][0]);
    }
    
    public function testSearchEntreprises()
    {
        $response = $this->callApi('/recherche', 'GET', [
            'q' => 'Restaurant'
        ]);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertGreaterThanOrEqual(1, count($response['data']));
        
        // Vérifier que les résultats contiennent "Restaurant"
        $found = false;
        foreach ($response['data'] as $entreprise) {
            if (strpos($entreprise['nom'], 'Restaurant') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Aucun résultat contenant 'Restaurant' n'a été trouvé");
    }
    
    public function testGetEntrepriseById()
    {
        $response = $this->callApi('/entreprises/1');
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals(1, $response['id']);
        $this->assertEquals('Restaurant Le Test', $response['nom']);
        
        // Vérifier les relations
        $this->assertArrayHasKey('adresses', $response);
        $this->assertArrayHasKey('contacts', $response);
        $this->assertArrayHasKey('activites', $response);
    }
    
    public function testGetEntreprisesByActivite()
    {
        $response = $this->callApi('/activites/1/entreprises');
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('activite', $response);
        $this->assertArrayHasKey('entreprises', $response);
        
        // Vérifier l'activité
        $this->assertEquals(1, $response['activite']['id']);
        $this->assertEquals('Restaurant', $response['activite']['libelle']);
        
        // Vérifier les entreprises associées
        $this->assertGreaterThanOrEqual(1, count($response['entreprises']));
        $this->assertEquals(1, $response['entreprises'][0]['id']);
        $this->assertEquals('Restaurant Le Test', $response['entreprises'][0]['nom']);
    }
    
    public function testInvalidEndpoint()
    {
        $response = $this->callApi('/endpoint_inexistant');
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }
}
