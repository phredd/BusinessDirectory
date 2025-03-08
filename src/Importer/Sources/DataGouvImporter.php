<?php
namespace App\Importer\Sources;

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Importateur spécifique pour l'Annuaire des Entreprises de data.gouv.fr
 */
class DataGouvImporter extends BaseSourceImporter 
{
	// Configuration spécifique à l'annuaire des entreprises data.gouv.fr
	protected $base_url = 'https://annuaire-entreprises.data.gouv.fr';
	protected $url_departements = 'https://annuaire-entreprises.data.gouv.fr/departements/';
	protected $max_pages_per_activity = 10; // Limiter le nombre de pages par activité
	protected $delay_min = 2;
	protected $delay_max = 5;
	
	/**
	 * Importe les données depuis annuaire-entreprises.data.gouv.fr
	 * 
	 * @param string $keyword Mot-clé de recherche ou code du département (ex: "75", "69", etc.)
	 * @param string $location Non utilisé pour cet importateur
	 * @return int Nombre total d'entreprises importées
	 */
	public function import($keyword, $location = null) 
	{
		$totalImported = 0;
		
		// Si le keyword est un département (code numérique), on l'utilise directement
		if (is_numeric($keyword) && strlen($keyword) <= 3)
			$departements = [$keyword];
		// Sinon, on récupère tous les départements
		else
			$departements = $this->getDepartementsList();
		
		foreach ($departements as $deptInfo) 
		{
			// Si c'est juste un code département
			if (is_string($deptInfo) && is_numeric($deptInfo)) 
			{
				$deptUrl = $this->findDepartementUrl($deptInfo);
				if (!$deptUrl)
				{
					$this->log("URL pour le département $deptInfo non trouvée, passage au suivant");
					continue;
				}
				$deptCode = $deptInfo;
			} 
			// Si c'est un tableau avec le code et l'URL
			else 
			{
				$deptCode = $deptInfo['code'];
				$deptUrl = $deptInfo['url'];
			}
			
			// Obtenir la liste des activités pour ce département
			$activities = $this->getActivitiesForDepartement($deptCode);
			$this->log("Nombre d'activités trouvées pour le département $deptCode: " . count($activities));
			
			// Traiter chaque activité
			$deptImported = 0;
			foreach ($activities as $activity) 
			{
				$activityImported = $this->importActivity($deptCode, $activity['url'], $activity['code']);
				$deptImported += $activityImported;
				
				// Pause entre les activités
				sleep(mt_rand(2, 4));
			}
			
			$totalImported += $deptImported;
			$this->log("Total importé pour le département $deptCode: $deptImported entreprises");
			
			// Pause entre les départements
			sleep(mt_rand(3, 8));
		}
		
		return $totalImported;
	}
	
	/**
	 * Récupère la liste des départements disponibles
	 * 
	 * @return array Liste des codes de départements avec leurs URLs
	 */
	protected function getDepartementsList() 
	{
		try 
		{
			$this->log("Récupération de la liste des départements");
			
			$response = $this->httpClient->get($this->url_departements);
			
			if ($response->getStatusCode() !== 200)
			{
				$this->log("Erreur HTTP " . $response->getStatusCode() . " lors de la récupération des départements");
				return [['code' => '75', 'url' => $this->base_url . '/departements/75-paris/index.html']]; // Par défaut, Paris
			}
			
			$html = $response->getBody()->getContents();
			$crawler = new Crawler($html);
			
			// Extraction des URLs de départements depuis les liens dans la div spécifiée
			$departements = [];
			$crawler->filter('.fr-container.body-wrapper a')->each(function (Crawler $node) use (&$departements) 
			{
				$href = $node->attr('href');
				$text = trim($node->text());
				
				// Extraire le code du département à partir de l'URL
				if (preg_match('#/departements/(\d+)-([^/]+)/index\.html$#', $href, $matches))
					$departements[] = [
						'code' => $matches[1],
						'name' => $matches[2],
						'url' => $this->base_url . $href
					];
			});
			
			if (empty($departements))
			{
				$this->log("Aucun département trouvé, utilisation de la valeur par défaut");
				return [['code' => '75', 'url' => $this->base_url . '/departements/75-paris/index.html']]; // Par défaut, Paris
			}
			
			$this->log(count($departements) . " départements trouvés");
			return $departements;
			
		} 
		catch (\Exception $e) 
		{
			$this->log("Erreur lors de la récupération des départements: " . $e->getMessage());
			return [['code' => '75', 'url' => $this->base_url . '/departements/75-paris/index.html']]; // Par défaut, Paris
		}
	}
	
