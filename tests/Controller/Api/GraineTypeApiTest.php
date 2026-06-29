<?php

namespace App\Tests\Controller\Api;

use App\Entity\GraineType;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels du CRUD JSON des types de graines génériques (grainothèque).
 */
class GraineTypeApiTest extends DatabaseTestCase
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

    private function makeGraineType(Utilisateur $user, string $name = 'Tomate Cerise', string $code = 'TC', ?GraineType $parent = null): GraineType
    {
        $gt = (new GraineType())->setName($name)->setCode($code)->setUtilisateur($user)->setParent($parent);
        $this->em->persist($gt);
        $this->em->flush();

        return $gt;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/graine-types');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndList(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/graine-types', ['name' => 'Tomate Cerise', 'code' => 'TC']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Tomate Cerise', $created['name']);
        $this->assertSame('TC', $created['code']);
        $this->assertSame(0, $created['nbGraines']);

        $list = $this->json('GET', '/api/graine-types');
        $this->assertSame(['TC'], array_column($list['items'], 'code'));
    }

    public function testCreateWithoutNameReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-types', ['name' => '', 'code' => 'TC']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testCreateWithoutCodeReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-types', ['name' => 'Tomate', 'code' => '']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('code', $data['errors']);
    }

    public function testDuplicatePrefixReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->makeGraineType($alice, 'Tomate Cerise', 'TC');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-types', ['name' => 'Autre', 'code' => 'TC']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('code', $data['errors']);
    }

    public function testSamePrefixDifferentUsersOk(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->makeGraineType($alice, 'Tomate Cerise', 'TC');

        $this->client->loginUser($bob);
        $this->json('POST', '/api/graine-types', ['name' => 'Tomate Cerise', 'code' => 'TC']);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testListScopedToOwner(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->makeGraineType($alice, 'Type Alice', 'TA');
        $this->makeGraineType($bob, 'Type Bob', 'TB');

        $this->client->loginUser($alice);
        $list = $this->json('GET', '/api/graine-types');

        $codes = array_column($list['items'], 'code');
        $this->assertContains('TA', $codes);
        $this->assertNotContains('TB', $codes);
    }

    public function testCannotEditOthers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $gt = $this->makeGraineType($alice, 'Type Alice', 'TA');

        $this->client->loginUser($bob);
        $this->client->request(
            'PUT',
            '/api/graine-types/'.$gt->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Piraté', 'code' => 'TA']),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateWithParentSerialized(): void
    {
        $alice = $this->createUser('alice');
        $pois = $this->makeGraineType($alice, 'Pois', 'P');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/graine-types', ['name' => 'Pois nain', 'code' => 'PN', 'parent' => $pois->getId()]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($pois->getId(), $created['parentId']);
        $this->assertSame('Pois', $created['parentName']);
    }

    public function testParentCanBeCleared(): void
    {
        $alice = $this->createUser('alice');
        $pois = $this->makeGraineType($alice, 'Pois', 'P');
        $nain = $this->makeGraineType($alice, 'Pois nain', 'PN', $pois);
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/graine-types/'.$nain->getId(), ['name' => 'Pois nain', 'code' => 'PN', 'parent' => '']);
        $this->assertResponseIsSuccessful();
        $this->assertNull($updated['parentId']);
    }

    public function testCannotBeOwnParent(): void
    {
        $alice = $this->createUser('alice');
        $pois = $this->makeGraineType($alice, 'Pois', 'P');
        $this->client->loginUser($alice);

        $data = $this->json('PUT', '/api/graine-types/'.$pois->getId(), ['name' => 'Pois', 'code' => 'P', 'parent' => $pois->getId()]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('parent', $data['errors']);
    }

    public function testCannotParentToOwnDescendant(): void
    {
        $alice = $this->createUser('alice');
        $pois = $this->makeGraineType($alice, 'Pois', 'P');
        $nain = $this->makeGraineType($alice, 'Pois nain', 'PN', $pois);
        $this->client->loginUser($alice);

        // Rattacher « Pois » sous son propre descendant « Pois nain » → cycle interdit.
        $data = $this->json('PUT', '/api/graine-types/'.$pois->getId(), ['name' => 'Pois', 'code' => 'P', 'parent' => $nain->getId()]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('parent', $data['errors']);
    }

    public function testParentFromOtherUserRejected(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $poisBob = $this->makeGraineType($bob, 'Pois', 'P');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/graine-types', ['name' => 'Pois nain', 'code' => 'PN', 'parent' => $poisBob->getId()]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('parent', $data['errors']);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $gt = $this->makeGraineType($alice, 'Tomate', 'TC');
        $id = $gt->getId();
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/graine-types/'.$id, ['name' => 'Tomate Cerise', 'code' => 'TC']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Tomate Cerise', $updated['name']);

        $this->client->request('DELETE', '/api/graine-types/'.$id);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/graine-types/'.$id);
        $this->assertResponseStatusCodeSame(404);
    }
}
