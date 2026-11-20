# Journal des versions

Les versions suivent la gestion sémantique de version : MAJEUR.MINEUR.CORRECTIF.

- MAJEUR : changement incompatible de l'API.
- MINEUR : nouvelle fonctionnalité, compatible avec l'existant.
- CORRECTIF : correction d'anomalie.

## 1.0.0 – 17/11/2026

Première version : le MVP et les deux user stories d'évolution.

- Consultation des films et des séances, par semaine.
- Plan de salle en temps réel, selon le type de salle.
- Réservation de 1 à 10 places, paiement simulé, billet avec QR code.
- Back-office : films, séances, réservations, contrôle des billets, statistiques.
- Sécurité : contrôle d'accès par rôle, protection CSRF, blocage après 5 échecs de connexion, en-têtes de sécurité.