	/**
	 * Trouve l'URL d'un département à partir de son code
	 * 
	 * @param string $deptCode Code du département
	 * @return string|null URL du département ou null si non trouvé
	 */
	protected function findDepartementUrl($deptCode) 
	{
		// Récupérer la liste complète des départements
		$allDepts = $this->getDepartementsList();
		
		// Rechercher le département par son code
		foreach ($allDepts as $dept)
			if ($dept['code'] == $deptCode)
				return $dept['url'];
		
		return null;
	}
	
	/**
	 * Récupère la liste des activités pour un département
	 * 
	 * @param string $deptCode Code du département
	 * @return array Liste des activités avec leurs URLs
	 */
	protected function getActivitiesForDepartement($deptCode) 
	{
		try 
		{
			$deptUrl = $this->base_url . "/departements/{$deptCode}-";
			$this->log("Récupération des activités pour le département: $deptCode");
			
			// D'abord, obtenir l'URL complète du département
			$deptUrl = $this->findDepartementUrl($deptCode);
			
			if (!$deptUrl)
			{
				$this->log("URL du département $deptCode non trouvée");
				return [];
			}
			
			$response = $this->httpClient->get($deptUrl);
			
			if ($response->getStatusCode() !== 200)
			{
				$this->log("Erreur HTTP " . $response->getStatusCode() . " lors de la récupération des activités");
				return [];
			}
			
			$html = $response->getBody()->getContents();
			$crawler = new Crawler($html);
			
			// Extraction des liens d'activités depuis la div contenant les classes fr-container et body-wrapper
			$activities = [];
			$crawler->filter('.fr-container.body-wrapper a')->each(function (Crawler $node) use (&$activities, $deptCode) 
			{
				$href = $node->attr('href');
				$text = trim($node->text());
				
				// Vérifier si c'est un lien d'activité (format spécifique avec [code]/1.html)
				if (preg_match('#/departements/(\d+)-([^/]+)/([^/]+)/1\.html$#', $href, $matches))
				{
					$deptCodeFromUrl = $matches[1];
					$deptName = $matches[2];
					$activityCode = $matches[3];
					
					// Ne traiter que si c'est le bon département
					if ($deptCodeFromUrl == $deptCode)
					{
						// Construire l'URL complète
						$url = $this->base_url . $href;
						
						$activities[] = [
							'code' => $activityCode,
							'url' => $url
						];
					}
				}
			});
			
			$this->log(count($activities) . " activités trouvées pour le département $deptCode");
			return $activities;
			
		} 
		catch (\Exception $e) 
		{
			$this->log("Erreur lors de la récupération des activités: " . $e->getMessage());
			return [];
		}
	}
	
