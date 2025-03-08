<?php
header('Content-Type: application/json');

// Log de la requête pour le débogage
error_log('API Request: ' . $_SERVER['REQUEST_URI']);

// Définir les routes
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api/', '/', $uri);

// Réponse simple pour tester
if ($uri === '/activites') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            ['id' => 1, 'libelle' => 'Restaurant'],
            ['id' => 2, 'libelle' => 'Coiffeur'],
            ['id' => 3, 'libelle' => 'Boulangerie']
        ]
    ]);
} elseif (strpos($uri, '/autour') === 0) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            [
                'id' => 1, 
                'nom' => 'Entreprise Test',
                'adresse' => '123 rue Test',
                'code_postal' => '75000',
                'ville' => 'Paris',
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'distance' => 1.2
            ]
        ],
        'pagination' => [
            'total' => 1,
            'per_page' => 10,
            'current_page' => 1,
            'total_pages' => 1
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint non trouvé']);
}
