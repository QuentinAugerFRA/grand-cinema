# Le Grand Cinéma – Réservation de places en ligne

Application web de réservation du Grand Cinéma : consultation de la programmation, choix des places sur le plan de salle, paiement, billet dématérialisé avec QR code, et back-office d'administration.

## Technologies

| Couche | Technologie |
|---|---|
| API | PHP 8.2+, Symfony 7.4 (version LTS) |
| Base de données | MySQL ou MariaDB, accès par PDO et requêtes préparées |
| Site Web et back-office | HTML, CSS, JavaScript, Material Icons |
| Qualité | PHPUnit (tests et couverture), PHPStan (analyse statique), GitHub Actions |

Le paiement et l'envoi des e-mails sont simulés : `src/Service/Paiement.php` remplace le prestataire de paiement, et `MAILER_DSN=null://null` désactive l'envoi réel.

## Structure du projet

```
base-de-donnees/   Script de création (schema.sql) et données de démonstration (donnees.sql)
config/            Configuration Symfony : services, routes, sécurité
public/            Site Web (index.html), back-office (admin.html), documentation de l'API
src/Controller/    Points d'entrée de l'API : ils lisent la requête et renvoient du JSON
src/Service/       Règles métier et requêtes SQL
src/Security/      Authentification des utilisateurs
tests/Unit/        Tests unitaires : une règle à la fois, sans base de données
tests/Service/     Tests d'intégration des services, avec la base de test
tests/Controller/  Tests d'intégration de l'API
```

## Installation en local

```bash
composer install
mysql -u root -e "CREATE DATABASE grand_cinema"
mysql -u root grand_cinema < base-de-donnees/schema.sql
mysql -u root grand_cinema < base-de-donnees/donnees.sql
php -S localhost:8000 -t public
```

Les identifiants de la base se règlent dans un fichier `.env.local`, qui n'est pas versionné.

| Page | Adresse |
|---|---|
| Site Web | http://localhost:8000/ |
| Back-office | http://localhost:8000/admin |
| Documentation de l'API | http://localhost:8000/documentation |

Chaque vue a sa propre adresse (`/film/3`, `/film/3/seance/45`, `/admin/seances`). Ces adresses sont servies par les règles de `public/.htaccess` : avec le serveur de développement `php -S`, la navigation fonctionne, mais le rechargement d'une de ces adresses demande Apache.

## Comptes de démonstration

| Rôle | Login | Mot de passe |
|---|---|---|
| Administrateur | admin@grandcinema.fr | Admin2026! |
| Spectateur | spectateur@grandcinema.fr | Spectateur2026! |

Carte de paiement acceptée : 16 chiffres, par exemple `4242424242424242`. Une carte qui se termine par `0000` est refusée.

## Tests et qualité

```bash
mysql -u root -e "CREATE DATABASE grand_cinema_test"
composer tests            # tous les tests et la couverture
composer tests-unitaires  # tests unitaires seuls
composer qualite          # analyse statique PHPStan
```

Les deux commandes sont lancées automatiquement par GitHub Actions à chaque push et à chaque pull request.

## Sécurité

| Risque | Protection | Emplacement |
|---|---|---|
| Injection SQL | Requêtes préparées avec PDO | `src/Service/BaseDeDonnees.php` |
| Vol de mots de passe | Hachage par le composant Security | `config/packages/security.yaml` |
| Accès non autorisé | Contrôle d'accès par rôle | `config/packages/security.yaml` |
| Force brute sur la connexion | Blocage 15 minutes après 5 échecs | `config/packages/security.yaml` |
| Falsification de requête (CSRF) | En-tête `X-Requested-With` exigé, cookie `SameSite` | `src/Security/ProtectionCsrf.php` |
| Script injecté (XSS) | Textes échappés, politique de sécurité du contenu (CSP) | `public/js/commun.js`, `public/.htaccess` |
| Détournement de clic | En-tête `X-Frame-Options` | `src/Security/EnTetesSecurite.php`, `public/.htaccess` |
| Vol de session | Cookie `HttpOnly` et `Secure`, HTTPS imposé (HSTS) | `config/packages/framework.yaml`, `public/.htaccess` |
| Fichier malveillant | Type et taille de l'affiche contrôlés, nom aléatoire | `src/Controller/Admin/ProgrammationController.php` |
| Fuite d'information | Erreurs internes sans détail technique, secrets hors du dépôt | `src/EventListener/ErreurApi.php`, `.env.local` |

Les en-têtes du fichier `.htaccess` demandent le module Apache `headers` : `sudo a2enmod headers`.

## Versions

Le numéro de version suit la gestion sémantique de version (MAJEUR.MINEUR.CORRECTIF). Il est défini dans `config/services.yaml`, affiché en pied de page et renvoyé par `GET /api/version`. Chaque version est décrite dans `CHANGELOG.md` et marquée par une étiquette Git sur `main` :

```bash
git tag v1.0.0
git push --tags
```

## Convention de nommage des branches

| Branche | Rôle |
|---|---|
| `main` | Version en production. Elle ne reçoit que des fusions depuis `develop`. |
| `develop` | Branche d'intégration. Elle reçoit les fonctionnalités terminées. |
| `feature/xxx` | Une branche par fonctionnalité (EPIC), créée depuis `develop`, par exemple `feature/reserver-places`. |
| `fix/xxx` | Correction d'une anomalie, par exemple `fix/double-reservation`. |

Règles :

- les noms sont en minuscules, sans accent, avec des tirets ;
- une branche `feature` est fusionnée dans `develop` par une pull request, une fois les tests au vert ;
- les messages de commit commencent par un verbe à l'infinitif : « Ajouter le plan de salle ».

## Déploiement sur un hébergement mutualisé

1. En local : `composer install --no-dev --optimize-autoloader`.
2. Créer la base dans l'espace client, puis importer `schema.sql` et `donnees.sql` avec phpMyAdmin.
3. Créer un fichier `.env.local` avec `APP_ENV=prod`, un `APP_SECRET` aléatoire et les identifiants de la base.
4. Envoyer tout le projet par FTP, dossier `vendor` compris.
5. Faire pointer le dossier racine du domaine sur le dossier `public`.
6. Donner au serveur web le droit d'écrire dans `var` et dans `public/affiches`.
