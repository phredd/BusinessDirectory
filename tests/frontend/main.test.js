/**
 * Tests unitaires pour le frontend JavaScript
 * À exécuter avec Jest
 */

// Mock pour Leaflet
jest.mock('leaflet', () => ({
  map: jest.fn().mockReturnValue({
    setView: jest.fn().mockReturnThis(),
    addLayer: jest.fn().mockReturnThis(),
    on: jest.fn().mockReturnThis()
  }),
  tileLayer: jest.fn().mockReturnValue({
    addTo: jest.fn().mockReturnThis()
  }),
  marker: jest.fn().mockReturnValue({
    bindPopup: jest.fn().mockReturnThis(),
    on: jest.fn().mockReturnThis(),
    getLatLng: jest.fn().mockReturnValue({ lat: 48.8566, lng: 2.3522 }),
    openPopup: jest.fn().mockReturnThis()
  }),
  markerClusterGroup: jest.fn().mockReturnValue({
    addLayer: jest.fn().mockReturnThis(),
    clearLayers: jest.fn().mockReturnThis()
  }),
  divIcon: jest.fn().mockReturnValue({}),
}));

// Mock pour les fonctions de fetch
global.fetch = jest.fn();

// Mock pour le DOM
document.body.innerHTML = `
  <div id="map"></div>
  <div id="results-count"></div>
  <div id="entreprises-list"></div>
  <div id="selected-entreprise-details" class="d-none">
    <div id="selected-entreprise-title"></div>
    <div id="selected-entreprise-content"></div>
  </div>
  <div id="activites-container"></div>
  <input id="search-input" type="text">
  <div id="search-results"></div>
  <button id="locate-me"></button>
  <form id="filters-form">
    <input id="rayon" type="range" min="1" max="50" value="5">
    <span id="rayon-value">5</span>
  </form>
`;

// Import des fonctions à tester
const {
  initMap,
  loadEntreprisesAutour,
  addEntrepriseToMap,
  addEntrepriseToList,
  selectEntreprise,
  loadEntrepriseDetails,
  hideEntrepriseDetails,
  setupSearch,
  loadActivites,
  setupFilters,
  setupLocateMe
} = require('../../src/js/main');

describe('Fonctions de la carte', () => {
  beforeEach(() => {
    // Réinitialiser tous les mocks
    jest.clearAllMocks();
    
    // Réinitialiser le DOM pour chaque test
    document.getElementById('entreprises-list').innerHTML = '';
    document.getElementById('results-count').textContent = '';
    document.getElementById('selected-entreprise-details').classList.add('d-none');
  });
  
  test('initMap initialise la carte correctement', () => {
    initMap();
    
    expect(L.map).toHaveBeenCalledWith('map');
    expect(L.tileLayer).toHaveBeenCalledWith(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', 
      expect.any(Object)
    );
  });
  
  test('loadEntreprisesAutour fait une requête API avec les bons paramètres', () => {
    // Mock pour la réponse de fetch
    const mockResponse = {
      json: jest.fn().mockResolvedValue({
        data: [],
        pagination: { total: 0 }
      }),
      ok: true
    };
    fetch.mockResolvedValue(mockResponse);
    
    loadEntreprisesAutour(48.8566, 2.3522, { rayon: 5 });
    
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/autour?lat=48.8566&lng=2.3522&rayon=5')
    );
  });
  
  test('addEntrepriseToMap ajoute un marqueur à la carte', () => {
    // Mock global pour markerClusterGroup
    global.markerClusterGroup = {
      addLayer: jest.fn()
    };
    
    // Mock global pour currentMarkers
    global.currentMarkers = [];
    
    const entreprise = {
      id: 1,
      nom: 'Test Entreprise',
      adresse: '123 Test Street',
      code_postal: '75001',
      ville: 'Paris',
      latitude: 48.8566,
      longitude: 2.3522
    };
    
    addEntrepriseToMap(entreprise);
    
    expect(L.marker).toHaveBeenCalledWith([48.8566, 2.3522]);
    expect(global.currentMarkers.length).toBe(1);
  });
  
  test('addEntrepriseToList ajoute une entreprise à la liste', () => {
    const entreprise = {
      id: 1,
      nom: 'Test Entreprise',
      adresse: '123 Test Street',
      code_postal: '75001',
      ville: 'Paris',
      distance: 1.5
    };
    
    addEntrepriseToList(entreprise);
    
    const listElement = document.getElementById('entreprises-list');
    expect(listElement.innerHTML).toContain('Test Entreprise');
    expect(listElement.innerHTML).toContain('123 Test Street');
    expect(listElement.innerHTML).toContain('1.5 km');
  });
  
  test('selectEntreprise sélectionne une entreprise et charge ses détails', () => {
    // Mock pour les fonctions
    global.loadEntrepriseDetails = jest.fn();
    global.currentMarkers = [{
      id: 1,
      marker: {
        getLatLng: jest.fn().mockReturnValue({ lat: 48.8566, lng: 2.3522 }),
        openPopup: jest.fn()
      }
    }];
    global.map = {
      setView: jest.fn()
    };
    
    // Ajouter un élément d'entreprise au DOM
    document.getElementById('entreprises-list').innerHTML = `
      <div class="entreprise-card" data-id="1">Test</div>
    `;
    
    selectEntreprise(1);
    
    // Vérifier que l'entreprise est sélectionnée visuellement
    const entrepriseElement = document.querySelector('.entreprise-card[data-id="1"]');
    expect(entrepriseElement.classList).toContain('border-primary');
    
    // Vérifier que la carte se centre sur l'entreprise
    expect(global.map.setView).toHaveBeenCalledWith(
      { lat: 48.8566, lng: 2.3522 },
      expect.any(Number)
    );
    
    // Vérifier que les détails sont chargés
    expect(global.loadEntrepriseDetails).toHaveBeenCalledWith(1);
  });
  
  test('loadEntrepriseDetails charge les détails d\'une entreprise', async () => {
    // Mock pour la réponse de fetch
    const mockResponse = {
      json: jest.fn().mockResolvedValue({
        id: 1,
        nom: 'Test Entreprise',
        siret: '12345678901234',
        adresses: [],
        contacts: [],
        activites: [],
        dirigeants: [],
        sites_web: []
      }),
      ok: true
    };
    fetch.mockResolvedValue(mockResponse);
    
    await loadEntrepriseDetails(1);
    
    expect(fetch).toHaveBeenCalledWith('/api/entreprises/1');
    expect(document.getElementById('selected-entreprise-details').classList).not.toContain('d-none');
    expect(document.getElementById('selected-entreprise-title').textContent).toBe('Test Entreprise');
  });
  
  test('hideEntrepriseDetails cache les détails d\'une entreprise', () => {
    // Préparer le DOM
    document.getElementById('selected-entreprise-details').classList.remove('d-none');
    document.getElementById('entreprises-list').innerHTML = `
      <div class="entreprise-card border-primary" data-id="1">Test</div>
    `;
    global.selectedEntrepriseId = 1;
    
    hideEntrepriseDetails();
    
    expect(document.getElementById('selected-entreprise-details').classList).toContain('d-none');
    const entrepriseElement = document.querySelector('.entreprise-card[data-id="1"]');
    expect(entrepriseElement.classList).not.toContain('border-primary');
  });
});

