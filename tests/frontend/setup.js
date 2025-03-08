// Configuration de l'environnement de test pour Jest
require('@testing-library/jest-dom');

// Mock pour navigator.geolocation
const mockGeolocation = {
  getCurrentPosition: jest.fn().mockImplementation(success => 
    success({
      coords: {
        latitude: 48.8566,
        longitude: 2.3522
      }
    })
  ),
  watchPosition: jest.fn()
};

global.navigator.geolocation = mockGeolocation;

// Mock pour les fonctions que Jest ne peut pas trouver dans JSDOM
global.L = require('leaflet');
window.map = jest.fn();
window.markerClusterGroup = jest.fn();
