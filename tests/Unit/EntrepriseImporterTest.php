<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class EntrepriseImporterTest extends TestCase
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
        $this->db->exec('TRUNCATE TABLE sites_web');
        $this->db->exec('TRUNCATE TABLE activites');
        $this->db->exec('TRUNCATE TABLE entreprises_activites');
        $this->db->exec('TRUNCATE TABLE dirigeants');
        $this->db->exec('TRUNCATE TABLE import_logs');
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
    }
    
    /**
     * Prépare un mock pour le client HTTP
     */
    private function getMockHttpClient($responses)
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        
        // Historique des requêtes pour vérification
        $container = [];
        $history = Middleware::history($container);
        $handlerStack->push($history);
        
        return [
            new Client(['handler' => $handlerStack]),
            $container
        ];
    }
    
    public function testImportPagesJaunes()
    {
        // HTML de test pour simuler une réponse Pages Jaunes
        $htmlResponse = <<<HTML
        <!DOCTYPE html>
        <html>
        <body>
            <div class="bi-pro" data-idetablissement="123456">
                <a class="denomination-links">Restaurant Test</a>
                <div class="address">123 rue Test 75001 Paris</div>
                <div class="tel">01 23 45 67 89</div>
                <div class="site-internet"><a href="http://www.restaurant-test.fr">Site web</a></div>
                <div class="activite">Restaurant, Cuisine française</div>
            </div>
            <div class="bi-pro" data-idetablissement="789012">
                <a class="denomination-links">Café Example</a>
                <div class="address">456 avenue Example 75002 Paris</div>
                <div class="tel">01 98 76 54 32</div>
                <div class="activite">Café, Bar</div>
            </div>
        </body>
        </html>
        HTML;
        
        // Créer un mock du client HTTP
        list($mockClient, $container) = $this->getMockHttpClient([
            new Response(200, [], $htmlResponse)
        ]);
        
        // Créer une instance de l'importateur avec des dépendances mockées
        $importer = $this->createPartialMock('EntrepriseImporter', ['getHttpClient']);
        $importer->method('getHttpClient')->willReturn($mockClient);
        
        // Injecter la connexion à la base de données
        $reflection = new \ReflectionClass($importer);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($importer, $this->db);
        
        // Exécuter l'importation
        $result = $importer->importPagesjaunes('restaurant', 'Paris');
        
        // Vérifier que deux entreprises ont été importées
        $this->assertEquals(2, $result);
        
        // Vérifier que les entreprises sont dans la base de données
        $stmt = $this->db->query("SELECT COUNT(*) FROM entreprises");
        $this->assertEquals(2, $stmt->fetchColumn());
        
        // Vérifier les détails de la première entreprise
        $stmt = $this->db->prepare("SELECT * FROM entreprises WHERE nom = ?");
        $stmt->execute(['Restaurant Test']);
        $entreprise = $stmt->fetch();
        
        $this->assertNotFalse($entreprise);
        $this->assertEquals('pagesjaunes', $entreprise['source']);
        
        // Vérifier l'adresse
        $stmt = $this->db->prepare("
            SELECT * FROM adresses 
            WHERE entreprise_id = ? AND code_postal = ? AND ville = ?
        ");
        $stmt->execute([$entreprise['id'], '75001', 'Paris']);
        $adresse = $stmt->fetch();
        
        $this->assertNotFalse($adresse);
        $this->assertEquals('123 rue Test', $adresse['adresse']);
        
        // Vérifier le téléphone
        $stmt = $this->db->prepare("
            SELECT * FROM contacts 
            WHERE entreprise_id = ? AND type = ?
        ");
        $stmt->execute([$entreprise['id'], 'telephone']);
        $contact = $stmt->fetch();
        
        $this->assertNotFalse($contact);
        $this->assertEquals('01 23 45 67 89', $contact['valeur']);
        
        // Vérifier le site web
        $stmt = $this->db->prepare("
            SELECT * FROM sites_web 
            WHERE entreprise_id = ?
        ");
        $stmt->execute([$entreprise['id']]);
        $siteWeb = $stmt->fetch();
        
        $this->assertNotFalse($siteWeb);
        $this->assertEquals('http://www.restaurant-test.fr', $siteWeb['url']);
        
        // Vérifier les activités
        $stmt = $this->db->query("
            SELECT a.libelle FROM activites a
            JOIN entreprises_activites ea ON a.id = ea.activite_id
            JOIN entreprises e ON ea.entreprise_id = e.id
            WHERE e.nom = 'Restaurant Test'
        ");
        $activites = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $this->assertCount(2, $activites);
        $this->assertContains('Restaurant', $activites);
        $this->assertContains('Cuisine française', $activites);
    }
    
    public function testImportInfogreffe()
    {
        // JSON de test pour simuler une réponse Infogreffe
        $jsonResponse = json_encode([
            'results' => [
                [
                    'siret' => '12345678901234',
                    'denomination' => 'Entreprise SARL',
                    'nom_commercial' => 'Entreprise Test',
                    'date_creation' => '2020-01-01',
                    'forme_juridique' => 'SARL',
                    'capital' => 10000,
                    'code_naf' => '6201Z',
                    'tranche_effectif' => '10-19',
                    'siege' => [
                        'adresse' => '123 rue Business',
                        'code_postal' => '75003',
                        'ville' => 'Paris',
                        'latitude' => 48.8566,
                        'longitude' => 2.3522
                    ],
                    'dirigeants' => [
                        [
                            'nom' => 'Dupont',
                            'prenom' => 'Jean',
                            'fonction' => 'Gérant',
                            'date_debut_fonction' => '2020-01-01'
                        ]
                    ]
                ]
            ]
        ]);
        
        // Créer un mock du client HTTP
        list($mockClient, $container) = $this->getMockHttpClient([
            new Response(200, [], $jsonResponse),
            new Response(200, [], '{}') // Réponse vide pour les détails d'entreprise
        ]);
        
        // Créer une instance de l'importateur avec des dépendances mockées
        $importer = $this->createPartialMock('EntrepriseImporter', ['getHttpClient']);
        $importer->method('getHttpClient')->willReturn($mockClient);
        
        // Injecter la connexion à la base de données
        $reflection = new \ReflectionClass($importer);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($importer, $this->db);
        
        // Configurer la source Infogreffe
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sources = $sourcesProperty->getValue($importer);
        $sources['infogreffe']['api_key'] = 'test_api_key';
        $sourcesProperty->setValue($importer, $sources);
        
        // Exécuter l'importation
        $result = $importer->importInfogreffe('entreprise', 'Paris');
        
        // Vérifier qu'une entreprise a été importée
        $this->assertEquals(1, $result);
        
        // Vérifier que l'entreprise est dans la base de données
        $stmt = $this->db->prepare("SELECT * FROM entreprises WHERE siret = ?");
        $stmt->execute(['12345678901234']);
        $entreprise = $stmt->fetch();
        
        $this->assertNotFalse($entreprise);
        $this->assertEquals('Entreprise Test', $entreprise['nom']);
        $this->assertEquals('Entreprise SARL', $entreprise['raison_sociale']);
        $this->assertEquals('SARL', $entreprise['forme_juridique']);
        $this->assertEquals(10000, $entreprise['capital']);
        $this->assertEquals('6201Z', $entreprise['code_naf']);
        $this->assertEquals('10-19', $entreprise['tranche_effectif']);
        
        // Vérifier l'adresse
        $stmt = $this->db->prepare("
            SELECT * FROM adresses 
            WHERE entreprise_id = ? AND type = 'siege'
        ");
        $stmt->execute([$entreprise['id']]);
        $adresse = $stmt->fetch();
        
        $this->assertNotFalse($adresse);
        $this->assertEquals('123 rue Business', $adresse['adresse']);
        $this->assertEquals('75003', $adresse['code_postal']);
        $this->assertEquals('Paris', $adresse['ville']);
        $this->assertEquals(48.8566, $adresse['latitude']);
        $this->assertEquals(2.3522, $adresse['longitude']);
        
        // Vérifier le dirigeant
        $stmt = $this->db->prepare("
            SELECT * FROM dirigeants 
            WHERE entreprise_id = ?
        ");
        $stmt->execute([$entreprise['id']]);
        $dirigeant = $stmt->fetch();
        
        $this->assertNotFalse($dirigeant);
        $this->assertEquals('Dupont', $dirigeant['nom']);
        $this->assertEquals('Jean', $dirigeant['prenom']);
        $this->assertEquals('Gérant', $dirigeant['fonction']);
    }
}
