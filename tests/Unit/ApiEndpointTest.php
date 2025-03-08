<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class ApiEndpointTest extends TestCase
{
    private $client;
    private $baseUri;
    
    protected function setUp(): void
    {
        // Configurer le client HTTP avec une URL de base pour les tests
        $this->baseUri = 'https://demo1.phredd.fr'; // Modifier selon votre environnement
        
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'http_errors' => false, // Désactiver les exceptions pour les erreurs HTTP
        ]);
    }
    
    public function testActivitesEndpoint()
    {
        $response = $this->client->get('/api/activites');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        // Si des données sont présentes, vérifier leur structure
        if (count($data['data']) > 0) {
            $this->assertArrayHasKey('id', $data['data'][0]);
            $this->assertArrayHasKey('libelle', $data['data'][0]);
        }
    }
    
    public function testEntreprisesAutourEndpoint()
    {
        // Coordonnées de test
        $lat = 48.8566;
        $lng = 2.3522;
        $rayon = 5;
        
        $response = $this->client->get("/api/autour?lat=$lat&lng=$lng&rayon=$rayon");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('pagination', $data);
        
        // Si des données sont présentes, vérifier leur structure
        if (count($data['data']) > 0) {
            $this->assertArrayHasKey('id', $data['data'][0]);
            $this->assertArrayHasKey('nom', $data['data'][0]);
            $this->assertArrayHasKey('latitude', $data['data'][0]);
            $this->assertArrayHasKey('longitude', $data['data'][0]);
            $this->assertArrayHasKey('distance', $data['data'][0]);
        }
    }
    
    public function testRechercheEndpoint()
    {
        $query = 'restaurant';
        
        $response = $this->client->get("/api/recherche?q=$query");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('pagination', $data);
    }
    
    public function testEntrepriseDetailEndpoint()
    {
        // Cet ID doit exister dans votre base de données de test
        // Si non, le test échouera - vous pouvez le mettre à jour avec un ID valide
        $entrepriseId = 1;
        
        $response = $this->client->get("/api/entreprises/$entrepriseId");
        
        // Si l'entreprise existe, le code devrait être 200, sinon 404
        if ($response->getStatusCode() == 200) {
            $data = json_decode($response->getBody(), true);
            
            $this->assertIsArray($data);
            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('nom', $data);
            $this->assertEquals($entrepriseId, $data['id']);
        } else {
            $this->assertEquals(404, $response->getStatusCode());
        }
    }
    
    public function testInvalidEndpoint()
    {
        $response = $this->client->get('/api/endpoint_inexistant');
        
        $this->assertEquals(404, $response->getStatusCode());
    }
}
