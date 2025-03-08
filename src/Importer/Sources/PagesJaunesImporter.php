<?php
namespace App\Importer\Sources;

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Importateur spécifique pour Pages Jaunes
 */
class PagesJaunesImporter extends BaseSourceImporter {
    // Configuration spécifique à Pages Jaunes
    protected $url_base = 'https://www.pagesjaunes.fr/annuaire/chercherlespros?quoiqui={keyword}&ou={location}&page={page}';
    protected $max_pages = 5;
    protected $delay_min = 3;
    protected $delay_max = 7;
    
    /**
     * Importe les données depuis Pages Jaunes
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
                $this->log("Récupération de la page $page pour '$keyword' à '$location'");
                
                // Simuler un délai aléatoire comme un utilisateur humain
                $delay = mt_rand($this->delay_min, $this->delay_max);
                sleep($delay);
                
                // Effectuer la requête avec Guzzle
                $response = $this->httpClient->get($url);
                $statusCode = $response->getStatusCode();
                
                // Vérifier le code de statut
                if ($statusCode !== 200) {
                    $this->log("Erreur HTTP $statusCode pour la page $page");
                    
                    // Si on obtient un 403 ou 429, c'est probablement une limitation de taux
                    if ($statusCode == 403 || $statusCode == 429) {
                        $pageFailCount++;
                        
                        if ($pageFailCount >= $maxFailCount) {
                            $this->log("Trop d'échecs consécutifs, arrêt de l'importation");
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
                
                // Vérifier si la page contient un captcha ou une page "Just a moment..."
                if (strpos($html, 'Just a moment...') !== false || 
                    strpos($html, 'Cloudflare') !== false ||
                    strpos($html, 'captcha') !== false) {
                    $this->log("Détection de protection anti-bot, attente de 2 minutes...");
                    sleep(120); // Attendre 2 minutes
                    continue;
                }
                
                // Utilisation de Symfony DomCrawler pour parser le HTML
                $crawler = new Crawler($html);
                
                // Vérifier si nous avons des résultats
                $entrepriseNodes = $crawler->filter('.bi-pro');
                $count = $entrepriseNodes->count();
                
                $this->log("$count entreprises trouvées sur la page $page");
                
                if ($count == 0) {
                    // Si aucun résultat sur cette page, passer aux pages suivantes
                    // sauf si c'est la première page, dans ce cas on arrête
                    if ($page === 1) {
                        $this->log("Aucun résultat trouvé pour '$keyword' à '$location'");
                        break;
                    }
                    continue;
                }
                
                $pageImported = 0;
                
                $entrepriseNodes->each(function (Crawler $node) use (&$totalImported, &$pageImported) {
                    // Extraction des données
                    try {
                        $nom = trim($node->filter('.denomination-links')->text(''));
                        
                        if (empty($nom)) {
                            return; // Ignorer les résultats sans nom
                        }
                        
                        // Vérifier si l'entreprise existe déjà
                        $entrepriseId = $this->entrepriseExistsByName($nom, 'pagesjaunes');
                        
                        if (!$entrepriseId) {
                            // Insertion nouvelle entreprise
                            $sourceId = $node->attr('data-idetablissement') ?? uniqid();
                            $entrepriseId = $this->insertEntreprise([
                                'nom' => $nom,
                                'source' => 'pagesjaunes',
                                'source_id' => $sourceId
                            ]);
                            $totalImported++;
                            $pageImported++;
                        }
                        
                        // Adresse
                        $adresse = $node->filter('.address')->text('');
                        if ($adresse) {
                            // Extraction des composants de l'adresse
                            preg_match('/(.+?)\s+(\d{5})\s+(.+)/', $adresse, $matches);
                            
                            if (count($matches) >= 4) {
                                $rue = trim($matches[1]);
                                $codePostal = $matches[2];
                                $ville = trim($matches[3]);
                                
                                $this->insertAdresse($entrepriseId, [
                                    'type' => 'siege',
                                    'adresse' => $rue,
                                    'code_postal' => $codePostal,
                                    'ville' => $ville
                                ]);
                            }
                        }
                        
                        // Téléphone
                        $telephone = $node->filter('.tel')->text('');
                        if ($telephone) {
                            // Normaliser le numéro de téléphone
                            $telephone = preg_replace('/\s+/', '', $telephone);
                            $this->insertContact($entrepriseId, 'telephone', $telephone);
                        }
                        
                        // Site web
                        try {
                            $siteWeb = $node->filter('.site-internet a')->attr('href', '');
                            if ($siteWeb) {
                                $this->insertSiteWeb($entrepriseId, $siteWeb);
                            }
                        } catch (\Exception $e) {
                            // Ignorer si pas de site web
                        }
                        
                        // Activités
                        try {
                            $activites = $node->filter('.activite')->text('');
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
                        $this->log("Erreur lors du traitement d'une entreprise: " . $e->getMessage());
                    }
                });
                
                $this->log("$pageImported entreprises importées depuis la page $page");
                
                // Délai aléatoire entre les pages pour éviter d'être détecté comme un robot
                $delayBetweenPages = mt_rand(5, 15);
                $this->log("Attente de $delayBetweenPages secondes avant la prochaine page...");
                sleep($delayBetweenPages);
                
            } catch (RequestException $e) {
                $this->log("Erreur lors du scraping de la page $page pour '$keyword': " . $e->getMessage());
                $pageFailCount++;
                
                if ($pageFailCount >= $maxFailCount) {
                    $this->log("Trop d'échecs consécutifs, arrêt de l'importation");
                    break;
                }
                
                // Attendre plus longtemps en cas d'erreur
                sleep(30);
            } catch (\Exception $e) {
                $this->log("Exception lors du traitement de la page $page pour '$keyword': " . $e->getMessage());
            }
        }
        
        return $totalImported;
    }
}
