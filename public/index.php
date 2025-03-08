<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annuaire des Entreprises Locales</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- Leaflet MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-building me-2"></i>Annuaire Entreprises</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-map-marker-alt me-1"></i>Carte</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-list me-1"></i>Liste</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-tags me-1"></i>Activités</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <button class="btn btn-light me-2" id="locate-me">
                        <i class="fas fa-location-arrow me-1"></i>Me localiser
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-3">
                <div class="filters-container mb-3">
                    <h4><i class="fas fa-filter me-2"></i>Filtres</h4>
                    <hr>
                    
                    <form id="filters-form">
                        <div class="mb-3">
                            <label for="rayon" class="form-label">Rayon de recherche (km)</label>
                            <div class="d-flex align-items-center">
                                <input type="range" class="form-range flex-grow-1 me-2" id="rayon" min="1" max="2000" value="5">
                                <span id="rayon-value">5</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="activites" class="form-label">Activités</label>
                            <div class="activities-list border p-2">
                                <div class="form-check" id="activites-container">
                                    <div class="d-flex justify-content-center">
                                        <div class="loader"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Filtrer
                        </button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Recherche</h5>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" class="form-control" id="search-input" placeholder="Nom, activité, ville...">
                            <div class="search-results" id="search-results"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div id="results-count"></div>
                    <div id="entreprises-list"></div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div id="map" class="mb-3"></div>
                
                <div id="selected-entreprise-details" class="card d-none">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0" id="selected-entreprise-title">Détails de l'entreprise</h5>
                    </div>
                    <div class="card-body" id="selected-entreprise-content">
                        <!-- Les détails seront injectés ici -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Leaflet MarkerCluster JS -->
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Application JS -->
    <script src="assets/js/bundle.js"></script>
</body>
</html>
