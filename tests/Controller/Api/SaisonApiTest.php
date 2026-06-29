<?php

namespace App\Tests\Controller\Api;

use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels du CRUD JSON des saisons + sélection de la saison courante.
 */
class SaisonApiTest extends DatabaseTestCase
{
    private function json(string $method, string $uri, array $payload = null): array
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
            ->setName($name)
            ->setAnnee($annee)
            ->setDateDebut(new \DateTimeImmutable('2025-03-01'))
            ->setStatut($statut)
            ->setUtilisateur($user);
        $this->em->persist($saison);
        $this->em->flush();

        return $saison;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/saisons');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndList(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/saisons', ['name' => 'Saison 2026', 'annee' => 2026, 'dateDebut' => '2026-03-15']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Saison 2026', $created['name']);
        $this->assertSame(2026, $created['annee']);
        $this->assertSame('2026-03-15', $created['dateDebut']);
        $this->assertSame('active', $created['statut']);

        $list = $this->json('GET', '/api/saisons');
        $this->assertSame(['Saison 2026'], array_column($list['items'], 'name'));
    }

    public function testCreateMissingFieldsReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/saisons', ['name' => '', 'annee' => null, 'dateDebut' => null]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('name', $data['errors']);
        $this->assertArrayHasKey('annee', $data['errors']);
        $this->assertArrayHasKey('dateDebut', $data['errors']);
    }

    public function testCreateSecondSeasonClosesPrevious(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $first = $this->json('POST', '/api/saisons', ['name' => '2025', 'annee' => 2025, 'dateDebut' => '2025-03-01']);
        $this->json('POST', '/api/saisons', ['name' => '2026', 'annee' => 2026, 'dateDebut' => '2026-03-01']);
        $this->assertResponseStatusCodeSame(201);

        $reloaded = $this->json('GET', '/api/saisons/'.$first['id']);
        $this->assertSame('cloturee', $reloaded['statut']);

        // une seule active
        $list = $this->json('GET', '/api/saisons');
        $actives = array_filter($list['items'], fn ($s) => 'active' === $s['statut']);
        $this->assertCount(1, $actives);
    }

    public function testCloturerEndpoint(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $this->client->loginUser($alice);

        $data = $this->json('PUT', '/api/saisons/'.$saison->getId().'/cloturer');
        $this->assertResponseIsSuccessful();
        $this->assertSame('cloturee', $data['statut']);
    }

    public function testCurrentSeasonSelection(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice, '2025', 2025);
        $this->client->loginUser($alice);

        $set = $this->json('PUT', '/api/current-season', ['id' => $saison->getId()]);
        $this->assertSame($saison->getId(), $set['id']);

        $get = $this->json('GET', '/api/current-season');
        $this->assertSame($saison->getId(), $get['id']);
    }

    public function testCurrentSeasonFallsBackToActive(): void
    {
        $alice = $this->createUser('alice');
        $active = $this->makeSaison($alice, '2026', 2026, Saison::STATUT_ACTIVE);
        $this->client->loginUser($alice);

        // rien sélectionné en session → retombe sur l'active
        $get = $this->json('GET', '/api/current-season');
        $this->assertSame($active->getId(), $get['id']);
    }

    public function testCannotViewOthers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $saison = $this->makeSaison($alice);

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/saisons/'.$saison->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListScopedToOwner(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->makeSaison($alice, 'Saison Alice', 2025);
        $this->makeSaison($bob, 'Saison Bob', 2025);

        $this->client->loginUser($alice);
        $list = $this->json('GET', '/api/saisons');

        $names = array_column($list['items'], 'name');
        $this->assertContains('Saison Alice', $names);
        $this->assertNotContains('Saison Bob', $names);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice, '2025', 2025);
        $id = $saison->getId();
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/saisons/'.$id, ['name' => 'Saison 2025', 'annee' => 2025, 'dateDebut' => '2025-04-01']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Saison 2025', $updated['name']);
        $this->assertSame('2025-04-01', $updated['dateDebut']);

        $this->client->request('DELETE', '/api/saisons/'.$id);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/saisons/'.$id);
        $this->assertResponseStatusCodeSame(404);
    }
}
