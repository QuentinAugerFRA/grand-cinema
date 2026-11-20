-- Le Grand Cinéma : structure de la base de données (MySQL / MariaDB)

DROP TABLE IF EXISTS reservation_place, reservation, seance, place, salle, film, utilisateur;

CREATE TABLE utilisateur
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(180) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role         ENUM('ROLE_USER', 'ROLE_ADMIN') NOT NULL DEFAULT 'ROLE_USER'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE film
(
    id            INT AUTO_INCREMENT PRIMARY KEY,
    titre         VARCHAR(150) NOT NULL,
    duree_minutes SMALLINT     NOT NULL,
    genre         VARCHAR(50)  NOT NULL,
    affiche       VARCHAR(255) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE salle
(
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(50) NOT NULL,
    type              VARCHAR(40) NOT NULL,
    nombre_rangees    TINYINT     NOT NULL,
    places_par_rangee TINYINT     NOT NULL,
    nombre_blocs      TINYINT     NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE place
(
    id       INT AUTO_INCREMENT PRIMARY KEY,
    salle_id INT     NOT NULL,
    rangee   CHAR(1) NOT NULL,
    numero   TINYINT NOT NULL,
    UNIQUE (salle_id, rangee, numero),
    FOREIGN KEY (salle_id) REFERENCES salle (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE seance
(
    id       INT AUTO_INCREMENT PRIMARY KEY,
    film_id  INT           NOT NULL,
    salle_id INT           NOT NULL,
    debut    DATETIME      NOT NULL,
    fin      DATETIME      NOT NULL,
    tarif    DECIMAL(5, 2) NOT NULL,
    INDEX (debut),
    FOREIGN KEY (film_id) REFERENCES film (id) ON DELETE CASCADE,
    FOREIGN KEY (salle_id) REFERENCES salle (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE reservation
(
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT           NOT NULL,
    seance_id      INT           NOT NULL,
    statut         ENUM('en_attente', 'payee', 'annulee') NOT NULL DEFAULT 'en_attente',
    montant        DECIMAL(6, 2) NOT NULL,
    code           CHAR(32)      NULL UNIQUE,
    cree_le        DATETIME      NOT NULL,
    valide_le      DATETIME      NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id),
    FOREIGN KEY (seance_id) REFERENCES seance (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- La clé primaire (seance_id, place_id) interdit de vendre deux fois la même place.
CREATE TABLE reservation_place
(
    reservation_id INT NOT NULL,
    seance_id      INT NOT NULL,
    place_id       INT NOT NULL,
    PRIMARY KEY (seance_id, place_id),
    FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seance (id),
    FOREIGN KEY (place_id) REFERENCES place (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
