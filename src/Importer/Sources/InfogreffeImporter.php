<?php
namespace App\Importer\Sources;

/**
 * Importateur spécifique pour Infogreffe
 */
class InfogreffeImporter extends BaseSourceImporter {
    // Configuration spécifique à Infogreffe
    protected $url_base = 'https://api.infogreffe.fr/api/recherche/entreprises';
    protected $api_key = '{{VOTRE_API_KEY_INFOGREFFE}}';
    
    /**
     * Importe les données depuis Infogreffe
     */
    public function import($keyword, $location) {
        $totalImported = 0;
        
        try {
            // Simuler une requête à l'API Infogreffe
            $response = $this->httpClient->get(
                $this->url_base, 
                [
                    'query' => [
                        'q' => $keyword,
                        'ville' => $location,
                        'limit' => 100
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->api_key
                    ]
                ]
            );
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $this->log("Erreur API Infogreffe: code $statusCode");
                return 0;
            }
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['results']) || !is_array($data['results'])) {
                $this->log("Format de réponse Infogreffe invalide ou aucun résultat");
                return 0;
            }
            
            $this->log("Traitement de " . count($data['results']) . " résultats depuis Infogreffe");
            
            foreach ($data['results'] as $entreprise) {
                $siret = $entreprise['siret'] ?? null;
                
                if ($siret) {
                    // Vérifier si l'entreprise existe déjà
                    $entrepriseId = $this->entrepriseExistsBySiret($siret);
                    
                    if (!$entrepriseId) {
                        // Insérer nouvelle entreprise
                        $entrepriseId = $this->insertEntreprise([
                            'siret' => $siret,
                            'nom' => $entreprise['nom_commercial'] ?? $entreprise['denomination'],
                            'raison_sociale' => $entreprise['denomination'] ?? '',
                            'date_creation' => $entreprise['date_creation'] ?? null,
                            'forme_juridique' => $entreprise['forme_juridique'] ?? '',
                            'capital' => $entreprise['capital'] ?? null,
                            'code_naf' => $entreprise['code_naf'] ?? '',
                            'tranche_effectif' => $entreprise['tranche_effectif'] ?? '',
                            'source' => 'infogreffe',
                            'source_id' => $siret
                        ]);
                        $totalImported++;
                    }
                    
                    // Traiter les dirigeants
                    if (isset($entreprise['dirigeants']) && is_array($entreprise['dirigeants'])) {
                        foreach ($entreprise['dirigeants'] as $dirigeant) {
                            $this->insertDirigeant($entrepriseId, [
                                'nom' => $dirigeant['nom'] ?? '',
                                'prenom' => $dirigeant['prenom'] ?? '',
                                'fonction' => $dirigeant['fonction'] ?? '',
                                'date_debut_fonction' => $dirigeant['date_debut_fonction'] ?? null
                            ]);
                        }
                    }
                    
                    // Traiter l'adresse
                    if (isset($entreprise['siege'])) {
                        $adresse = $entreprise['siege']['adresse'] ?? '';
                        $codePostal = $entreprise['siege']['code_postal'] ?? '';
                        $ville = $entreprise['siege']['ville'] ?? '';
                        
                        if ($adresse && $codePostal && $ville) {
                            $this->insertAdresse($entrepriseId, [
                                'type'
