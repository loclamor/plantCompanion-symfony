<?php

namespace App\Tests\Controller\Api;

use App\Entity\Bac;
use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\GraineType;
use App\Entity\Saison;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels de l'API des cultures (Phase 5) : CRUD, scope saison,
 * validation de placement (bornes + chevauchement), lien semis → planté,
 * garde saison clôturée, isolation propriétaire.
 */
class CultureApiTest extends DatabaseTestCase
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
        $s = (new Saison())
            ->setName($name)->setAnnee($annee)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut($statut)->setUtilisateur($user);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    private function makeBacSaison(Utilisateur $user, Saison $saison, int $lignes = 4, int $colonnes = 6): BacSaison
    {
        $bac = (new Bac())
            ->setName('Bac A')->setLargeurDefaut(120)->setLongueurDefaut(80)
            ->setLignesDefaut($lignes)->setColonnesDefaut($colonnes)->setUtilisateur($user);
        $this->em->persist($bac);

        $bs = (new BacSaison())
            ->setBac($bac)->setSaison($saison)
            ->setLargeur(120)->setLongueur(80)->setPosX(0)->setPosY(0)
            ->setLignes($lignes)->setColonnes($colonnes)->setUtilisateur($user);
        $this->em->persist($bs);
        $this->em->flush();

        return $bs;
    }

    private function makeGraineType(Utilisateur $user, string $code = 'TC'): GraineType
    {
        $gt = (new GraineType())->setName('Tomate Cerise')->setCode($code)->setUtilisateur($user);
        $this->em->persist($gt);
        $this->em->flush();

        return $gt;
    }

    private function basePayload(BacSaison $bs, array $overrides = []): array
    {
        return array_merge([
            'bacSaison' => $bs->getId(),
            'name' => 'Tomate',
            'posX' => 0, 'posY' => 0,
            'largeurCases' => 1, 'hauteurCases' => 1,
            'datePlantation' => '2026-04-01',
            'statut' => 'en_place',
        ], $overrides);
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/cultures');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndScopedList(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 2, 'posY' => 1]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(2, $created['posX']);
        $this->assertSame($saison->getId(), $created['saison']['id']);
        $this->assertSame($bs->getId(), $created['bacSaison']['id']);

        $list = $this->json('GET', '/api/cultures?saison='.$saison->getId());
        $this->assertCount(1, $list['items']);

        $byBac = $this->json('GET', '/api/cultures?bacSaison='.$bs->getId().'&statut=en_place');
        $this->assertCount(1, $byBac['items']);
    }

    public function testCreateRejectsOutOfBounds(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison, lignes: 4, colonnes: 6);
        $this->client->loginUser($alice);

        // colonnes=6 → posX 5 + largeur 2 = 7 > 6 → hors bornes.
        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 5, 'largeurCases' => 2]));
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateRejectsOverlapWithEnPlace(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 1, 'posY' => 1, 'largeurCases' => 2, 'hauteurCases' => 2]));
        $this->assertResponseStatusCodeSame(201);

        // Zone qui recoupe la première (case 2,2).
        $res = $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 2, 'posY' => 2]));
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('conflicts', $res);
    }

    public function testOverlapAllowedWithHarvestedCulture(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $first = $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 1, 'posY' => 1]));
        // Passer la première en « récolté » → ne bloque plus.
        $this->json('PUT', '/api/cultures/'.$first['id'], $this->basePayload($bs, ['posX' => 1, 'posY' => 1, 'statut' => 'recolte']));
        $this->assertResponseIsSuccessful();

        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 1, 'posY' => 1]));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAdjacentPlacementAllowed(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 0, 'posY' => 0]));
        $this->assertResponseStatusCodeSame(201);
        // Case adjacente (1,0) : pas de recouvrement.
        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 1, 'posY' => 0]));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testUpdateDoesNotConflictWithItself(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $c = $this->json('POST', '/api/cultures', $this->basePayload($bs, ['posX' => 1, 'posY' => 1, 'largeurCases' => 2, 'hauteurCases' => 2]));
        // Mise à jour qui garde une zone se chevauchant avec sa propre position → OK.
        $this->json('PUT', '/api/cultures/'.$c['id'], $this->basePayload($bs, ['posX' => 1, 'posY' => 1, 'largeurCases' => 2, 'hauteurCases' => 2, 'name' => 'Renommée']));
        $this->assertResponseIsSuccessful();
    }

    public function testSemisLinkSwitchesToPlante(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $gt = $this->makeGraineType($alice);

        $semis = (new Semis())
            ->setSaison($saison)->setGraineType($gt)->setMethode(Semis::METHODE_GODET)
            ->setDateSemis(new \DateTimeImmutable('2026-03-10'))
            ->setDateLevee(new \DateTimeImmutable('2026-03-20'))
            ->setUtilisateur($alice);
        $semis->recomputeStatut();
        $this->em->persist($semis);
        $this->em->flush();
        $this->assertSame('leve', $semis->getStatut());

        $this->client->loginUser($alice);
        $this->json('POST', '/api/cultures', $this->basePayload($bs, ['semis' => $semis->getId(), 'graineType' => $gt->getId()]));
        $this->assertResponseStatusCodeSame(201);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Semis::class)->find($semis->getId());
        $this->assertSame('plante', $reloaded->getStatut());
        $this->assertSame('2026-04-01', $reloaded->getDatePlantation()->format('Y-m-d'));
    }

    public function testWriteBlockedOnClosedSeason(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice, statut: Saison::STATUT_CLOTUREE);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $this->json('POST', '/api/cultures', $this->basePayload($bs));
        $this->assertResponseStatusCodeSame(409);
    }

    public function testMultipleRecoltesAccumulateAndCultureStaysEnPlace(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $c = $this->json('POST', '/api/cultures', $this->basePayload($bs, ['name' => 'Tomate']));

        $r1 = $this->json('POST', '/api/cultures/'.$c['id'].'/recoltes', ['date' => '2026-06-20', 'quantite' => 1.5, 'unite' => 'kg', 'notes' => '1re']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, $r1['recoltes']);

        $r2 = $this->json('POST', '/api/cultures/'.$c['id'].'/recoltes', ['date' => '2026-07-01', 'quantite' => 800, 'unite' => 'g']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(2, $r2['recoltes']);
        // La culture n'a pas changé de statut malgré les récoltes.
        $this->assertSame('en_place', $r2['statut']);
        $this->assertSame(1.5, $r2['recoltes'][0]['quantite']);
        $this->assertSame('kg', $r2['recoltes'][0]['unite']);
    }

    public function testDeleteRecolte(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $c = $this->json('POST', '/api/cultures', $this->basePayload($bs));
        $added = $this->json('POST', '/api/cultures/'.$c['id'].'/recoltes', ['date' => '2026-06-20', 'unite' => 'pieces']);
        $rid = $added['recoltes'][0]['id'];

        $after = $this->json('DELETE', '/api/cultures/'.$c['id'].'/recoltes/'.$rid);
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $after['recoltes']);
    }

    public function testRecolteRejectsInvalidUnite(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);

        $c = $this->json('POST', '/api/cultures', $this->basePayload($bs));
        $this->json('POST', '/api/cultures/'.$c['id'].'/recoltes', ['date' => '2026-06-20', 'unite' => 'tonnes']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testRecolteBlockedOnClosedSeason(): void
    {
        $alice = $this->createUser('alice');
        $active = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $active);
        $this->client->loginUser($alice);
        $c = $this->json('POST', '/api/cultures', $this->basePayload($bs));

        // Clôturer la saison côté base puis tenter d'ajouter une récolte.
        $active->setStatut(Saison::STATUT_CLOTUREE);
        $this->em->flush();

        $this->json('POST', '/api/cultures/'.$c['id'].'/recoltes', ['date' => '2026-06-20', 'unite' => 'g']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testOwnerIsolation(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $saison = $this->makeSaison($alice);
        $bs = $this->makeBacSaison($alice, $saison);
        $this->client->loginUser($alice);
        $created = $this->json('POST', '/api/cultures', $this->basePayload($bs));

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/cultures/'.$created['id']);
        $this->assertResponseStatusCodeSame(403);
    }
}
