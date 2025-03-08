// Importation des styles CSS
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

// Importation des modules JS
import L from 'leaflet';
import 'leaflet.markercluster';

// Configuration
const API_BASE_URL = '/api';
let map, markerClusterGroup;
let currentPosition = null;
let currentMarkers = [];
let selectedEntrepriseId = null;

const LIMIT	= 1000;
// Labatie
const LAT	= 45.0167;
const LON	= 4.4833;

// Initialisation de la carte
function initMap() {
	map = L.map('map').setView([LAT, LON], 13);
	
	// Ajout de la couche de tuiles OpenStreetMap
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
	}).addTo(map);
	
	// Initialiser le groupe de clusters de marqueurs
	markerClusterGroup = L.markerClusterGroup({
		showCoverageOnHover: false,
		zoomToBoundsOnClick: true,
		spiderfyOnMaxZoom: true,
		removeOutsideVisibleBounds: true
	});
	
	map.addLayer(markerClusterGroup);
	
	// Événement de clic sur la carte
	map.on('click', function() {
		hideEntrepriseDetails();
	});
	
	// Obtenir la position de l'utilisateur et charger les entreprises à proximité
	getUserLocation();
}

// Obtenir la position de l'utilisateur
function getUserLocation() {
	if (navigator.geolocation) {
		document.getElementById('locate-me').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Localisation...';
		
		navigator.geolocation.getCurrentPosition(
			function(position) {
				currentPosition = {
					lat: position.coords.latitude,
					lng: position.coords.longitude
				};
				
				map.setView([currentPosition.lat, currentPosition.lng], 14);
				
				// Ajouter un marqueur pour la position de l'utilisateur
				L.marker([currentPosition.lat, currentPosition.lng], {
					icon: L.divIcon({
						className: 'my-position-marker',
						html: '<i class="fas fa-user-circle fa-3x text-primary"></i>',
						iconSize: [30, 30],
						iconAnchor: [15, 15]
					})
				}).addTo(map)
				  .bindTooltip("Votre position", { permanent: false });
				
				// Charger les entreprises à proximité
				loadEntreprisesAutour(currentPosition.lat, currentPosition.lng);
				
				document.getElementById('locate-me').innerHTML = '<i class="fas fa-location-arrow me-1"></i>Me localiser';
			},
			function(error) {
				console.error("Erreur de géolocalisation:", error);
				document.getElementById('locate-me').innerHTML = '<i class="fas fa-location-arrow me-1"></i>Me localiser';
				alert("Impossible de vous localiser. Erreur: " + error.message);
				
				// Charger des entreprises par défaut
				loadEntreprisesAutour(LAT, LON);
			}
		);
	} else {
		alert("La géolocalisation n'est pas prise en charge par votre navigateur.");
		document.getElementById('locate-me').innerHTML = '<i class="fas fa-location-arrow me-1"></i>Me localiser';
		
		// Charger des entreprises par défaut
		loadEntreprisesAutour(LAT, LON);
	}
}

// Charger les entreprises autour d'un point géographique
function loadEntreprisesAutour(lat, lng, options = {}) {
	// Paramètres par défaut
	const params = new URLSearchParams({
		lat: lat,
		lng: lng,
		rayon: options.rayon || 5,
		limit: LIMIT
	});
	
	// Ajouter l'ID d'activité si présent
	if (options.activiteId) {
		params.append('activite_id', options.activiteId);
	}
	
	// Afficher un indicateur de chargement
	document.getElementById('entreprises-list').innerHTML = '<div class="d-flex justify-content-center my-4"><div class="loader"></div></div>';
	document.getElementById('results-count').textContent = "Chargement...";
	
	// Effectuer la requête API
	fetch(`${API_BASE_URL}/autour?${params.toString()}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Erreur réseau: ' + response.status);
			}
			return response.json();
		})
		.then(data => {
			// Afficher le nombre de résultats
			const count = data.pagination.total;
			document.getElementById('results-count').textContent = `${count} entreprise${count > 1 ? 's' : ''} trouvée${count > 1 ? 's' : ''}`;
			
			// Vider les marqueurs existants
			markerClusterGroup.clearLayers();
			currentMarkers = [];
			
			// Vider la liste des entreprises
			document.getElementById('entreprises-list').innerHTML = '';
			
			// Pas de résultats
			if (count === 0) {
				document.getElementById('entreprises-list').innerHTML = '<div class="alert alert-info">Aucune entreprise trouvée dans ce rayon.</div>';
				return;
			}
			
			// Ajouter les marqueurs et les entreprises à la liste
			data.data.forEach(entreprise => {
				addEntrepriseToMap(entreprise);
				addEntrepriseToList(entreprise);
			});
		})
		.catch(error => {
			console.error('Erreur lors du chargement des entreprises:', error);
			document.getElementById('results-count').textContent = "Erreur de chargement";
			document.getElementById('entreprises-list').innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des données: ${error.message}</div>`;
		});
}

