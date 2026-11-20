<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ApiTest extends WebTestCase
{
    private const SPECTATEUR = ['email' => 'spectateur@grandcinema.fr', 'motDePasse' => 'Spectateur2026!'];
    private const ADMINISTRATEUR = ['email' => 'admin@grandcinema.fr', 'motDePasse' => 'Admin2026!'];

    private const IMAGE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private KernelBrowser $navigateur;

    protected function setUp(): void
    {
        $this->navigateur = static::createClient([], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    // La programmation se consulte sans connexion.
    public function testProgrammationPublique(): void
    {
        $films = $this->appeler('GET', '/api/films');

        $this->assertResponseIsSuccessful();
        $this->assertCount(6, $films);
        $this->assertNotEmpty($this->appeler('GET', "/api/films/{$films[0]['id']}/seances"));
    }

    // L'application donne son numéro de version.
    public function testVersion(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->appeler('GET', '/api/version')['version']);
    }

    // La réservation exige une connexion.
    public function testConnexionRequise(): void
    {
        $this->appeler('POST', '/api/reservations', ['seanceId' => 1, 'placeIds' => [1]]);

        $this->assertResponseStatusCodeSame(401);
    }

    // Un mauvais mot de passe est refusé.
    public function testMauvaisMotDePasse(): void
    {
        $this->appeler('POST', '/api/connexion', ['motDePasse' => 'mauvais'] + self::SPECTATEUR);

        $this->assertResponseStatusCodeSame(401);
    }

    // La connexion est bloquée après 5 échecs.
    public function testBlocageApresCinqEchecs(): void
    {
        for ($tentative = 1; $tentative <= 5; $tentative++)
        {
            $this->appeler('POST', '/api/connexion', ['motDePasse' => 'mauvais'] + self::SPECTATEUR);
        }
        $this->appeler('POST', '/api/connexion', self::SPECTATEUR);

        $this->assertResponseStatusCodeSame(401);
    }

    // Une requête venant d'un autre site est refusée.
    public function testRequeteAutreSite(): void
    {
        $this->navigateur->setServerParameter('HTTP_X_REQUESTED_WITH', '');
        $this->appeler('POST', '/api/connexion', self::SPECTATEUR);
        $this->assertResponseStatusCodeSame(403);

        $this->appeler('GET', '/api/films');
        $this->assertResponseIsSuccessful();
    }

    // Les réponses portent les en-têtes de sécurité.
    public function testEnTetesSecurite(): void
    {
        $this->appeler('GET', '/api/films');

        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
        $this->assertResponseHasHeader('Content-Security-Policy');
    }

    // L'administration est interdite au spectateur.
    public function testAdministrationInterdite(): void
    {
        $this->appeler('POST', '/api/connexion', self::SPECTATEUR);
        $this->appeler('GET', '/api/admin/films');

        $this->assertResponseStatusCodeSame(403);
    }

    // Une inscription invalide est refusée.
    public function testInscriptionInvalide(): void
    {
        $this->appeler('POST', '/api/inscription', ['email' => 'pas-un-email', 'motDePasse' => 'court']);

        $this->assertResponseStatusCodeSame(400);
    }

    // Un compte ne peut pas être créé deux fois avec le même e-mail.
    public function testCompteEnDouble(): void
    {
        $compte = ['email' => uniqid('test') . '@exemple.fr', 'motDePasse' => 'motdepasse'];
        $this->appeler('POST', '/api/inscription', $compte);
        $this->assertResponseStatusCodeSame(201);

        $this->appeler('POST', '/api/inscription', $compte);
        $this->assertResponseStatusCodeSame(409);
    }

    // Parcours complet : de la réservation au contrôle du billet.
    public function testParcoursComplet(): void
    {
        $compte = $this->appeler('POST', '/api/connexion', self::SPECTATEUR);
        $this->assertFalse($compte['administrateur']);

        $seance = $this->appeler('GET', '/api/films/1/seances')[1];
        $placesLibres = array_values(array_filter($this->appeler('GET', "/api/seances/{$seance['id']}/places"), fn (array $place) => !$place['occupee']));
        $reservation = $this->appeler('POST', '/api/reservations', ['seanceId' => $seance['id'], 'placeIds' => [$placesLibres[0]['id']]]);
        $this->assertResponseStatusCodeSame(201);

        $billet = $this->appeler('POST', "/api/reservations/{$reservation['id']}/paiement", ['numeroCarte' => '4242424242424242']);
        $this->assertSame($billet, $this->appeler('GET', "/api/billets/{$billet['code']}"));

        $this->appeler('POST', '/api/connexion', self::ADMINISTRATEUR);
        $this->assertNotEmpty($this->appeler('GET', "/api/admin/reservations?seanceId={$seance['id']}"));
        $this->appeler('POST', "/api/admin/billets/{$billet['code']}/validation", ['seanceId' => $seance['id']]);
        $this->assertResponseIsSuccessful();

        $this->appeler('POST', "/api/admin/reservations/{$reservation['id']}/annulation");
        $this->assertResponseStatusCodeSame(204);
    }

    // L'administrateur gère les films et les séances.
    public function testGestionProgrammation(): void
    {
        $this->appeler('POST', '/api/connexion', self::ADMINISTRATEUR);
        $film = ['titre' => 'Film de test API', 'dureeMinutes' => 60, 'genre' => 'Drame', 'affiche' => 'https://exemple.fr/affiche.jpg'];

        $filmId = $this->appeler('POST', '/api/admin/films', $film)['id'];
        $this->assertContains('Film de test API', array_column($this->appeler('GET', '/api/admin/films'), 'titre'));
        $this->appeler('PUT', "/api/admin/films/$filmId", ['genre' => 'Comédie'] + $film);
        $this->assertCount(10, $this->appeler('GET', '/api/admin/salles'));
        $debut = date('Y-m-d\T08:00', strtotime('+40 days'));
        $this->appeler('POST', '/api/admin/seances', ['filmId' => $filmId, 'salleId' => 9, 'debut' => $debut, 'tarif' => 9]);
        $this->assertResponseStatusCodeSame(201);

        $this->appeler('DELETE', "/api/admin/films/$filmId");
        $this->assertResponseStatusCodeSame(204);
    }

    // Une affiche est acceptée si c'est une image.
    public function testEnvoiAffiche(): void
    {
        $this->appeler('POST', '/api/connexion', self::ADMINISTRATEUR);
        $image = tempnam(sys_get_temp_dir(), 'affiche');
        file_put_contents($image, base64_decode(self::IMAGE_PNG));
        $texte = tempnam(sys_get_temp_dir(), 'affiche');
        file_put_contents($texte, 'Ceci n\'est pas une image.');

        $this->navigateur->request('POST', '/api/admin/affiches', [], ['affiche' => new UploadedFile($image, 'affiche.png', null, null, true)]);
        $this->assertResponseStatusCodeSame(201);
        $affiche = json_decode($this->navigateur->getResponse()->getContent(), true)['affiche'];
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/' . $affiche);
        unlink(dirname(__DIR__, 2) . '/public/' . $affiche);

        $this->navigateur->request('POST', '/api/admin/affiches', [], ['affiche' => new UploadedFile($texte, 'affiche.png', null, null, true)]);
        $this->assertResponseStatusCodeSame(400);
    }

    // Les statistiques s'exportent en CSV.
    public function testExportCsv(): void
    {
        $this->appeler('POST', '/api/connexion', self::ADMINISTRATEUR);

        $this->assertNotEmpty($this->appeler('GET', '/api/admin/statistiques/remplissage'));
        $this->assertNotEmpty($this->appeler('GET', '/api/admin/statistiques/affluence'));
        $this->navigateur->request('GET', '/api/admin/statistiques/remplissage?regroupement=salle&format=csv');
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringStartsWith('salle;capacite;places_vendues;taux', $this->navigateur->getResponse()->getContent());
    }

    private function appeler(string $methode, string $chemin, array $donnees = []): mixed
    {
        $this->navigateur->jsonRequest($methode, $chemin, $donnees);

        return json_decode($this->navigateur->getResponse()->getContent(), true);
    }
}
