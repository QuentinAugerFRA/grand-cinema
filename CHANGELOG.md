# Journal des versions

Les versions suivent la gestion sémantique de version : MAJEUR.MINEUR.CORRECTIF.

- MAJEUR : changement incompatible de l'API.
- MINEUR : nouvelle fonctionnalité, compatible avec l'existant.
- CORRECTIF : correction d'anomalie.

## 1.1.3 – 20/11/2026

- Plan de salle : la taille des places s'adapte à la hauteur de l'écran, salle centrée.

## 1.1.2 – 20/11/2026

- Interface : icônes alignées verticalement avec le texte.
- Interface : pied de page toujours en bas de l'écran, barre de défilement toujours visible, logo en dégradé.
- Accueil : genre et durée du film sur deux lignes.
- Back-office : onglets colorés au survol, logo bleu.
- Adresses sans extension : `/admin`, `/mentions-legales`, `/documentation`.

## 1.1.1 – 20/11/2026

- Interface : boutons éclaircis au survol.

## 1.1.0 – 20/11/2026

- Interface : dégradés légers, ombre sur les cartes des films.
- Back-office : formulaire de contrôle du billet centré.

## 1.0.0 – 17/11/2026

Première version : le MVP et les deux user stories d'évolution.

- Consultation des films et des séances, par semaine.
- Plan de salle en temps réel, selon le type de salle.
- Réservation de 1 à 10 places, paiement simulé, billet avec QR code.
- Back-office : films, séances, réservations, contrôle des billets, statistiques.
- Sécurité : contrôle d'accès par rôle, protection CSRF, blocage après 5 échecs de connexion, en-têtes de sécurité.