describe('Fonctions de recherche et filtres', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('setupSearch configure la recherche d\'entreprises', () => {
    // Mock pour la réponse de fetch
    const mockResponse = {
      json: jest.fn().mockResolvedValue({
        data: [{ id: 1, nom: 'Test Entreprise', ville: 'Paris', adresse: 'Test', code_postal: '75001' }],
        pagination: { total: 1 }
      }),
      ok: true
    };
    fetch.mockResolvedValue(mockResponse);
    
    // Mock pour l'événement d'entrée
    const inputEvent = new Event('input');
    
    setupSearch();
    
    // Simuler une saisie de recherche
    const searchInput = document.getElementById('search-input');
    searchInput.value = 'test';
    searchInput.dispatchEvent(inputEvent);
    
    // Attendre la prochaine exécution du microtask queue (pour la promesse fetch)
    return new Promise(resolve => setTimeout(resolve, 0)).then(() => {
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/api/recherche?q=test'));
      expect(document.getElementById('search-results').style.display).toBe('block');
    });
  });
  
  test('loadActivites charge les activités disponibles', async () => {
    // Mock pour la réponse de fetch
    const mockResponse = {
      json: jest.fn().mockResolvedValue({
        data: [
          { id: 1, libelle: 'Restaurant' },
          { id: 2, libelle: 'Coiffeur' }
        ]
      }),
      ok: true
    };
    fetch.mockResolvedValue(mockResponse);
    
    await loadActivites();
    
    expect(fetch).toHaveBeenCalledWith('/api/activites');
    const activitesContainer = document.getElementById('activites-container');
    expect(activitesContainer.innerHTML).toContain('Restaurant');
    expect(activitesContainer.innerHTML).toContain('Coiffeur');
  });
  
  test('setupFilters configure les filtres de recherche', () => {
    // Mock pour les variables globales
    global.loadEntreprisesAutour = jest.fn();
    global.currentPosition = { lat: 48.8566, lng: 2.3522 };
    
    // Mock pour l'événement input sur le slider de rayon
    const inputEvent = new Event('input');
    const submitEvent = new Event('submit');
    
    setupFilters();
    
    // Simuler un changement de rayon
    const rayonInput = document.getElementById('rayon');
    rayonInput.value = '10';
    rayonInput.dispatchEvent(inputEvent);
    
    expect(document.getElementById('rayon-value').textContent).toBe('10');
    
    // Simuler une soumission du formulaire
    document.getElementById('filters-form').dispatchEvent(submitEvent);
    
    expect(global.loadEntreprisesAutour).toHaveBeenCalledWith(
      48.8566, 
      2.3522, 
      expect.objectContaining({ rayon: '10' })
    );
  });
  
  test('setupLocateMe configure le bouton de localisation', () => {
    // Mock pour getUserLocation
    global.getUserLocation = jest.fn();
    
    setupLocateMe();
    
    // Simuler un clic sur le bouton
    document.getElementById('locate-me').click();
    
    expect(global.getUserLocation).toHaveBeenCalled();
  });
});
