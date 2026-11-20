<?php

namespace App\Tests\Service;

use App\Service\Paiement;
use App\Service\Reservations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;

class ReservationsTest extends ServiceTestCase
{
    private const CARTE_ACCEPTEE = '4242424242424242';
    private const CARTE_REFUSEE = '4242424242420000';

    private Reservations $reservations;
    private MailerInterface&MockObject $messagerie;
    private int $seanceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messagerie = $this->createMock(MailerInterface::class);
        $this->reservations = new Reservations($this->base, new Paiement(), $this->messagerie);
        $this->seanceId = $this->seanceAVenir();
    }

    // Une réservation bloque ses places et calcule le montant.
    public function testBlocageDesPlaces(): void
    {
        $reservation = $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(3));

        $this->assertEquals(27, $reservation['montant']);
        $this->assertSame(3, $this->nombrePlacesOccupees());
    }

    // Une place déjà réservée est refusée (erreur 409).
    public function testPlaceDejaVendue(): void
    {
        $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(2));

        $this->expectException(ConflictHttpException::class);
        $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(1));
    }

    // Une place d'une autre salle est refusée.
    public function testPlaceAutreSalle(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(1, 2));
    }

    // Une séance inconnue est refusée.
    public function testSeanceInconnue(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->reservations->reserver(self::SPECTATEUR_ID, 0, $this->places(1));
    }

    // Un paiement accepté délivre le billet et l'envoie par e-mail.
    public function testPaiementAccepte(): void
    {
        $this->messagerie->expects($this->once())->method('send');
        $reservation = $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(2));

        $billet = $this->reservations->payer($reservation['id'], self::SPECTATEUR_ID, self::CARTE_ACCEPTEE);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $billet['code']);
        $this->assertSame('A1, A2', $billet['places']);
        $this->assertSame($billet, $this->reservations->billet($billet['code']));
    }

    // Un paiement refusé libère les places.
    public function testPaiementRefuse(): void
    {
        $reservation = $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(2));
        try
        {
            $this->reservations->payer($reservation['id'], self::SPECTATEUR_ID, self::CARTE_REFUSEE);
            $this->fail('Le paiement aurait dû être refusé.');
        }
        catch (HttpException $erreur)
        {
            $this->assertSame(402, $erreur->getStatusCode());
        }

        $this->assertSame(0, $this->nombrePlacesOccupees());
    }

    // Une réservation non payée expire après 10 minutes.
    public function testExpiration(): void
    {
        $reservation = $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(2));
        $this->base->ecrire('UPDATE reservation SET cree_le = NOW() - INTERVAL 11 MINUTE WHERE id = ?', [$reservation['id']]);

        $this->assertSame(0, $this->nombrePlacesOccupees());
        $this->expectException(NotFoundHttpException::class);
        $this->reservations->payer($reservation['id'], self::SPECTATEUR_ID, self::CARTE_ACCEPTEE);
    }

    // Un billet n'est validé qu'une seule fois.
    public function testBilletDejaValide(): void
    {
        $billet = $this->billetPaye();
        $this->assertSame($billet['code'], $this->reservations->validerBillet($billet['code'], $this->seanceId)['code']);

        $this->expectException(ConflictHttpException::class);
        $this->reservations->validerBillet($billet['code'], $this->seanceId);
    }

    // Un billet d'une autre séance est refusé.
    public function testBilletAutreSeance(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->reservations->validerBillet($this->billetPaye()['code'], $this->seanceAVenir(2));
    }

    // Un billet inconnu est refusé.
    public function testBilletInconnu(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->reservations->validerBillet('inconnu', $this->seanceId);
    }

    // L'annulation remet les places en vente et prévient le spectateur.
    public function testAnnulation(): void
    {
        $billet = $this->billetPaye();
        $this->messagerie->expects($this->once())->method('send');

        $this->reservations->annuler($billet['id']);

        $this->assertSame(0, $this->nombrePlacesOccupees());
        $this->assertSame('annulee', $this->reservations->lister($this->seanceId)[0]['statut']);
        $this->expectException(NotFoundHttpException::class);
        $this->reservations->annuler($billet['id']);
    }

    // Les réservations se recherchent par e-mail ou par numéro.
    public function testRecherche(): void
    {
        $billet = $this->billetPaye();

        $this->assertCount(1, $this->reservations->lister($this->seanceId, 'spectateur'));
        $this->assertCount(1, $this->reservations->lister($this->seanceId, (string) $billet['id']));
        $this->assertCount(0, $this->reservations->lister($this->seanceId, 'inconnu'));
    }

    private function billetPaye(): array
    {
        $reservation = $this->reservations->reserver(self::SPECTATEUR_ID, $this->seanceId, $this->places(2));

        return $this->reservations->payer($reservation['id'], self::SPECTATEUR_ID, self::CARTE_ACCEPTEE);
    }

    private function nombrePlacesOccupees(): int
    {
        return count(array_filter(array_column($this->reservations->plan($this->seanceId), 'occupee')));
    }
}
