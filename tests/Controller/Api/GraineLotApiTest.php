<?php

namespace App\Tests\Controller\Api;

use App\Entity\Graine;
use App\Entity\GraineLot;
use App\Entity\GraineType;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels du CRUD JSON des lots de graines (grainothèque).
 */
class GraineLotApiTest extends DatabaseTestCase
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

    private function makeGraine(Utilisateur $user, string $code = 'TC1'): Graine
    {
        $gt = (new GraineType())->setName('Tomate Cerise')->setCode('TC')->setUtilisateur($user);
        $this->em->persist($gt);
        $g = (new Graine())->setGraineType($gt)->setCode($code)->setName('Sweet')->setUtilisateur($user);
        $this->em->persist($g);
        $this->em->flush();

        return $g;
    }

    private function makeLot(Utilisateur $user, Graine $graine, int $restante = 10): GraineLot
    {
        $lot = (new GraineLot())
            ->setGraine($graine)
            ->setSource('achat')
            ->setDateAcquisition(new \DateTime('2026-01-15'))
            ->setQuantiteInitiale($restante)
            ->setQuantiteRestante($restante)
            ->setUtilisateur($user);
        $this->em->persist($lot);
        $this->em->flush();

        return $lot;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/graine-lots');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateLotDefaultsRestanteToInitiale(): void
    {
        $alice = $this->createUser('alice');
        $graine = $this->makeGraine($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/graine-lots', [
            'graine' => $graine->getId(),
            'source' => 'achat',
            'dateAcquisition' => '2026-02-01',
            'quantiteInitiale' => 25,
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(25, $created['quantiteInitiale']);
        $this->assertSame(25, $created['quantiteRestante']);
        $this->assertSame($graine->getId(), $created['graine']['id']);
    }

    public function testCreateWithoutGraineReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-lots', [
            'source' => 'achat',
            'dateAcquisition' => '2026-02-01',
            'quantiteInitiale' => 10,
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('graine', $data['errors']);
    }

    public function testInvalidSourceReturns422(): void
    {
        $alice = $this->createUser('alice');
        $graine = $this->makeGraine($alice);
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-lots', [
            'graine' => $graine->getId(),
            'source' => 'don',
            'dateAcquisition' => '2026-02-01',
            'quantiteInitiale' => 10,
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('source', $data['errors']);
    }

    public function testListFilteredByGraine(): void
    {
        $alice = $this->createUser('alice');
        $g1 = $this->makeGraine($alice, 'TC1');
        $g2 = $this->makeGraine($alice, 'CG1');
        $this->makeLot($alice, $g1, 10);
        $this->makeLot($alice, $g2, 5);
        $this->client->loginUser($alice);

        $list = $this->json('GET', '/api/graine-lots?graine='.$g1->getId());
        $this->assertCount(1, $list['items']);
        $this->assertSame($g1->getId(), $list['items'][0]['graine']['id']);
    }

    public function testListScopedToOwner(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $gA = $this->makeGraine($alice, 'TA1');
        $gB = $this->makeGraine($bob, 'TB1');
        $this->makeLot($alice, $gA, 10);
        $this->makeLot($bob, $gB, 7);

        $this->client->loginUser($alice);
        $list = $this->json('GET', '/api/graine-lots');
        $this->assertCount(1, $list['items']);
    }

    public function testCannotEditOthersLot(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $graine = $this->makeGraine($alice);
        $lot = $this->makeLot($alice, $graine, 10);

        $this->client->loginUser($bob);
        $this->client->request(
            'PUT',
            '/api/graine-lots/'.$lot->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['graine' => $graine->getId(), 'source' => 'achat', 'dateAcquisition' => '2026-01-01', 'quantiteInitiale' => 1]),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $graine = $this->makeGraine($alice);
        $lot = $this->makeLot($alice, $graine, 10);
        $id = $lot->getId();
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/graine-lots/'.$id, [
            'graine' => $graine->getId(),
            'source' => 'recolte',
            'dateAcquisition' => '2026-03-01',
            'quantiteInitiale' => 10,
            'quantiteRestante' => 3,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('recolte', $updated['source']);
        $this->assertSame(3, $updated['quantiteRestante']);

        $this->client->request('DELETE', '/api/graine-lots/'.$id);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/graine-lots/'.$id);
        $this->assertResponseStatusCodeSame(404);
    }
}