// Ajouter une entreprise à la carte
function addEntrepriseToMap(entreprise) {
	if (entreprise.latitude && entreprise.longitude) {
		const entrepriseIcon = L.icon({
            iconUrl: '/assets/png/marker.png',
            iconSize: [25, 41], // Taille de l'icône (à ajuster selon votre image)
            iconAnchor: [12, 41], // Point d'ancrage de l'icône (généralement en bas au milieu)
            popupAnchor: [0, -41] // Point d'ancrage du popup (généralement au-dessus de l'icône)
        });

        const marker = L.marker([entreprise.latitude, entreprise.longitude], {
            icon: entrepriseIcon // Utilisez l'icône personnalisée ici
        }).bindPopup(`
            <strong>${entreprise.nom}</strong><br>
            ${entreprise.adresse || ''}<br>
            ${entreprise.code_postal || ''} ${entreprise.ville || ''}<br>
            <a href="#" class="voir-details" data-id="${entreprise.id}">Voir les détails</a>
        `);

		marker.on('click', function() {
			selectEntreprise(entreprise.id);
		});
		
		// Ajouter un événement au lien dans la popup
		marker.on('popupopen', function() {
			const link = document.querySelector('.voir-details[data-id="' + entreprise.id + '"]');
			if (link) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					selectEntreprise(entreprise.id);
				});
			}
		});
		
		markerClusterGroup.addLayer(marker);
		currentMarkers.push({
			id: entreprise.id,
			marker: marker
		});
	}
}

// Ajouter une entreprise à la liste
function addEntrepriseToList(entreprise) {
	const cardElement = document.createElement('div');
	cardElement.className = 'card entreprise-card';
	cardElement.setAttribute('data-id', entreprise.id);
	cardElement.innerHTML = `
		<div class="card-body">
			<h5 class="card-title">${entreprise.nom}</h5>
			<p class="card-text">
				<i class="fas fa-map-marker-alt text-danger me-1"></i>
				${entreprise.adresse || ''}, ${entreprise.code_postal || ''} ${entreprise.ville || ''}
			</p>
			<p class="card-text">
				<small class="text-muted">
					Distance: ${Math.round(entreprise.distance * 10) / 10} km
				</small>
			</p>
			<button class="btn btn-sm btn-outline-primary voir-details" data-id="${entreprise.id}">
				<i class="fas fa-info-circle me-1"></i>Détails
			</button>
		</div>
	`;
	
	// Ajouter l'événement de clic
	cardElement.querySelector('.voir-details').addEventListener('click', function(e) {
		e.preventDefault();
		selectEntreprise(entreprise.id);
	});
	
	document.getElementById('entreprises-list').appendChild(cardElement);
}

