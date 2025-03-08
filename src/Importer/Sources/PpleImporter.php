i<?php
namespace App\Importer\Sources;

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Importateur spécifique pour PPLE.fr
 */
class PpleImporter extends BaseSourceImporter {
    // Configuration spécifique à PPLE
    protected $url_base = 'https://www.pple.fr/recherche?q={keyword}&ville={location}&page={page}';
    protected $max_pages = 5;
    protected $delay_min = 2;
    protected $delay_max = 5;
    
    /**
     * Importe les données depuis PPLE.fr
     */
    public function import($keyword, $location) {
        $totalImported = 0;
        $pageFailCount = 0;
        $maxFailCount = 3; // Nombre maximum d'échecs consécutifs avant d'arrêter
        
        for ($page = 1; $page <= $this->max_pages; $page++) {
            $url = str_replace(
                ['{keyword}', '{location}', '{page}'],
                [urlencode($keyword), urlencode($location), $page],
                $this->url_base
            );
            
            try {
                $this->log("Récupération de la page PPLE $page pour '$keyword' à '$location'");
                
                // Simuler un délai aléatoire comme un utilisateur humain
                $delay = mt_rand($this->delay_min, $this->delay_max);
                sleep($delay);
                
                // Effectuer la requête avec Guzzle
                $response = $this->httpClient->get($url);
                $statusCode = $response->getStatusCode();
                
                // Vérifier le code de statut
                if ($statusCode !== 200) {
                    $this->log("Erreur HTTP $statusCode pour la page PPLE $page");
                    
                    // Si on obtient un 403 ou 429, c'est probablement une limitation de taux
                    if ($statusCode == 403 || $statusCode == 429) {
                        $pageFailCount++;
                        
                        if ($pageFailCount >= $maxFailCount) {
                            $this->log("Trop d'échecs consécutifs, arrêt de l'importation PPLE");
                            break;
                        }
                        
                        // Attendre plus longtemps et réessayer plus tard
                        $this->log("Attente de 60 secondes avant de réessayer...");
                        sleep(60);
                        $page--; // Réessayer la même page
                        continue;
                    }
                    
                    // Pour les autres erreurs, passer à la page suivante
                    continue;
                }
                
                // Réinitialiser le compteur d'échecs
                $pageFailCount = 0;
                
                $html = $response->getBody()->getContents();
                
                // Vérifier si la page contient un captcha ou une protection
                if (strpos($html, 'captcha') !== false || 
                    strpos($html, 'security check') !== false) {
                    $this->log("Détection de protection anti-bot sur PPLE, attente de 2 minutes...");
                    sleep(120); // Attendre 2 minutes
                    continue;
                }
                
                // Utilisation de Symfony DomCrawler pour parser le HTML
                $crawler = new Crawler($html);
                
                // Vérifier si nous avons des résultats (adaptation aux sélecteurs CSS de PPLE)
                $entrepriseNodes = $crawler->filter('.search-result-item'); // À ajuster selon la structure réelle de PPLE
                $count = $entrepriseNodes->count();
                
                $this->log("$count entreprises trouvées sur la page PPLE $page");
                
                if ($count == 0) {
                    // Si aucun résultat sur cette page, arrêter
                    if ($page === 1) {
                        $this->log("Aucun résultat trouvé sur PPLE pour '$keyword' à '$location'");
                    }
                    break; // Aucune raison de continuer à la page suivante si la page actuelle n'a pas de résultats
                }
                
                $pageImported = 0;
                
                $entrepriseNodes->each(function (Crawler $node) use (&$totalImported, &$pageImported) {
                    // Extraction des données (adaptez les sélecteurs selon la structure HTML de PPLE)
                    try {
                        $nom = trim($node->filter('.company-name')->text(''));
                        
                        if (empty($nom)) {
                            return; // Ignorer les résultats sans nom
                        }
                        
                        // Vérifier si l'entreprise existe déjà
                        $entrepriseId = $this->entrepriseExistsByName($nom, 'pple');
                        
                        if (!$entrepriseId) {
                            // Obtenir un identifiant unique pour l'entreprise sur PPLE
                            // Soit depuis un attribut data, soit en générant un UUID
                            $sourceId = $node->attr('data-company-id') ?? uniqid('pple_');
                            
                            // Insertion nouvelle entreprise
                            $entrepriseId = $this->insertEntreprise([
                                'nom' => $nom,
                                'source' => 'pple',
                                'source_id' => $sourceId
                            ]);
                            $totalImported++;
                            $pageImported++;
                        }
                        
                        // Adresse
                        $adresseComplete = $node->filter('.company-address')->text('');
                        if ($adresseComplete) {
                            // Extraction des composants de l'adresse (adaptation au format PPLE)
                            // Format supposé: rue, code postal ville
                            if (preg_match('/(.+),\s*(\d{5})\s+(.+)/', $adresseComplete, $matches) || 
                                preg_match('/(.+)\s+(\d{5})\s+(.+)/', $adresseComplete, $matches)) {
                                
                                $rue = trim($matches[1]);
                                $codePostal = $matches[2];
                                $ville = trim($matches[3]);
                                
                                $this->insertAdresse($entrepriseId, [
                                    'type' => 'siege',
                                    'adresse' => $rue,
                                    'code_postal' => $codePostal,
                                    'ville' => $ville
                                ]);
                                
                                // Si la géolocalisation est disponible, l'ajouter aussi
                                $lat = $node->attr('data-lat') ?? null;
                                $lng = $node->attr('data-lng') ?? null;
                                
                                if ($lat && $lng) {
                                    $stmt = $this->db->prepare("
                                        UPDATE adresses SET latitude = ?, longitude = ?
                                        WHERE entreprise_id = ? AND code_postal = ? AND ville = ?
                                    ");
                                    $stmt->execute([$lat, $lng, $entrepriseId, $codePostal, $ville]);
                                }
                            }
                        }
                        
                        // Téléphone
                        try {
                            $telephone = $node->filter('.company-phone')->text('');
                            if ($telephone) {
                                // Normaliser le numéro de téléphone
                                $telephone = preg_replace('/\s+/', '', $telephone);
                                $this->insertContact($entrepriseId, 'telephone', $telephone);
                            }
                        } catch (\Exception $e) {
                            // Ignorer si le téléphone n'est pas disponible
                        }
                        
                        // Email 
                        try {
                            $email = $node->filter('.company-email')->text('');
                            if ($email) {
                                $this->insertContact($entrepriseId, 'email', $email);
                            }
                        } catch (\Exception $e) {
                            // Ignorer si l'email n'est pas disponible
                        }
                        
                        // Site web
                        try {
                            $siteWeb = $node->filter('.company-website')->attr('href', '');
                            if ($siteWeb) {
                                $this->insertSiteWeb($entrepriseId, $siteWeb);
                            }
                        } catch (\Exception $e) {
                            // Ignorer si le site web n'est pas disponible
                        }
                        
                        // Activités/catégories
                        try {
                            $activites = $node->filter('.company-categories')->text('');
                            if ($activites) {
                                $activitesArray = explode(',', $activites);
                                foreach ($activitesArray as $activite) {
                                    $activite = trim($activite);
                                    if ($activite) {
                                        $activiteId = $this->getOrCreateActivite($activite);
                                        $this->associateActivite($entrepriseId, $activiteId);
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignorer si pas d'activités
                        }
                        
                    } catch (\Exception $e) {
                        $this->log("Erreur lors du traitement d'une entreprise PPLE: " . $e->getMessage());
                    }
                });
                
                $this->log("$pageImported entreprises importées depuis la page PPLE $page");
                
                // Délai aléatoire entre les pages pour éviter d'être détecté comme un robot
                $delayBetweenPages = mt_rand(5, 15);
                $this->log("Attente de $delayBetweenPages secondes avant la prochaine page PPLE...");
                sleep($delayBetweenPages);
                
            } catch (RequestException $e) {
                $this->log("Erreur lors du scraping de la page PPLE $page pour '$keyword': " . $e->getMessage());
                $pageFailCount++;
                
                if ($pageFailCount >= $maxFailCount) {
                    $this->log("Trop d'échecs consécutifs, arrêt de l'importation PPLE");
                    break;
                }
                
                // Attendre plus longtemps en cas d'erreur
                sleep(30);
            } catch (\Exception $e) {
                $this->log("Exception lors du traitement de la page PPLE $page pour '$keyword': " . $e->getMessage());
            }
        }
        
        return $totalImported;
    }
}
