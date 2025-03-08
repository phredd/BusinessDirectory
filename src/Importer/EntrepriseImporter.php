<?php
namespace App\Importer;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Importer\Sources\PagesJaunesImporter;
use App\Importer\Sources\InfogreffeImporter;
use App\Importer\Sources\PpleImporter;
use App\Importer\Sources\DataGouvImporter;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PDO;

/**
 * Classe principale d'importation d'entreprises
 */
class EntrepriseImporter {
    protected $db;
    protected $sources = [
        'pagesjaunes' => false,
        'infogreffe' => false,
        'pple' => false,
        'datagouv' => true  // Nouvelle source ajoutée
    ];
    protected $httpClient;
    protected $importLogId;
    protected $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:96.0) Gecko/20100101 Firefox/96.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36 Edg/96.0.1054.62'
    ];
    protected $cookieJar;
    protected $currentUserAgent;
    protected $proxyList = []; // Liste de proxies à configurer si nécessaire (format: 'http://ip:port')

    /**
     * Constructeur
     */
    public function __construct() {
        // Connexion à la base de données
        $this->db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, 
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Initialiser le cookie jar
        $this->cookieJar = new CookieJar();
        
        // Sélectionner un user agent aléatoire
        $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Initialise le client HTTP avec des configurations aléatoires
     */
    public function initHttpClient($useProxy = false) {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 15,
            'cookies' => $this->cookieJar,
            'headers' => [
                'User-Agent' => $this->currentUserAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://www.google.com/',
                'Cache-Control' => 'max-age=0',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'cross-site',
                'Sec-Fetch-User' => '?1',
            ],
            'verify' => false, // Désactiver la vérification SSL (utiliser avec précaution)
            'http_errors' => false // Ne pas lancer d'exception sur les erreurs HTTP
        ];
        
        // Utiliser un proxy si demandé et si des proxies sont configurés
        if ($useProxy && !empty($this->proxyList)) {
            $proxy = $this->proxyList[array_rand($this->proxyList)];
            $config['proxy'] = $proxy;
            $this->logMessage("Utilisation du proxy: $proxy");
        }
        
        $this->httpClient = new Client($config);
        return $this->httpClient;
    }

    /**
     * Lance l'importation pour toutes les sources activées
     */
    public function runImport($keywords = ['restaurant', 'coiffeur', 'boulangerie'], $location = 'Paris') {
        foreach ($keywords as $keyword) {
            $this->logMessage("Traitement du mot-clé: $keyword");
            
            foreach ($this->sources as $sourceName => $enabled) {
                if ($enabled) {
                    $this->logImportStart($sourceName, $keyword);
                    
                    try {
                        // Choisir l'importateur approprié
                        $importer = $this->getImporter($sourceName);
                        
                        if ($importer) {
                            $total = $importer->import($keyword, $location);
                            $this->updateImportCount($total);
                            $this->logImportEnd($sourceName, 'termine');
                        } else {
                            $this->logImportEnd($sourceName, 'erreur', "Importateur non trouvé pour $sourceName");
                        }
                    } catch (\Exception $e) {
                        $this->logImportEnd($sourceName, 'erreur', $e->getMessage());
                    }
                    
                    // Pause entre chaque source pour ne pas surcharger
                    sleep(mt_rand(5, 10));
                }
            }
        }
    }
    
    /**
     * Retourne l'importateur approprié pour la source donnée
     */
    protected function getImporter($sourceName) {
        // Créer un nouvel HttpClient
        $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
        $httpClient = $this->initHttpClient();
        
        // Instancier l'importateur approprié
        switch ($sourceName) {
            case 'pagesjaunes':
                return new PagesJaunesImporter($this->db, $httpClient, [$this, 'logMessage']);
                
            case 'infogreffe':
                return new InfogreffeImporter($this->db, $httpClient, [$this, 'logMessage']);
                
            case 'pple':
                return new PpleImporter($this->db, $httpClient, [$this, 'logMessage']);
                
            case 'datagouv':
                return new DataGouvImporter($this->db, $httpClient, [$this, 'logMessage']);
                
            default:
                return null;
        }
    }
    
    /**
     * Enregistre le début d'un import
     */
    protected function logImportStart($source, $keyword = '') {
        $stmt = $this->db->prepare("
            INSERT INTO import_logs (source, date_debut, statut, message)
            VALUES (?, NOW(), 'en_cours', ?)
        ");
        $message = "Début de l'import depuis $source" . ($keyword ? " pour '$keyword'" : '');
        $stmt->execute([$source, $message]);
        $this->importLogId = $this->db->lastInsertId();
        $this->logMessage($message);
    }
    
    /**
     * Enregistre la fin d'un import
     */
    protected function logImportEnd($source, $statut, $message = null) {
        $stmt = $this->db->prepare("
            UPDATE import_logs 
            SET date_fin = NOW(), statut = ?, message = ?
            WHERE id = ?
        ");
        $stmt->execute([$statut, $message, $this->importLogId]);
        $this->logMessage("Fin de l'import depuis $source: $statut" . ($message ? " - $message" : ''));
    }
    
    /**
     * Met à jour le nombre d'entreprises importées
     */
    protected function updateImportCount($count) {
        $stmt = $this->db->prepare("
            UPDATE import_logs 
            SET nb_entreprises = ?
            WHERE id = ?
        ");
        $stmt->execute([$count, $this->importLogId]);
    }
    
    /**
     * Écrit un message dans les logs
     */
    public function logMessage($message) {
        echo date('Y-m-d H:i:s') . " - $message\n";
    }
}
