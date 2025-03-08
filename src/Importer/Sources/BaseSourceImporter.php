<?php
namespace App\Importer\Sources;

use GuzzleHttp\Client;
use PDO;

/**
 * Classe de base pour tous les importateurs de sources
 */
abstract class BaseSourceImporter {
    protected $db;
    protected $httpClient;
    protected $logger;
    
    /**
     * Constructeur
     * 
     * @param PDO $db Connexion à la base de données
     * @param Client $httpClient Client HTTP pour les requêtes
     * @param callable $logger Fonction de journalisation
     */
    public function __construct(PDO $db, Client $httpClient, callable $logger) {
        $this->db = $db;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }
    
    /**
     * Méthode principale d'importation à implémenter par les classes filles
     * 
     * @param string $keyword Mot-clé de recherche
     * @param string $location Localisation
     * @return int Nombre total d'entreprises importées
     */
    abstract public function import($keyword, $location);
    
    /**
     * Écrit un message dans les logs
     * 
     * @param string $message Message à journaliser
     */
    protected function log($message) {
        call_user_func($this->logger, $message);
    }
    
    /**
     * Vérifie si une entreprise existe déjà par son nom et sa source
     * 
     * @param string $nom Nom de l'entreprise
     * @param string $source Nom de la source
     * @return int|false ID de l'entreprise si elle existe, false sinon
     */
    protected function entrepriseExistsByName($nom, $source) {
        $stmt = $this->db->prepare("SELECT id FROM entreprises WHERE nom = ? AND source = ?");
        $stmt->execute([$nom, $source]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Vérifie si une entreprise existe déjà par son SIRET
     * 
     * @param string $siret Numéro SIRET
     * @return int|false ID de l'entreprise si elle existe, false sinon
     */
    protected function entrepriseExistsBySiret($siret) {
        $stmt = $this->db->prepare("SELECT id FROM entreprises WHERE siret = ?");
        $stmt->execute([$siret]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Enregistre une nouvelle entreprise
     * 
     * @param array $data Données de l'entreprise
     * @return int ID de la nouvelle entreprise
     */
    protected function insertEntreprise($data) {
        $stmt = $this->db->prepare("
            INSERT INTO entreprises (
                nom, siret, raison_sociale, date_creation, forme_juridique,
                capital, code_naf, tranche_effectif, source, source_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'] ?? '',
            $data['siret'] ?? null,
            $data['raison_sociale'] ?? null,
            $data['date_creation'] ?? null,
            $data['forme_juridique'] ?? null,
            $data['capital'] ?? null,
            $data['code_naf'] ?? null,
            $data['tranche_effectif'] ?? null,
            $data['source'],
            $data['source_id']
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Enregistre une adresse pour une entreprise
     * 
     * @param int $entrepriseId ID de l'entreprise
     * @param array $data Données de l'adresse
     * @return int ID de la nouvelle adresse
     */
    protected function insertAdresse($entrepriseId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO adresses (
                entreprise_id, type, adresse, complement, code_postal, ville, pays, latitude, longitude
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                adresse = VALUES(adresse),
                complement = VALUES(complement),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude)
        ");
        $stmt->execute([
            $entrepriseId,
            $data['type'] ?? 'siege',
            $data['adresse'] ?? '',
            $data['complement'] ?? null,
            $data['code_postal'] ?? '',
            $data['ville'] ?? '',
            $data['pays'] ?? 'France',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Enregistre un contact pour une entreprise
     * 
     * @param int $entrepriseId ID de l'entreprise
     * @param string $type Type de contact (telephone, email, etc.)
     * @param string $valeur Valeur du contact
     * @param string|null $description Description optionnelle
     */
    protected function insertContact($entrepriseId, $type, $valeur, $description = null) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO contacts (entreprise_id, type, valeur, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$entrepriseId, $type, $valeur, $description]);
    }
    
    /**
     * Enregistre un site web pour une entreprise
     * 
     * @param int $entrepriseId ID de l'entreprise
     * @param string $url URL du site web
     * @param string $type Type de site web
     */
    protected function insertSiteWeb($entrepriseId, $url, $type = 'site_officiel') {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO sites_web (entreprise_id, url, type)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$entrepriseId, $url, $type]);
    }
    
/**
 * Enregistre ou récupère une activité
 * 
 * @param string $libelle Libellé de l'activité
 * @param string|null $code Code de l'activité (optionnel)
 * @return int ID de l'activité
 */
protected function getOrCreateActivite($libelle, $code = null) 
{
	// Si le libellé contient un code (ex: "Abattage du bétail - 35.01")
	if ($code === null && strpos($libelle, ' - ') !== false) 
	{
		$parts = explode(' - ', $libelle, 2);
		$libelle = trim($parts[0]);
		$code = trim($parts[1]);
	}
	
	// Vérifier si l'activité existe déjà par son code ou son libellé
	$stmt = null;
	if ($code !== null) 
	{
		$stmt = $this->db->prepare("SELECT id FROM activites WHERE code = ?");
		$stmt->execute([$code]);
		$activiteId = $stmt->fetchColumn();
		
		if ($activiteId)
			return $activiteId;
	}
	
	// Si pas trouvé par code ou pas de code, chercher par libellé
	$stmt = $this->db->prepare("SELECT id FROM activites WHERE libelle = ?");
	$stmt->execute([$libelle]);
	$activiteId = $stmt->fetchColumn();
	
	if (!$activiteId) 
	{
		// Insérer nouvelle activité
		$stmt = $this->db->prepare("INSERT INTO activites (libelle, code) VALUES (?, ?)");
		$stmt->execute([$libelle, $code]);
		$activiteId = $this->db->lastInsertId();
	} 
	else if ($code !== null) 
	{
		// Mettre à jour le code si nécessaire
		$stmt = $this->db->prepare("UPDATE activites SET code = ? WHERE id = ? AND (code IS NULL OR code = '')");
		$stmt->execute([$code, $activiteId]);
	}
	
	return $activiteId;
}
    /**
     * Associe une activité à une entreprise
     * 
     * @param int $entrepriseId ID de l'entreprise
     * @param int $activiteId ID de l'activité
     */
    protected function associateActivite($entrepriseId, $activiteId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO entreprises_activites (entreprise_id, activite_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$entrepriseId, $activiteId]);
    }
    
    /**
     * Enregistre un dirigeant pour une entreprise
     * 
     * @param int $entrepriseId ID de l'entreprise
     * @param array $data Données du dirigeant
     */
    protected function insertDirigeant($entrepriseId, $data) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO dirigeants (
                entreprise_id, nom, prenom, fonction, date_naissance, date_debut_fonction
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $entrepriseId,
            $data['nom'] ?? '',
            $data['prenom'] ?? null,
            $data['fonction'] ?? null,
            $data['date_naissance'] ?? null,
            $data['date_debut_fonction'] ?? null
        ]);
    }
}