// Sélectionner une entreprise
function selectEntreprise(id) {
	// Désélectionner l'entreprise précédente s'il y en a une
	if (selectedEntrepriseId) {
		document.querySelector(`.entreprise-card[data-id="${selectedEntrepriseId}"]`)?.classList.remove('border-primary');
	}
	
	selectedEntrepriseId = id;
	
	// Surligner l'entreprise dans la liste
	const entrepriseElement = document.querySelector(`.entreprise-card[data-id="${id}"]`);
	if (entrepriseElement) {
		entrepriseElement.classList.add('border-primary');
		entrepriseElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
	
	// Centrer sur le marqueur
	const markerInfo = currentMarkers.find(m => m.id == id);
	if (markerInfo) {
		map.setView(markerInfo.marker.getLatLng(), 16);
		markerInfo.marker.openPopup();
	}
	
	// Charger les détails de l'entreprise
	loadEntrepriseDetails(id);
}

// Charger les détails d'une entreprise
function loadEntrepriseDetails(id) {
	document.getElementById('selected-entreprise-details').classList.remove('d-none');
	document.getElementById('selected-entreprise-content').innerHTML = '<div class="d-flex justify-content-center my-4"><div class="loader"></div></div>';
	
	fetch(`${API_BASE_URL}/entreprises/${id}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Erreur réseau: ' + response.status);
			}
			return response.json();
		})
		.then(entreprise => {
			// Mettre à jour le titre
			document.getElementById('selected-entreprise-title').textContent = entreprise.nom;
			
			// Construire le contenu HTML
			let html = `
				<div class="row">
					<div class="col-md-6">
						<h5><i class="fas fa-info-circle me-2"></i>Informations générales</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.siret ? `<li class="list-group-item"><strong>SIRET :</strong> ${entreprise.siret}</li>` : ''}
							${entreprise.raison_sociale ? `<li class="list-group-item"><strong>Raison sociale :</strong> ${entreprise.raison_sociale}</li>` : ''}
							${entreprise.forme_juridique ? `<li class="list-group-item"><strong>Forme juridique :</strong> ${entreprise.forme_juridique}</li>` : ''}
							${entreprise.date_creation ? `<li class="list-group-item"><strong>Date de création :</strong> ${new Date(entreprise.date_creation).toLocaleDateString()}</li>` : ''}
							${entreprise.capital ? `<li class="list-group-item"><strong>Capital :</strong> ${entreprise.capital.toLocaleString()} €</li>` : ''}
						</ul>
						
						<h5><i class="fas fa-user-tie me-2"></i>Dirigeants</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.dirigeants && entreprise.dirigeants.length > 0 
								? entreprise.dirigeants.map(d => `
									<li class="list-group-item">
										<strong>${d.fonction || 'Dirigeant'} :</strong> ${d.prenom || ''} ${d.nom || ''}
										${d.date_debut_fonction ? `<br><small class="text-muted">Depuis le ${new Date(d.date_debut_fonction).toLocaleDateString()}</small>` : ''}
									</li>
								`).join('')
								: '<li class="list-group-item">Aucun dirigeant connu</li>'
							}
						</ul>
						
						<h5><i class="fas fa-tag me-2"></i>Activités</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.activites && entreprise.activites.length > 0 
								? entreprise.activites.map(a => `<li class="list-group-item">${a.libelle}</li>`).join('')
								: '<li class="list-group-item">Aucune activité spécifiée</li>'
							}
						</ul>
					</div>
					
					<div class="col-md-6">
						<h5><i class="fas fa-map-marker-alt me-2"></i>Adresses</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.adresses && entreprise.adresses.length > 0 
								? entreprise.adresses.map(a => `
									<li class="list-group-item">
										<strong>${a.type === 'siege' ? 'Siège social' : a.type === 'etablissement' ? 'Établissement' : 'Adresse'} :</strong><br>
										${a.adresse || ''},
										${a.complement ? a.complement + ',' : ''}
										${a.code_postal || ''} ${a.ville || ''},
										${a.pays || 'France'}
									</li>
								`).join('')
								: '<li class="list-group-item">Aucune adresse connue</li>'
							}
						</ul>
						
						<h5><i class="fas fa-phone me-2"></i>Contacts</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.contacts && entreprise.contacts.length > 0 
								? entreprise.contacts.map(c => {
									let icon = '';
									let label = '';
									
									switch(c.type) {
										case 'telephone':
											icon = 'fas fa-phone';
											label = 'Téléphone';
											break;
										case 'email':
											icon = 'fas fa-envelope';
											label = 'Email';
											break;
										case 'fax':
											icon = 'fas fa-fax';
											label = 'Fax';
											break;
										case 'mobile':
											icon = 'fas fa-mobile-alt';
											label = 'Mobile';
											break;
										default:
											icon = 'fas fa-address-card';
											label = 'Contact';
									}
									
									return `
										<li class="list-group-item">
											<i class="${icon} me-2"></i>
											<strong>${label} :</strong> 
											${c.type === 'email' 
												? `<a href="mailto:${c.valeur}">${c.valeur}</a>` 
												: (c.type === 'telephone' || c.type === 'mobile' || c.type === 'fax')
													? `<a href="tel:${c.valeur.replace(/\s/g, '')}">${c.valeur}</a>`
													: c.valeur
											}
											${c.description ? `<br><small class="text-muted">${c.description}</small>` : ''}
										</li>
									`;
								}).join('')
								: '<li class="list-group-item">Aucun contact connu</li>'
							}
						</ul>
						
						<h5><i class="fas fa-globe me-2"></i>Présence en ligne</h5>
						<ul class="list-group list-group-flush mb-3">
							${entreprise.sites_web && entreprise.sites_web.length > 0 
								? entreprise.sites_web.map(s => `
									<li class="list-group-item">
										<i class="fas fa-link me-2"></i>
										<strong>${s.type === 'site_officiel' ? 'Site officiel' : 
												  s.type === 'e_commerce' ? 'E-commerce' : 
												  s.type === 'blog' ? 'Blog' : 
												  s.type === 'reseau_social' ? 'Réseau social' : 
												  'Site web'} :</strong>
										<a href="${s.url}" target="_blank">${s.url}</a>
									</li>
								`).join('')
								: '<li class="list-group-item">Aucun site web connu</li>'
							}
						</ul>
					</div>
				</div>
				
				<div class="text-end mt-3">
					<button class="btn btn-secondary" id="close-details">
						<i class="fas fa-times me-1"></i>Fermer
					</button>
				</div>
			`;
			
			document.getElementById('selected-entreprise-content').innerHTML = html;
			
			// Ajouter un gestionnaire d'événements pour le bouton de fermeture
			document.getElementById('close-details').addEventListener('click', function() {
				hideEntrepriseDetails();
			});
		})
		.catch(error => {
			console.error('Erreur lors du chargement des détails:', error);
			document.getElementById('selected-entreprise-content').innerHTML = `
				<div class="alert alert-danger">
					Erreur lors du chargement des détails de l'entreprise: ${error.message}
				</div>
			`;
		});
}

// Masquer les détails de l'entreprise
function hideEntrepriseDetails() {
	document.getElementById('selected-entreprise-details').classList.add('d-none');
	document.querySelector(`.entreprise-card[data-id="${selectedEntrepriseId}"]`)?.classList.remove('border-primary');
	selectedEntrepriseId = null;
}

// Fonction de recherche
function setupSearch() {
	const searchInput = document.getElementById('search-input');
	const searchResults = document.getElementById('search-results');
	
	searchInput.addEventListener('input', function() {
		const query = this.value.trim();
		
		if (query.length < 2) {
			searchResults.style.display = 'none';
			return;
		}
		
		// Effectuer la recherche
		fetch(`${API_BASE_URL}/recherche?q=${encodeURIComponent(query)}`)
			.then(response => response.json())
			.then(data => {
				if (data.data.length === 0) {
					searchResults.innerHTML = '<div class="search-result-item">Aucun résultat trouvé</div>';
				} else {
					searchResults.innerHTML = '';
					data.data.slice(0, 10).forEach(result => {
						const resultItem = document.createElement('div');
						resultItem.className = 'search-result-item';
						resultItem.setAttribute('data-id', result.id);
						resultItem.innerHTML = `
							<strong>${result.nom}</strong>
							${result.ville ? `<br>${result.adresse}, ${result.code_postal} ${result.ville}` : ''}
						`;
						
						resultItem.addEventListener('click', function() {
							// Déplacer la carte vers cette entreprise
							if (result.latitude && result.longitude) {
								map.setView([result.latitude, result.longitude], 16);
							}
							
							// Sélectionner l'entreprise
							selectEntreprise(result.id);
							
							// Masquer les résultats de recherche
							searchResults.style.display = 'none';
							searchInput.value = result.nom;
						});
						
						searchResults.appendChild(resultItem);
					});
				}
				
				searchResults.style.display = 'block';
			})
			.catch(error => {
				console.error('Erreur de recherche:', error);
				searchResults.innerHTML = '<div class="search-result-item">Erreur lors de la recherche</div>';
				searchResults.style.display = 'block';
			});
	});
	
	// Cacher les résultats lorsqu'on clique ailleurs
	document.addEventListener('click', function(e) {
		if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
			searchResults.style.display = 'none';
		}
	});
}

// Charger les activités disponibles
function loadActivites() {
	const activitesContainer = document.getElementById('activites-container');
	
	fetch(`${API_BASE_URL}/activites`)
		.then(response => response.json())
		.then(data => {
			activitesContainer.innerHTML = '';
			
			// Ajouter l'option "Toutes les activités"
			const allOption = document.createElement('div');
			allOption.className = 'form-check';
			allOption.innerHTML = `
				<input class="form-check-input" type="radio" name="activite" id="activite-all" value="" checked>
				<label class="form-check-label" for="activite-all">
					Toutes les activités
				</label>
			`;
			activitesContainer.appendChild(allOption);
			
			// Ajouter les activités disponibles
			data.data.forEach(activite => {
				const activiteOption = document.createElement('div');
				activiteOption.className = 'form-check';
				activiteOption.innerHTML = `
					<input class="form-check-input" type="radio" name="activite" id="activite-${activite.id}" value="${activite.id}">
					<label class="form-check-label" for="activite-${activite.id}">
						${activite.libelle}
					</label>
				`;
				activitesContainer.appendChild(activiteOption);
			});
		})
		.catch(error => {
			console.error('Erreur lors du chargement des activités:', error);
			activitesContainer.innerHTML = '<div class="text-danger">Erreur lors du chargement des activités</div>';
		});
}

// Configurer les filtres
function setupFilters() {
	const rayonInput = document.getElementById('rayon');
	const rayonValue = document.getElementById('rayon-value');
	const filtersForm = document.getElementById('filters-form');
   
	// Valeurs par défaut pour des pas plus adaptés aux grandes distances
	const breakpoints =
	[
		{ value: 50, step: 1 },	 // De 1 à 50 km: pas de 1
		{ value: 200, step: 5 },	// De 51 à 200 km: pas de 5
		{ value: 500, step: 25 },   // De 201 à 500 km: pas de 25
		{ value: 2000, step: 100 }  // De 501 à 2000 km: pas de 100
	];

	// Mettre à jour l'affichage du rayon et ajuster le pas
	rayonInput.addEventListener('input', function() {
		rayonValue.textContent = this.value;

		// Ajuster le pas en fonction de la valeur
        for (const bp of breakpoints) {
            if (parseInt(this.value) <= bp.value) {
                this.step = bp.step;
                break;
            }
        }
	});
	
	// Soumettre les filtres
	filtersForm.addEventListener('submit', function(e) {
		e.preventDefault();
		
		const rayon = rayonInput.value;
		const activiteId = document.querySelector('input[name="activite"]:checked')?.value || null;
		
		if (currentPosition) {
			loadEntreprisesAutour(currentPosition.lat, currentPosition.lng, {
				rayon: rayon,
				activiteId: activiteId
			});
		} else {
			alert("Veuillez d'abord vous localiser pour appliquer les filtres.");
		}
	});
}

// Configurer le bouton de localisation
function setupLocateMe() {
	document.getElementById('locate-me').addEventListener('click', function() {
		getUserLocation();
	});
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
	initMap();
	setupSearch();
	loadActivites();
	setupFilters();
	setupLocateMe();
});

