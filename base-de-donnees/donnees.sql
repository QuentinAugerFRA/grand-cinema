-- Le Grand Cinéma : données de démonstration

SET NAMES utf8mb4;

-- Comptes : admin@grandcinema.fr / Admin2026!  et  spectateur@grandcinema.fr / Spectateur2026!
INSERT INTO utilisateur (email, mot_de_passe, role) VALUES
    ('admin@grandcinema.fr', '$2y$10$ZK4UDqiypnwvLG40YqfC0.78tyqwf6nEzPfi6HAlxndiLXyNLlKJK', 'ROLE_ADMIN'),
    ('spectateur@grandcinema.fr', '$2y$10$y880AL1KoAp3GV8/JKqe6.G0Rt9qDEPBEHMr9baHjqSuBVUfq3M4i', 'ROLE_USER');

-- 10 salles pour un total de 1 700 places, selon les quatre types de salle du cinéma.
-- nombre_blocs : nombre de blocs de fauteuils, séparés par des allées (2 ou 3).
INSERT INTO salle (nom, type, nombre_rangees, places_par_rangee, nombre_blocs) VALUES
    ('Salle 1', 'Premium / IMAX', 20, 15, 3),
    ('Salle 2', 'Premium / IMAX', 20, 15, 3),
    ('Salle 3', 'Standard', 12, 15, 3),
    ('Salle 4', 'Standard', 12, 15, 3),
    ('Salle 5', 'Standard', 12, 15, 3),
    ('Salle 6', 'Standard', 12, 15, 3),
    ('Salle 7', 'VIP', 10, 12, 2),
    ('Salle 8', 'VIP', 10, 12, 2),
    ('Salle 9', 'Événementielle / modulable', 7, 10, 2),
    ('Salle 10', 'Événementielle / modulable', 7, 10, 2);

-- Une place par rangée (A, B, C...) et par numéro, pour chaque salle
INSERT INTO place (salle_id, rangee, numero)
WITH RECURSIVE nombre (valeur) AS (SELECT 1 UNION ALL SELECT valeur + 1 FROM nombre WHERE valeur < 20)
SELECT salle.id, CHAR(64 + rangee.valeur), numero.valeur
FROM salle
JOIN nombre AS rangee ON rangee.valeur <= salle.nombre_rangees
JOIN nombre AS numero ON numero.valeur <= salle.places_par_rangee;

INSERT INTO film (titre, duree_minutes, genre, affiche) VALUES
    ('Les Nuits du Jazz', 112, 'Drame', 'https://picsum.photos/seed/jazz/300/450'),
    ('Le Dernier Phare', 98, 'Aventure', 'https://picsum.photos/seed/phare/300/450'),
    ('Minuit à l''Opéra', 125, 'Comédie', 'https://picsum.photos/seed/opera/300/450'),
    ('Orbite 9', 134, 'Science-fiction', 'https://picsum.photos/seed/orbite/300/450'),
    ('La Forêt des Lucioles', 87, 'Animation', 'https://picsum.photos/seed/lucioles/300/450'),
    ('Sous la Ville', 105, 'Thriller', 'https://picsum.photos/seed/ville/300/450');

-- Trois séances par jour et par film pendant 30 jours.
-- Le décalage fait tourner les films dans les salles, sans jamais en mettre deux dans la même.
INSERT INTO seance (film_id, salle_id, debut, fin, tarif)
WITH RECURSIVE jour (valeur) AS (SELECT 0 UNION ALL SELECT valeur + 1 FROM jour WHERE valeur < 29)
SELECT film.id,
       1 + (film.id - 1 + horaire.decalage) % 10,
       CURDATE() + INTERVAL jour.valeur DAY + INTERVAL horaire.minutes MINUTE,
       CURDATE() + INTERVAL jour.valeur DAY + INTERVAL horaire.minutes + film.duree_minutes MINUTE,
       9.00
FROM film
JOIN jour
JOIN (SELECT 840 AS minutes, 0 AS decalage UNION SELECT 1020, 3 UNION SELECT 1230, 6) AS horaire;
