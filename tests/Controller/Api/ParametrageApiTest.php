<?php

namespace App\Tests\Controller\Api;

use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels des CRUD JSON de paramétrage (Type, Group, Lieu, PorteGreffe).
 */
class ParametrageApiTest extends DatabaseTestCase
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

    private function makeType(Utilisateur $user, string $name): Type
    {
        $type = (new Type())->setName($name)->setUtilisateur($user);
        $this->em->persist($type);
        $this->em->flush();

        return $type;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/types');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndListType(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/types', ['name' => 'Arbre fruitier']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Arbre fruitier', $created['name']);

        $list = $this->json('GET', '/api/types');
        $this->assertSame(['Arbre fruitier'], array_column($list['items'], 'name'));
    }

    public function testCreateTypeWithParent(): void
    {
        $alice = $this->createUser('alice');
        $parent = $this->makeType($alice, 'Arbre');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/types', ['name' => 'Pommier', 'parent' => $parent->getId()]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($parent->getId(), $created['parent']['id']);
    }

    public function testCreateWithoutNameReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/lieux', ['name' => '']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testPorteGreffeRequiresType(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/porte-greffes', ['name' => 'Franc']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('type', $data['errors']);

        $type = $this->makeType($alice, 'Pommier');
        $ok = $this->json('POST', '/api/porte-greffes', ['name' => 'Franc', 'type' => $type->getId()]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($type->getId(), $ok['type']['id']);
    }

    public function testListIsScopedToOwner(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->makeType($alice, 'Type Alice');
        $this->makeType($bob, 'Type Bob');

        $this->client->loginUser($alice);
        $list = $this->json('GET', '/api/types');

        $names = array_column($list['items'], 'name');
        $this->assertContains('Type Alice', $names);
        $this->assertNotContains('Type Bob', $names);
    }

    public function testCannotEditOthersType(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $type = $this->makeType($alice, 'Type Alice');

        $this->client->loginUser($bob);
        $this->client->request(
            'PUT',
            '/api/types/'.$type->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Piraté']),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $type = $this->makeType($alice, 'Légume');
        $id = $type->getId();
        $this->client->loginUser($alice);

        $updated = $this->json('PUT', '/api/types/'.$id, ['name' => 'Légume racine']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Légume racine', $updated['name']);

        $this->client->request('DELETE', '/api/types/'.$id);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/types/'.$id);
        $this->assertResponseStatusCodeSame(404);
    }
}
