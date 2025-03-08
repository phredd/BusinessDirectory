-- Création de la base de données
CREATE DATABASE IF NOT EXISTS annuaire_entreprises CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE annuaire_entreprises;

-- Table des entreprises
CREATE TABLE IF NOT EXISTS entreprises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siret VARCHAR(14) UNIQUE,
    nom VARCHAR(255) NOT NULL,
    raison_sociale VARCHAR(255),
    date_creation DATE,
    forme_juridique VARCHAR(100),
    capital DECIMAL(15,2),
    code_naf VARCHAR(10),
    tranche_effectif VARCHAR(50),
    date_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    source VARCHAR(50),
    source_id VARCHAR(100),
    INDEX idx_siret (siret),
    INDEX idx_nom (nom)
);

-- Table des dirigeants
CREATE TABLE IF NOT EXISTS dirigeants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    fonction VARCHAR(100),
    date_naissance DATE,
    date_debut_fonction DATE,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id) ON DELETE CASCADE,
    INDEX idx_entreprise (entreprise_id)
);

-- Table des adresses
CREATE TABLE IF NOT EXISTS adresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise_id INT NOT NULL,
    type ENUM('siege', 'etablissement', 'autre') DEFAULT 'autre',
    adresse VARCHAR(255) NOT NULL,
    complement VARCHAR(255),
    code_postal VARCHAR(10) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    pays VARCHAR(100) DEFAULT 'France',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id) ON DELETE CASCADE,
    INDEX idx_entreprise (entreprise_id),
    INDEX idx_geoloc (latitude, longitude)
);

-- Table des contacts
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise_id INT NOT NULL,
    type ENUM('telephone', 'email', 'fax', 'mobile', 'autre') NOT NULL,
    valeur VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id) ON DELETE CASCADE,
    INDEX idx_entreprise (entreprise_id),
    INDEX idx_type_valeur (type, valeur)
);

-- Table des sites web
CREATE TABLE IF NOT EXISTS sites_web (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entreprise_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    type ENUM('site_officiel', 'e_commerce', 'blog', 'reseau_social', 'autre') DEFAULT 'site_officiel',
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id) ON DELETE CASCADE,
    INDEX idx_entreprise (entreprise_id)
);

-- Table des activités
CREATE TABLE IF NOT EXISTS activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL,
    UNIQUE KEY (libelle)
);

-- Table de liaison entreprises-activités
CREATE TABLE IF NOT EXISTS entreprises_activites (
    entreprise_id INT NOT NULL,
    activite_id INT NOT NULL,
    PRIMARY KEY (entreprise_id, activite_id),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id) ON DELETE CASCADE,
    FOREIGN KEY (activite_id) REFERENCES activites(id) ON DELETE CASCADE
);

-- Table pour stocker les informations d'import
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME,
    nb_entreprises INT DEFAULT 0,
    statut ENUM('en_cours', 'termine', 'erreur') DEFAULT 'en_cours',
    message TEXT,
    INDEX idx_source_date (source, date_debut)
);

