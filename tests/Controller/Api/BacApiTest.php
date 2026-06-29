<?php

namespace App\Tests\Controller\Api;

use App\Entity\Bac;
use App\Entity\Saison;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels du CRUD JSON des bacs (module Potager, Phase 4).
 */
class BacApiTest extends DatabaseTestCase
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

    private function makeBac(Utilisateur $user, string $name = 'Bac A', bool $archived = false): Bac
    {
        $bac = (new Bac())
            ->setName($name)
            ->setLargeurDefaut(120)
            ->setLongueurDefaut(80)
            ->setLignesDefaut(4)
            ->setColonnesDefaut(6)
            ->setArchived($archived)
            ->setUtilisateur($user);
        $this->em->persist($bac);
        $this->em->flush();

        return $bac;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/bacs');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndList(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bacs', [
            'name' => 'Carré nord',
            'largeurDefaut' => 120,
            'longueurDefaut' => 120,
            'lignesDefaut' => 4,
            'colonnesDefaut' => 4,
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Carré nord', $created['name']);
        $this->assertSame(120, $created['largeurDefaut']);
        $this->assertSame(4, $created['colonnesDefaut']);
        $this->assertFalse($created['archived']);

        $list = $this->json('GET', '/api/bacs');
        $this->assertSame(['Carré nord'], array_column($list['items'], 'name'));
    }

    public function testCreateAddsSnapshotToActiveSeason(): void
    {
        $alice = $this->createUser('alice');
        $saison = (new Saison())
            ->setName('2026')->setAnnee(2026)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut(Saison::STATUT_ACTIVE)->setUtilisateur($alice);
        $this->em->persist($saison);
        $this->em->flush();

        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/bacs', [
            'name' => 'Bac auto',
            'largeurDefaut' => 100,
            'longueurDefaut' => 100,
            'lignesDefaut' => 3,
            'colonnesDefaut' => 3,
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Le bac apparaît immédiatement dans la saison active.
        $list = $this->json('GET', '/api/bac-saisons?saison='.$saison->getId());
        $this->assertCount(1, $list['items']);
        $this->assertSame($created['id'], $list['items'][0]['bac']['id']);
        $this->assertSame(3, $list['items'][0]['lignes']);
    }

    public function testCreateArchivedBacSkipsSnapshot(): void
    {
        $alice = $this->createUser('alice');
        $saison = (new Saison())
            ->setName('2026')->setAnnee(2026)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut(Saison::STATUT_ACTIVE)->setUtilisateur($alice);
        $this->em->persist($saison);
        $this->em->flush();

        $this->client->loginUser($alice);

        $this->json('POST', '/api/bacs', [
            'name' => 'Bac archivé',
            'largeurDefaut' => 100, 'longueurDefaut' => 100,
            'lignesDefaut' => 3, 'colonnesDefaut' => 3,
            'archived' => true,
        ]);
        $this->assertResponseStatusCodeSame(201);

        $list = $this->json('GET', '/api/bac-saisons?saison='.$saison->getId());
        $this->assertCount(0, $list['items']);
    }

    public function testCreateInvalidDimensionsReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/bacs', [
            'name' => 'Bac',
            'largeurDefaut' => 0,
            'longueurDefaut' => -5,
            'lignesDefaut' => 0,
            'colonnesDefaut' => 'x',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('largeurDefaut', $data['errors']);
        $this->assertArrayHasKey('longueurDefaut', $data['errors']);
        $this->assertArrayHasKey('lignesDefaut', $data['errors']);
        $this->assertArrayHasKey('colonnesDefaut', $data['errors']);
    }

    public function testArchiverAndUnarchiver(): void
    {
        $alice = $this->createUser('alice');
        $bac = $this->makeBac($alice);
        $this->client->loginUser($alice);

        $archived = $this->json('PUT', '/api/bacs/'.$bac->getId().'/archiver');
        $this->assertResponseIsSuccessful();
        $this->assertTrue($archived['archived']);

        $unarchived = $this->json('PUT', '/api/bacs/'.$bac->getId().'/archiver', ['archived' => false]);
        $this->assertFalse($unarchived['archived']);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $bac = $this->makeBac($alice);
        $id = $bac->getId();
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/bacs/'.$id, [
            'name' => 'Bac renommé',
            'largeurDefaut' => 100,
            'longueurDefaut' => 100,
            'lignesDefaut' => 5,
            'colonnesDefaut' => 5,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Bac renommé', $updated['name']);
        $this->assertSame(5, $updated['lignesDefaut']);

        $this->client->request('DELETE', '/api/bacs/'.$id);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testCannotViewOthers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $bac = $this->makeBac($alice);

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/bacs/'.$bac->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListScopedToOwner(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->makeBac($alice, 'Bac Alice');
        $this->makeBac($bob, 'Bac Bob');

        $this->client->loginUser($alice);
        $list = $this->json('GET', '/api/bacs');

        $names = array_column($list['items'], 'name');
        $this->assertContains('Bac Alice', $names);
        $this->assertNotContains('Bac Bob', $names);
    }
}
