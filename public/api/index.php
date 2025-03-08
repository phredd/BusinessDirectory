<?php
/**
 * API Backend pour accéder aux données des entreprises
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Gestion des CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Répondre aux requêtes OPTIONS (pour les requêtes CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Routing simple basé sur l'URL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/api'; // Base path de l'API
$route = str_replace($basePath, '', $requestUri);

// Router les requêtes
try {
    $db = getDbConnection();
    
    switch (true) {
        // Liste des entreprises avec filtres
        case preg_match('#^/entreprises$#', $route) && $_SERVER['REQUEST_METHOD'] === 'GET':
            getEntreprises($db);
            break;
            
        // Détails d'une entreprise spécifique
        case preg_match('#^/entreprises/(\d+)$#', $route, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET':
            getEntrepriseById($db, $matches[1]);
            break;
            
        // Recherche d'entreprises
        case preg_match('#^/recherche$#', $route) && $_SERVER['REQUEST_METHOD'] === 'GET':
            searchEntreprises($db);
            break;
            
        // Entreprises autour d'un point géographique
        case preg_match('#^/autour$#', $route) && $_SERVER['REQUEST_METHOD'] === 'GET':
            getEntreprisesAutour($db);
            break;
            
        // Liste des activités
        case preg_match('#^/activites$#', $route) && $_SERVER['REQUEST_METHOD'] === 'GET':
            getActivites($db);
            break;
            
        // Entreprises par activité
        case preg_match('#^/activites/(\d+)/entreprises$#', $route, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET':
            getEntreprisesByActivite($db, $matches[1]);
            break;
            
        // Route non trouvée
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Ressource non trouvée']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Retourne la liste des entreprises avec filtres
 */
