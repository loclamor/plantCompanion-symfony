<?php

namespace App\Tests\Controller\Api;

use App\Entity\Bac;
use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Repository\BacSaisonRepository;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels du cycle de saison (Phase 4) : démarrage d'une nouvelle
 * saison avec clôture de l'active, recopie de la géométrie des bacs et
 * immutabilité des saisons passées.
 */
class SaisonCycleTest extends DatabaseTestCase
{
    private function json(string $method, string $uri, ?array $payload = null): array
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null !== $payload ? json_encode($payload) : null,
        );
        $content = $this->client->getResponse()->getContent();

        return '' !== $content ? (json_decode($content, true) ?? []) : [];
    }

    private function makeSaison(Utilisateur $user, string $name = '2025', int $annee = 2025, string $statut = Saison::STATUT_ACTIVE): Saison
    {
        $saison = (new Saison())
            ->setName($name)->setAnnee($annee)
            ->setDateDebut(new \DateTimeImmutable($annee.'-03-01'))
            ->setStatut($statut)->setUtilisateur($user);
        $this->em->persist($saison);
        $this->em->flush();

        return $saison;
    }

    private function makeBac(Utilisateur $user, string $name = 'Bac A', bool $archived = false): Bac
    {
        $bac = (new Bac())
            ->setName($name)
            ->setLargeurDefaut(120)->setLongueurDefaut(80)
            ->setLignesDefaut(4)->setColonnesDefaut(6)
            ->setArchived($archived)
            ->setUtilisateur($user);
        $this->em->persist($bac);
        $this->em->flush();

        return $bac;
    }

    private function bacSaisonRepo(): BacSaisonRepository
    {
        return static::getContainer()->get(BacSaisonRepository::class);
    }

    public function testNewCycleClosesPreviousAndSnapshotsGeometry(): void
    {
        $alice = $this->createUser('alice');
        $old = $this->makeSaison($alice, '2025', 2025, Saison::STATUT_ACTIVE);
        $bac = $this->makeBac($alice);
        $archived = $this->makeBac($alice, 'Vieux bac', true);

        // Snapshot existant pour 2025 avec découpage personnalisé.
        $bs2025 = (new BacSaison())
            ->setUtilisateur($alice)->setBac($bac)->setSaison($old)
            ->setLargeur(150)->setLongueur(90)->setPosX(3)->setPosY(2)
            ->setLignes(5)->setColonnes(7);
        $this->em->persist($bs2025);
        $this->em->flush();

        $this->client->loginUser($alice);

        $new = $this->json('POST', '/api/saisons/new-cycle', ['name' => 'Saison 2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('active', $new['statut']);

        // L'ancienne saison est clôturée.
        $reloaded = $this->json('GET', '/api/saisons/'.$old->getId());
        $this->assertSame('cloturee', $reloaded['statut']);

        // Géométrie recopiée depuis le dernier snapshot du bac actif (pas le bac archivé).
        $list = $this->json('GET', '/api/bac-saisons?saison='.$new['id']);
        $this->assertCount(1, $list['items']);
        $copied = $list['items'][0];
        $this->assertSame($bac->getId(), $copied['bac']['id']);
        $this->assertSame(150, $copied['largeur']);
        $this->assertSame(90, $copied['longueur']);
        $this->assertSame(3, $copied['posX']);
        $this->assertSame(5, $copied['lignes']);
        $this->assertSame(7, $copied['colonnes']);
    }

    public function testPastSeasonSnapshotsUnchanged(): void
    {
        $alice = $this->createUser('alice');
        $old = $this->makeSaison($alice, '2025', 2025, Saison::STATUT_ACTIVE);
        $bac = $this->makeBac($alice);
        $bs2025 = (new BacSaison())
            ->setUtilisateur($alice)->setBac($bac)->setSaison($old)
            ->setLargeur(150)->setLongueur(90)->setLignes(5)->setColonnes(7);
        $this->em->persist($bs2025);
        $this->em->flush();
        $oldBsId = $bs2025->getId();

        $this->client->loginUser($alice);
        $this->json('POST', '/api/saisons/new-cycle', ['name' => '2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);

        // Le snapshot de 2025 existe toujours à l'identique (immutabilité du passé).
        $this->em->clear();
        $still = $this->bacSaisonRepo()->find($oldBsId);
        $this->assertNotNull($still);
        $this->assertSame($old->getId(), $still->getSaison()->getId());
        $this->assertSame(150, $still->getLargeur());
    }

    public function testNewCycleFirstSeasonUsesBacDefaults(): void
    {
        $alice = $this->createUser('alice');
        $this->makeBac($alice); // aucun snapshot antérieur
        $this->client->loginUser($alice);

        $new = $this->json('POST', '/api/saisons/new-cycle', ['name' => '2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);
        $this->assertResponseStatusCodeSame(201);

        $list = $this->json('GET', '/api/bac-saisons?saison='.$new['id']);
        $this->assertCount(1, $list['items']);
        $this->assertSame(120, $list['items'][0]['largeur']);
        $this->assertSame(4, $list['items'][0]['lignes']);
    }

    public function testNewCycleValidatesPayload(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/saisons/new-cycle', ['name' => '', 'annee' => null, 'dateDebut' => null]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    private function makeCulture(Utilisateur $user, Saison $saison, BacSaison $bs, bool $perenne, string $statut, string $name = 'Plant'): Culture
    {
        $c = (new Culture())
            ->setUtilisateur($user)->setSaison($saison)->setBacSaison($bs)
            ->setName($name)->setPosX(2)->setPosY(1)->setLargeurCases(1)->setHauteurCases(1)
            ->setDatePlantation(new \DateTimeImmutable('2025-04-10'))
            ->setStatut($statut)->setPerenne($perenne);
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    public function testNewCycleReportsPerennesOnly(): void
    {
        $alice = $this->createUser('alice');
        $old = $this->makeSaison($alice, '2025', 2025, Saison::STATUT_ACTIVE);
        $bac = $this->makeBac($alice);
        $bs2025 = (new BacSaison())
            ->setUtilisateur($alice)->setBac($bac)->setSaison($old)
            ->setLargeur(120)->setLongueur(80)->setLignes(4)->setColonnes(6);
        $this->em->persist($bs2025);
        $this->em->flush();

        $perenne = $this->makeCulture($alice, $old, $bs2025, perenne: true, statut: Culture::STATUT_EN_PLACE, name: 'Fraisier');
        $this->makeCulture($alice, $old, $bs2025, perenne: false, statut: Culture::STATUT_EN_PLACE, name: 'Tomate');
        $this->makeCulture($alice, $old, $bs2025, perenne: true, statut: Culture::STATUT_RECOLTE, name: 'Vieux fraisier');
        $perenneId = $perenne->getId();

        $this->client->loginUser($alice);
        $new = $this->json('POST', '/api/saisons/new-cycle', ['name' => '2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);
        $this->assertResponseStatusCodeSame(201);

        // Seule la pérenne « en_place » est reportée.
        $list = $this->json('GET', '/api/cultures?saison='.$new['id']);
        $this->assertCount(1, $list['items']);
        $reported = $list['items'][0];
        $this->assertSame('Fraisier', $reported['name']);
        $this->assertTrue($reported['perenne']);
        $this->assertSame('en_place', $reported['statut']);
        $this->assertSame(2, $reported['posX']);
        $this->assertSame(1, $reported['posY']);
        // datePlantation d'origine conservée + lignage parentCulture.
        $this->assertSame('2025-04-10', $reported['datePlantation']);
        $this->assertSame($perenneId, $reported['parentCulture']['id']);
        // Rattachée au nouveau snapshot du même bac.
        $this->assertSame($bac->getId(), $reported['bacSaison']['bac']['id']);
    }

    public function testArchivedBacPerennesNotReported(): void
    {
        $alice = $this->createUser('alice');
        $old = $this->makeSaison($alice, '2025', 2025, Saison::STATUT_ACTIVE);
        $bac = $this->makeBac($alice, 'Bac archivé', archived: true);
        $bs2025 = (new BacSaison())
            ->setUtilisateur($alice)->setBac($bac)->setSaison($old)
            ->setLargeur(120)->setLongueur(80)->setLignes(4)->setColonnes(6);
        $this->em->persist($bs2025);
        $this->em->flush();
        $this->makeCulture($alice, $old, $bs2025, perenne: true, statut: Culture::STATUT_EN_PLACE, name: 'Asperge');

        $this->client->loginUser($alice);
        $new = $this->json('POST', '/api/saisons/new-cycle', ['name' => '2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);

        // Bac archivé → pas de snapshot recréé → pérenne non reportée.
        $list = $this->json('GET', '/api/cultures?saison='.$new['id']);
        $this->assertCount(0, $list['items']);
    }
}
