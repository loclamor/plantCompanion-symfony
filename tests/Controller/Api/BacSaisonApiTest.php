<?php

namespace App\Tests\Controller\Api;

use App\Entity\Bac;
use App\Entity\BacSaison;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels des snapshots de bacs par saison (Phase 4) : scope saison,
 * taille physique figée, découpage éditable, blocage saison clôturée.
 */
class BacSaisonApiTest extends DatabaseTestCase
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

    private function makeSaison(Utilisateur $user, string $name = '2026', int $annee = 2026, string $statut = Saison::STATUT_ACTIVE): Saison
    {
        $saison = (new Saison())
            ->setName($name)
            ->setAnnee($annee)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut($statut)
            ->setUtilisateur($user);
        $this->em->persist($saison);
        $this->em->flush();

        return $saison;
    }

    private function makeBac(Utilisateur $user, string $name = 'Bac A'): Bac
    {
        $bac = (new Bac())
            ->setName($name)
            ->setLargeurDefaut(120)
            ->setLongueurDefaut(80)
            ->setLignesDefaut(4)
            ->setColonnesDefaut(6)
            ->setUtilisateur($user);
        $this->em->persist($bac);
        $this->em->flush();

        return $bac;
    }

    public function testCreateScopedToSeasonUsesBacDefaults(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bac = $this->makeBac($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bac-saisons', ['bac' => $bac->getId(), 'saison' => $saison->getId()]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(120, $created['largeur']);
        $this->assertSame(80, $created['longueur']);
        $this->assertSame(4, $created['lignes']);
        $this->assertSame(6, $created['colonnes']);

        $list = $this->json('GET', '/api/bac-saisons?saison='.$saison->getId());
        $this->assertCount(1, $list['items']);
        $this->assertSame($saison->getId(), $list['saison']['id']);
    }

    public function testDecoupageEditableOnActiveSeason(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bac = $this->makeBac($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bac-saisons', ['bac' => $bac->getId(), 'saison' => $saison->getId()]);
        $updated = $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['lignes' => 8, 'colonnes' => 10]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(8, $updated['lignes']);
        $this->assertSame(10, $updated['colonnes']);
    }

    public function testPhysicalSizeFrozen(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bac = $this->makeBac($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bac-saisons', ['bac' => $bac->getId(), 'saison' => $saison->getId()]);

        // Tenter de changer la largeur (figée) → 409.
        $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['largeur' => 200]);
        $this->assertResponseStatusCodeSame(409);

        // Tenter de déplacer (posX figé) → 409.
        $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['posX' => 5]);
        $this->assertResponseStatusCodeSame(409);

        // Renvoyer la même valeur figée + changer le découpage → OK.
        $ok = $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['largeur' => 120, 'lignes' => 3]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $ok['lignes']);
    }

    public function testWriteBlockedOnClosedSeason(): void
    {
        $alice = $this->createUser('alice');
        $closed = $this->makeSaison($alice, '2025', 2025, Saison::STATUT_CLOTUREE);
        $bac = $this->makeBac($alice);

        $bacSaison = (new BacSaison())
            ->setUtilisateur($alice)
            ->setBac($bac)
            ->setSaison($closed)
            ->setLargeur(120)->setLongueur(80)->setLignes(4)->setColonnes(6);
        $this->em->persist($bacSaison);
        $this->em->flush();

        $this->client->loginUser($alice);

        // Création sur saison clôturée → 409.
        $this->json('POST', '/api/bac-saisons', ['bac' => $bac->getId(), 'saison' => $closed->getId()]);
        $this->assertResponseStatusCodeSame(409);

        // Édition du découpage sur saison clôturée → 409.
        $this->json('PUT', '/api/bac-saisons/'.$bacSaison->getId(), ['lignes' => 2]);
        $this->assertResponseStatusCodeSame(409);

        // Suppression sur saison clôturée → 409.
        $this->json('DELETE', '/api/bac-saisons/'.$bacSaison->getId());
        $this->assertResponseStatusCodeSame(409);
    }

    public function testDecoupageShrinkRejectedUnderEnPlaceCulture(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bac = $this->makeBac($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bac-saisons', ['bac' => $bac->getId(), 'saison' => $saison->getId()]);

        // Pose une culture « en_place » en colonne 5 (grille 6 colonnes).
        $bs = static::getContainer()->get(\App\Repository\BacSaisonRepository::class)->find($created['id']);
        $culture = (new \App\Entity\Culture())
            ->setUtilisateur($alice)->setSaison($saison)->setBacSaison($bs)
            ->setName('Tomate')->setPosX(5)->setPosY(0)->setLargeurCases(1)->setHauteurCases(1)
            ->setDatePlantation(new \DateTimeImmutable('2026-04-01'))
            ->setStatut(\App\Entity\Culture::STATUT_EN_PLACE);
        $this->em->persist($culture);
        $this->em->flush();

        // Resserrer à 4 colonnes laisserait la culture (col 5) hors bornes → 409.
        $res = $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['colonnes' => 4]);
        $this->assertResponseStatusCodeSame(409);
        $this->assertArrayHasKey('conflicts', $res);

        // Resserrement compatible (la culture tient encore) → OK.
        $this->json('PUT', '/api/bac-saisons/'.$created['id'], ['colonnes' => 6, 'lignes' => 2]);
        $this->assertResponseIsSuccessful();
    }

    public function testCannotViewOthers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $saison = $this->makeSaison($alice);
        $bac = $this->makeBac($alice);
        $bacSaison = (new BacSaison())
            ->setUtilisateur($alice)->setBac($bac)->setSaison($saison)
            ->setLargeur(120)->setLongueur(80)->setLignes(4)->setColonnes(6);
        $this->em->persist($bacSaison);
        $this->em->flush();

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/bac-saisons/'.$bacSaison->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