	/**
	 * Importe les entreprises pour une activité spécifique
	 * 
	 * @param string $departement Code du département
	 * @param string $activityUrl URL de la première page de l'activité
	 * @param string $activityCode Code de l'activité
	 * @return int Nombre d'entreprises importées
	 */
	protected function importActivity($departement, $activityUrl, $activityCode) 
	{
		$totalImported = 0;
		$processedUrls = []; // Pour éviter les doublons
		
		$this->log("Import des entreprises pour l'activité '$activityCode' du département $departement");
		
		try 
		{
			// Commencer par la première page
			$pageUrl = $activityUrl;
			$pageNum = 1;
			
			while ($pageUrl && $pageNum <= $this->max_pages_per_activity) 
			{
				$this->log("Traitement de la page $pageNum pour l'activité '$activityCode'");
				
				// Délai aléatoire pour éviter d'être bloqué
				sleep(mt_rand($this->delay_min, $this->delay_max));
				
				// Si cette URL a déjà été traitée, passer à la suivante
				if (in_array($pageUrl, $processedUrls))
				{
					$this->log("URL $pageUrl déjà traitée, passage à la suivante");
					break;
				}
				
				$processedUrls[] = $pageUrl;
				
				$response = $this->httpClient->get($pageUrl);
				
				if ($response->getStatusCode() !== 200)
				{
					$this->log("Erreur HTTP " . $response->getStatusCode() . " pour la page $pageNum");
					break;
				}
				
				$html = $response->getBody()->getContents();
				$crawler = new Crawler($html);
				
				// Récupérer tous les liens vers les entreprises
				$entrepriseLinks = [];
				$crawler->filter('a')->each(function (Crawler $node) use (&$entrepriseLinks) 
				{
					$href = $node->attr('href');
					
					// Format des liens d'entreprises: /entreprise/[slug]-[siren]
					//if (preg_match('#/entreprise/([^-]+)-(\d{9})$#', $href, $matches))
					if (preg_match('#/entreprise/([a-zA-Z-]+)-(\d{9})$#', $href, $matches))
					{
						$slug = $matches[1];
						$siren = $matches[2];
						
						$entrepriseLinks[] = [
							'url' => $this->base_url . $href,
							'siren' => $siren
						];
					}
				});
				
				$this->log(count($entrepriseLinks) . " liens d'entreprises trouvés sur la page $pageNum");
				
				// Importer chaque entreprise
				$pageImported = 0;
				foreach ($entrepriseLinks as $entrepriseLink) 
				{
					$imported = $this->importEntreprise($entrepriseLink['url'], $entrepriseLink['siren'], $activityCode);
					if ($imported)
						$pageImported++;
					
					// Petite pause entre les entreprises
					usleep(mt_rand(500000, 1000000)); // 0.5-1 seconde
				}
				
				$totalImported += $pageImported;
				$this->log("$pageImported entreprises importées depuis la page $pageNum");
				
				// Si aucune entreprise n'a été importée, passer à la page suivante
				if ($pageImported == 0 && $pageNum > 1)
				{
					$this->log("Aucune entreprise importée sur cette page, fin de l'importation pour cette activité");
					break;
				}
				
				// Chercher le lien vers la page suivante dans la pagination
				$nextPageUrl = null;
				$crawler->filter('.pagination a')->each(function (Crawler $node) use (&$nextPageUrl, $pageNum) 
				{
					$href = $node->attr('href');
					$text = trim($node->text());
					
					// Chercher un lien contenant le numéro de la page suivante
					if ($text == (string)($pageNum + 1))
						$nextPageUrl = $this->base_url . $href;
				});
				
				if (!$nextPageUrl)
				{
					$this->log("Pas de lien vers la page suivante, fin de l'importation pour cette activité");
					break;
				}
				
				$pageUrl = $nextPageUrl;
				$pageNum++;
				
			}
		} 
		catch (\Exception $e) 
		{
			$this->log("Erreur lors de l'importation de l'activité: " . $e->getMessage());
		}
		
		return $totalImported;
	}

/**
 * Importe les données d'une entreprise spécifique
 * 
 * @param string $url URL de la page détaillée de l'entreprise
 * @param string $siren Numéro SIREN de l'entreprise
 * @param string $activityCode Code de l'activité
 * @return bool True si l'entreprise a été importée avec succès
 */
protected function importEntreprise($url, $siren, $activityCode) 
{
	try 
	{
		// Vérifier si l'entreprise existe déjà par son SIREN
		$entrepriseId = $this->entrepriseExistsBySiret($siren);
		
		if ($entrepriseId)
		{
			$this->log("Entreprise avec SIREN $siren déjà présente dans la base");
			return false;
		}
		
		$response = $this->httpClient->get($url);
		
		if ($response->getStatusCode() !== 200)
		{
			$this->log("Erreur HTTP " . $response->getStatusCode() . " lors de la récupération des détails de l'entreprise");
			return false;
		}
		
		$html = $response->getBody()->getContents();
		$crawler = new Crawler($html);
		
		// Extraire les informations de l'entreprise
		$nom = '';
		try 
		{
			$nom = trim($crawler->filter('h1, .company-name')->text(''));
		} 
		catch (\Exception $e) 
		{
			$this->log("Impossible de trouver le nom de l'entreprise");
			return false;
		}
		
		if (empty($nom))
		{
			$this->log("Nom de l'entreprise vide");
			return false;
		}
		
		// Extraire le SIRET complet si disponible
		$siret = null;
		try 
		{
			$siretText = $crawler->filter('.company-siret, .siret')->text('');
			if (preg_match('/(\d{14})/', $siretText, $matches))
				$siret = $matches[1];
		} 
		catch (\Exception $e) 
		{
			// Si pas de SIRET, on utilise le SIREN
		}
		
		// Extraire la forme juridique
		$formeJuridique = '';
		try 
		{
			$formeJuridiqueText = $crawler->filter('.company-legal-form, .legal-form')->text('');
			if (preg_match('/Forme juridique\s*:?\s*([^\n\.]+)/', $formeJuridiqueText, $matches))
				$formeJuridique = trim($matches[1]);
		} 
		catch (\Exception $e) 
		{
			// Ignorer si pas trouvé
		}
		
		// Extraire la date de création
		$dateCreation = null;
		try 
		{
			$dateCreationText = $crawler->filter('.company-creation-date, .creation-date')->text('');
			if (preg_match('/Date de création\s*:?\s*(\d{2}\/\d{2}\/\d{4})/', $dateCreationText, $matches))
				$dateCreation = \DateTime::createFromFormat('d/m/Y', $matches[1])->format('Y-m-d');
		} 
		catch (\Exception $e) 
		{
			// Ignorer si pas trouvé
		}
		
		// Insérer l'entreprise
		$entrepriseId = $this->insertEntreprise([
			'nom' => $nom,
			'siret' => $siret,
			'raison_sociale' => $nom,
			'forme_juridique' => $formeJuridique,
			'date_creation' => $dateCreation,
			'code_naf' => $activityCode,
			'source' => 'datagouv',
			'source_id' => $siren
		]);
		
		// Extraire l'adresse depuis le tableau
		$adresseComplete = '';
		try 
		{
			// Trouver la ligne du tableau où la première colonne contient "Adresse postale"
			$crawler->filter('table tr')->each(function (Crawler $row) use (&$adresseComplete) {
				$firstCell = $row->filter('td:first-child, th:first-child')->text('');
				if (stripos($firstCell, 'adresse postale') !== false) {
					$adresseComplete = trim($row->filter('td:nth-child(2)')->text(''));
				}
			});
			
			$this->log("Adresse trouvée: $adresseComplete");
			
			if (!empty($adresseComplete)) {
				// Tentative d'extraction des composants de l'adresse
				// Format attendu: "adresse code_postal ville"
				if (preg_match('/(.+)\s+(\d{5})\s+(.+)$/', $adresseComplete, $matches)) {
					$adresse = trim($matches[1]);
					$codePostal = $matches[2];
					$ville = trim($matches[3]);
					
					// Géocodage de l'adresse avec stratégie de repli
					$coordonnees = $this->geocodeAdresse($adresse, $codePostal, $ville);
					
					$this->insertAdresse($entrepriseId, [
						'type' => 'siege',
						'adresse' => $adresse,
						'code_postal' => $codePostal,
						'ville' => $ville,
						'latitude' => $coordonnees ? $coordonnees['lat'] : null,
						'longitude' => $coordonnees ? $coordonnees['lng'] : null
					]);
				}
			}
		} 
		catch (\Exception $e) 
		{
			$this->log("Erreur lors de l'extraction de l'adresse: " . $e->getMessage());
		}
		
		// Ajouter l'activité
		try 
		{
			// Extraire le libellé de l'activité
			$activityLabel = '';
			$crawler->filter('table tr')->each(function (Crawler $row) use (&$activityLabel) {
				$firstCell = $row->filter('td:first-child, th:first-child')->text('');
				if (stripos($firstCell, 'activité principale') !== false) {
					$activityLabel = trim($row->filter('td:nth-child(2)')->text(''));
				}
			});
			
			if (empty($activityLabel)) {
				$activityLabel = 'Activité ' . $activityCode;
			}
			
			// Utiliser la fonction mise à jour qui gère séparément code et libellé
			$activiteId = $this->getOrCreateActivite($activityLabel, $activityCode);
			$this->associateActivite($entrepriseId, $activiteId);
		} 
		catch (\Exception $e) 
		{
			$this->log("Erreur lors de l'extraction de l'activité: " . $e->getMessage());
		}
		
		return true;
	} 
	catch (\Exception $e) 
	{
		$this->log("Erreur lors de l'importation de l'entreprise: " . $e->getMessage());
		return false;
	}
}
/**
 * Géocode une adresse en utilisant l'API OpenStreetMap Nominatim
 * Stratégie de repli en cas d'échec:
 * 1. Essayer avec l'adresse complète
 * 2. Si échec, enlever le premier mot de l'adresse et réessayer
 * 3. Si toujours pas de résultat, chercher uniquement avec code postal et ville
 * 
 * @param string $adresse Adresse complète
 * @param string $codePostal Code postal
 * @param string $ville Nom de la ville
 * @return array|null Tableau avec 'lat' et 'lng' ou null si échec
 */
protected function geocodeAdresse($adresse, $codePostal, $ville) 
{
	// Première tentative: adresse complète
	$coordonnees = $this->geocodeAdresseSimple("$adresse, $codePostal $ville, France");
	if ($coordonnees) 
		return $coordonnees;
	
	$this->log("Géocodage: premier essai échoué, simplification de l'adresse");
	
	// Deuxième tentative: adresse sans le premier mot
	$mots = explode(' ', $adresse);
	if (count($mots) > 1) 
	{
		array_shift($mots); // Enlever le premier mot
		$adresseSimplifiee = implode(' ', $mots);
		$coordonnees = $this->geocodeAdresseSimple("$adresseSimplifiee, $codePostal $ville, France");
		if ($coordonnees) 
			return $coordonnees;
	}
	
	$this->log("Géocodage: deuxième essai échoué, utilisation uniquement du code postal et de la ville");
	
	// Troisième tentative: uniquement code postal et ville
	return $this->geocodeAdresseSimple("$codePostal $ville, France");
}

/**
 * Fonction simple de géocodage d'une adresse
 * 
 * @param string $adresse Adresse à géocoder
 * @return array|null Tableau avec 'lat' et 'lng' ou null si échec
 */
protected function geocodeAdresseSimple($adresse) 
{
	try 
	{
		// Respecter les règles d'utilisation de l'API Nominatim
		sleep(1); // Maximum 1 requête par seconde
		
		$params = [
			'q' => $adresse,
			'format' => 'json',
			'limit' => 1,
			'addressdetails' => 1
		];
		
		$response = $this->httpClient->get('https://nominatim.openstreetmap.org/search', [
			'query' => $params,
			'headers' => [
				'User-Agent' => 'AnnuaireEntreprisesImporter/1.0', // Identifiant obligatoire
				'Referer' => $this->base_url
			]
		]);
		
		if ($response->getStatusCode() !== 200) 
		{
			$this->log("Erreur de géocodage: HTTP " . $response->getStatusCode());
			return null;
		}
		
		$data = json_decode($response->getBody()->getContents(), true);
		
		if (empty($data)) 
		{
			$this->log("Aucun résultat de géocodage pour: $adresse");
			return null;
		}
		
		$result = $data[0];
		$this->log("Géocodage réussi pour '$adresse': " . $result['lat'] . ", " . $result['lon']);
		
		return [
			'lat' => $result['lat'],
			'lng' => $result['lon']
		];
	} 
	catch (\Exception $e) 
	{
		$this->log("Erreur lors du géocodage: " . $e->getMessage());
		return null;
	}
}
}
