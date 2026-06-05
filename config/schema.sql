-- ================================================================
-- SCHÉMA BASE DE DONNÉES — Fête de la Jeunesse 11 Février
-- ================================================================
-- Exécutez ce fichier dans phpMyAdmin ou en ligne de commande :
--   mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS fete_jeunesse
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE fete_jeunesse;

-- ── Table des inscriptions ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS inscriptions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prenom      VARCHAR(80)  NOT NULL,
    nom         VARCHAR(80)  NOT NULL,
    age         TINYINT UNSIGNED NOT NULL,
    email       VARCHAR(150) NOT NULL,
    region      VARCHAR(60)  NOT NULL,
    activite    VARCHAR(100) NOT NULL,
    message     TEXT,
    statut      ENUM('en_attente','confirmé','annulé') DEFAULT 'en_attente',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_region   (region),
    INDEX idx_activite (activite),
    INDEX idx_statut   (statut),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Table admin ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,   -- bcrypt hash
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Table activités (référence) ───────────────────────────────
CREATE TABLE IF NOT EXISTS activites (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    categorie   ENUM('sport','culture','art','education','social') NOT NULL,
    description TEXT,
    lieu        VARCHAR(100),
    heure_debut TIME,
    heure_fin   TIME,
    places_max  SMALLINT UNSIGNED DEFAULT 100,
    active      TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Données initiales : activités ─────────────────────────────
INSERT INTO activites (nom, categorie, description, lieu, heure_debut, heure_fin, places_max) VALUES
('Tournoi de Football',        'sport',     'Compétitions inter-lycées et universités',          'Stade Municipal',        '09:00:00', '17:00:00', 200),
('Course & Athlétisme',        'sport',     '100m, 400m, relais, saut en longueur et hauteur',   'Stade Municipal',        '07:30:00', '12:00:00', 150),
('Basketball & Handball',      'sport',     'Tournois mixtes et séparés',                        'Gymnase Central',        '09:00:00', '17:00:00', 120),
('Défilé Culturel',            'culture',   'Représentation des tenues traditionnelles',         'Place Centrale',         '09:00:00', '11:00:00', 300),
('Musique Traditionnelle',     'culture',   'Concerts de balafons, tam-tams et chants',          'Scène Principale',       '10:00:00', '18:00:00', 500),
('Gastronomie Régionale',      'culture',   'Village gastronomique national',                    'Village Gourmet',        '12:00:00', '20:00:00', 999),
('Exposition & Concours Dessin','art',      'Peinture, sculpture, photographie, art numérique',  'Galerie Jeunesse',       '09:00:00', '17:00:00', 80),
('Concours de Danse',          'art',       'Afrobeat, bikutsi, makossa, hip-hop',               'Grande Scène',           '14:00:00', '18:00:00', 100),
('Slam & Poésie',              'art',       'Spoken word et joutes poétiques',                   'Amphithéâtre',           '15:00:00', '17:30:00', 60),
('Olympiades Scolaires',       'education', 'Maths, sciences, langues et lettres',               'Salle des Concours',     '08:00:00', '14:00:00', 200),
('Concours d''Innovation',     'education', 'Start-ups, prototypes tech et projets écolo',       'Espace Tech',            '09:00:00', '17:00:00', 50),
('Forum des Jeunes Leaders',   'social',    'Débats, conférences, ateliers leadership',          'Centre de Conférences',  '10:00:00', '16:00:00', 150),
('Opération Environnement',    'social',    'Plantation d''arbres, nettoyage collectif',         'Espace Vert',            '07:00:00', '10:00:00', 200);

-- ── Admin par défaut ───────────────────────────────────────────
-- Identifiants : admin / Admin2026!
-- Hash bcrypt généré (compatible PHP $2y$)
INSERT INTO admins (username, password) VALUES
('admin', '$2b$12$lUqUVNssE2QBcnx6LKVP.uKv/BHOWLzG8I1oqt1qqSqyDoATlmgL2');

-- ⚠️  Pour changer le mot de passe, exécutez dans un terminal :
--   php -r "echo password_hash('VotreNouveauMotDePasse', PASSWORD_BCRYPT);"
-- Puis : UPDATE admins SET password='NOUVEAU_HASH' WHERE username='admin';

-- ── Vue utile : stats par activité ────────────────────────────
CREATE OR REPLACE VIEW v_stats_activites AS
SELECT
    a.nom          AS activite,
    a.categorie,
    a.places_max,
    COUNT(i.id)    AS inscrits,
    a.places_max - COUNT(i.id) AS places_restantes
FROM activites a
LEFT JOIN inscriptions i ON i.activite = a.nom AND i.statut != 'annulé'
GROUP BY a.id;