function getEntreprises($db) {
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(500, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Construction de la requête
    $query = "
        SELECT e.*, 
            a.adresse, a.code_postal, a.ville, a.latitude, a.longitude
        FROM entreprises e
        LEFT JOIN adresses a ON e.id = a.entreprise_id AND a.type = 'siege'
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtre par activité
    if (isset($_GET['activite']) && !empty($_GET['activite'])) {
        $query .= " 
            AND e.id IN (
                SELECT ea.entreprise_id 
                FROM entreprises_activites ea 
                JOIN activites act ON ea.activite_id = act.id 
                WHERE act.libelle LIKE ?
            )
        ";
        $params[] = '%' . $_GET['activite'] . '%';
    }
    
    // Filtre par ville
    if (isset($_GET['ville']) && !empty($_GET['ville'])) {
        $query .= " AND a.ville LIKE ?";
        $params[] = '%' . $_GET['ville'] . '%';
    }
    
    // Filtre par code postal
    if (isset($_GET['code_postal']) && !empty($_GET['code_postal'])) {
        $query .= " AND a.code_postal LIKE ?";
        $params[] = $_GET['code_postal'] . '%';
    }
    
    // Requête pour le total des résultats
    $countQuery = str_replace('SELECT e.*, a.adresse, a.code_postal, a.ville, a.latitude, a.longitude', 'SELECT COUNT(*) as total', $query);
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetch()['total'];
    
    // Ajout de la pagination
    $query .= " ORDER BY e.nom ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Exécution de la requête
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $entreprises = $stmt->fetchAll();
    
    // Récupération des données associées pour chaque entreprise
    $result = [];
    foreach ($entreprises as $entreprise) {
        $entrepriseId = $entreprise['id'];
        
        // Récupérer les contacts
        $stmtContacts = $db->prepare("
            SELECT type, valeur 
            FROM contacts 
            WHERE entreprise_id = ?
        ");
        $stmtContacts->execute([$entrepriseId]);
        $contacts = $stmtContacts->fetchAll();
        
        // Récupérer les sites web
        $stmtSites = $db->prepare("
            SELECT url, type 
            FROM sites_web 
            WHERE entreprise_id = ?
        ");
        $stmtSites->execute([$entrepriseId]);
        $sites = $stmtSites->fetchAll();
        
        // Récupérer les activités
        $stmtActivites = $db->prepare("
            SELECT a.id, a.libelle 
            FROM activites a
            JOIN entreprises_activites ea ON a.id = ea.activite_id
            WHERE ea.entreprise_id = ?
        ");
        $stmtActivites->execute([$entrepriseId]);
        $activites = $stmtActivites->fetchAll();
        
        // Récupérer les dirigeants
        $stmtDirigeants = $db->prepare("
            SELECT nom, prenom, fonction 
            FROM dirigeants 
            WHERE entreprise_id = ?
        ");
        $stmtDirigeants->execute([$entrepriseId]);
        $dirigeants = $stmtDirigeants->fetchAll();
        
        // Assembler les données
        $entrepriseData = [
            'id' => $entreprise['id'],
            'nom' => $entreprise['nom'],
            'siret' => $entreprise['siret'],
            'adresse' => [
                'adresse' => $entreprise['adresse'],
                'code_postal' => $entreprise['code_postal'],
                'ville' => $entreprise['ville'],
                'geo' => [
                    'lat' => (float)$entreprise['latitude'],
                    'lng' => (float)$entreprise['longitude']
                ]
            ],
            'contacts' => $contacts,
            'sites_web' => $sites,
            'activites' => $activites,
            'dirigeants' => $dirigeants
        ];
        
        $result[] = $entrepriseData;
    }
    
    // Construction de la réponse avec pagination
    $totalPages = ceil($totalResults / $limit);
    $response = [
        'data' => $result,
        'pagination' => [
            'total' => $totalResults,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

/**
 * Retourne les détails d'une entreprise par son ID
 */
function getEntrepriseById($db, $id) {
    // Vérifier que l'ID est numérique
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID d\'entreprise invalide']);
        return;
    }
    
    // Récupérer les informations de base de l'entreprise
    $stmt = $db->prepare("
        SELECT e.* 
        FROM entreprises e
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $entreprise = $stmt->fetch();
    
    if (!$entreprise) {
        http_response_code(404);
        echo json_encode(['error' => 'Entreprise non trouvée']);
        return;
    }
    
    // Récupérer les adresses
    $stmtAdresses = $db->prepare("
        SELECT id, type, adresse, complement, code_postal, ville, pays, latitude, longitude
        FROM adresses
        WHERE entreprise_id = ?
    ");
    $stmtAdresses->execute([$id]);
    $adresses = $stmtAdresses->fetchAll();
    
    // Récupérer les contacts
    $stmtContacts = $db->prepare("
        SELECT id, type, valeur, description
        FROM contacts
        WHERE entreprise_id = ?
    ");
    $stmtContacts->execute([$id]);
    $contacts = $stmtContacts->fetchAll();
    
    // Récupérer les sites web
    $stmtSites = $db->prepare("
        SELECT id, url, type
        FROM sites_web
        WHERE entreprise_id = ?
    ");
    $stmtSites->execute([$id]);
    $sites = $stmtSites->fetchAll();
    
    // Récupérer les activités
    $stmtActivites = $db->prepare("
        SELECT a.id, a.libelle
        FROM activites a
        JOIN entreprises_activites ea ON a.id = ea.activite_id
        WHERE ea.entreprise_id = ?
    ");
    $stmtActivites->execute([$id]);
    $activites = $stmtActivites->fetchAll();
    
    // Récupérer les dirigeants
    $stmtDirigeants = $db->prepare("
        SELECT id, nom, prenom, fonction, date_naissance, date_debut_fonction
        FROM dirigeants
        WHERE entreprise_id = ?
    ");
    $stmtDirigeants->execute([$id]);
    $dirigeants = $stmtDirigeants->fetchAll();
    
    // Assembler toutes les données
    $result = [
        'id' => $entreprise['id'],
        'siret' => $entreprise['siret'],
        'nom' => $entreprise['nom'],
        'raison_sociale' => $entreprise['raison_sociale'],
        'date_creation' => $entreprise['date_creation'],
        'forme_juridique' => $entreprise['forme_juridique'],
        'capital' => $entreprise['capital'],
        'code_naf' => $entreprise['code_naf'],
        'tranche_effectif' => $entreprise['tranche_effectif'],
        'adresses' => $adresses,
        'contacts' => $contacts,
        'sites_web' => $sites,
        'activites' => $activites,
        'dirigeants' => $dirigeants,
        'date_maj' => $entreprise['date_maj']
    ];
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

/**
 * Recherche d'entreprises par mots-clés
 */
function searchEntreprises($db) {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($q)) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètre de recherche manquant']);
        return;
    }
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Recherche dans plusieurs colonnes
    $query = "
        SELECT e.id, e.nom, e.siret, e.raison_sociale,
               a.adresse, a.code_postal, a.ville, a.latitude, a.longitude
        FROM entreprises e
        LEFT JOIN adresses a ON e.id = a.entreprise_id AND a.type = 'siege'
        LEFT JOIN entreprises_activites ea ON e.id = ea.entreprise_id
        LEFT JOIN activites act ON ea.activite_id = act.id
        WHERE e.nom LIKE ? 
           OR e.raison_sociale LIKE ? 
           OR e.siret LIKE ? 
           OR a.ville LIKE ? 
           OR a.code_postal LIKE ? 
           OR act.libelle LIKE ?
        GROUP BY e.id
    ";
    
    $searchParam = '%' . $q . '%';
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    
    // Compter le total des résultats
    $countQuery = str_replace('SELECT e.id, e.nom, e.siret, e.raison_sociale, a.adresse, a.code_postal, a.ville, a.latitude, a.longitude', 'SELECT COUNT(DISTINCT e.id) as total', $query);
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetch()['total'];
    
    // Ajouter la pagination
    $query .= " ORDER BY e.nom ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Exécuter la requête
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Construction de la réponse avec pagination
    $totalPages = ceil($totalResults / $limit);
    $response = [
        'data' => $results,
        'pagination' => [
            'total' => $totalResults,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

/**
 * Récupère les entreprises autour d'un point géographique
 */
function getEntreprisesAutour($db) {
    $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
    $rayon = isset($_GET['rayon']) ? min(2000, max(0.1, (float)$_GET['rayon'])) : 5; // en km, max 50km
    
    if ($lat === null || $lng === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres de géolocalisation manquants (lat, lng)']);
        return;
    }
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Utiliser la formule de Haversine pour calculer la distance
    $query = "
        SELECT e.id, e.nom, e.siret, a.adresse, a.code_postal, a.ville,
               a.latitude, a.longitude,
               (6371 * acos(cos(radians(?)) * cos(radians(a.latitude)) * cos(radians(a.longitude) - radians(?)) + sin(radians(?)) * sin(radians(a.latitude)))) AS distance
        FROM entreprises e
        JOIN adresses a ON e.id = a.entreprise_id
        WHERE a.latitude IS NOT NULL AND a.longitude IS NOT NULL
        HAVING distance <= ?
    ";
    
    // Filtrer par activité si spécifié
    if (isset($_GET['activite_id']) && is_numeric($_GET['activite_id'])) {
        $query .= " 
            AND e.id IN (
                SELECT ea.entreprise_id 
                FROM entreprises_activites ea 
                WHERE ea.activite_id = ?
            )
        ";
        $params = [$lat, $lng, $lat, $rayon, $_GET['activite_id']];
    } else {
        $params = [$lat, $lng, $lat, $rayon];
    }
    
    // Compter le total des résultats
    $countQuery = str_replace('SELECT e.id, e.nom, e.siret, a.adresse, a.code_postal, a.ville, a.latitude, a.longitude', 'SELECT COUNT(*)', $query);
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Ajouter tri et pagination
    $query .= " ORDER BY distance ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Exécuter la requête
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Construction de la réponse avec pagination
    $totalPages = ceil($totalResults / $limit);
    $response = [
        'data' => $results,
        'pagination' => [
            'total' => $totalResults,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

/**
 * Récupère la liste des activités
 */
function getActivites($db) {
    $query = "SELECT id, libelle FROM activites ORDER BY libelle";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $activites = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $activites]);
}

/**
 * Récupère les entreprises par activité
 */
function getEntreprisesByActivite($db, $activiteId) {
    // Vérifier que l'ID est numérique
    if (!is_numeric($activiteId)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID d\'activité invalide']);
        return;
    }
    
    // Vérifier que l'activité existe
    $stmtCheck = $db->prepare("SELECT id, libelle FROM activites WHERE id = ?");
    $stmtCheck->execute([$activiteId]);
    $activite = $stmtCheck->fetch();
    
    if (!$activite) {
        http_response_code(404);
        echo json_encode(['error' => 'Activité non trouvée']);
        return;
    }
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Récupérer les entreprises associées à cette activité
    $query = "
        SELECT e.id, e.nom, e.siret, e.raison_sociale,
               a.adresse, a.code_postal, a.ville, a.latitude, a.longitude
        FROM entreprises e
        JOIN entreprises_activites ea ON e.id = ea.entreprise_id
        LEFT JOIN adresses a ON e.id = a.entreprise_id AND a.type = 'siege'
        WHERE ea.activite_id = ?
    ";
    
    // Compter le total des résultats
    $countQuery = str_replace('SELECT e.id, e.nom, e.siret, e.raison_sociale, a.adresse, a.code_postal, a.ville, a.latitude, a.longitude', 'SELECT COUNT(*)', $query);
    $stmt = $db->prepare($countQuery);
    $stmt->execute([$activiteId]);
    $totalResults = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Ajouter tri et pagination
    $query .= " ORDER BY e.nom ASC LIMIT ? OFFSET ?";
    
    // Exécuter la requête
    $stmt = $db->prepare($query);
    $stmt->execute([$activiteId, $limit, $offset]);
    $entreprises = $stmt->fetchAll();
    
    // Construction de la réponse avec pagination
    $totalPages = ceil($totalResults / $limit);
    $response = [
        'activite' => $activite,
        'entreprises' => $entreprises,
        'pagination' => [
            'total' => $totalResults,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

