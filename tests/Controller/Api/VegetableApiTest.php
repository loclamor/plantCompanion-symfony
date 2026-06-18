<?php

namespace App\Tests\Controller\Api;

use App\Entity\Group;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels de l'API JSON des plantes consommée par le SPA Vue.
 */
class VegetableApiTest extends DatabaseTestCase
{
    /** @return array{Type, Group} */
    private function refs(Utilisateur $user): array
    {
        $type = (new Type())->setName('Légume')->setUtilisateur($user);
        $group = (new Group())->setName('Jardin')->setUtilisateur($user);
        $this->em->persist($type);
        $this->em->persist($group);
        $this->em->flush();

        return [$type, $group];
    }

    private function jsonRequest(string $method, string $uri, array $payload = null): array
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

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListReturnsOnlyOwnVegetables(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->createVegetable($alice, 'Tomate Alice');
        $this->createVegetable($bob, 'Courgette Bob');

        $this->client->loginUser($alice);
        $data = $this->jsonRequest('GET', '/api/vegetables');

        $this->assertResponseIsSuccessful();
        $names = array_column($data['items'], 'name');
        $this->assertContains('Tomate Alice', $names);
        $this->assertNotContains('Courgette Bob', $names);
        $this->assertSame(1, $data['total']);
    }

    public function testCreateThenShow(): void
    {
        $alice = $this->createUser('alice');
        [$type, $group] = $this->refs($alice);

        $this->client->loginUser($alice);
        $created = $this->jsonRequest('POST', '/api/vegetables', [
            'name' => 'Basilic',
            'type' => $type->getId(),
            'group' => $group->getId(),
            'rusticite' => -5,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Basilic', $created['name']);

        $shown = $this->jsonRequest('GET', '/api/vegetables/'.$created['id']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Basilic', $shown['name']);
        $this->assertSame(-5, $shown['rusticite']);
        $this->assertSame($type->getId(), $shown['type']['id']);
    }

    public function testCreateWithoutNameReturns422(): void
    {
        $alice = $this->createUser('alice');
        [$type, $group] = $this->refs($alice);

        $this->client->loginUser($alice);
        $data = $this->jsonRequest('POST', '/api/vegetables', [
            'name' => '',
            'type' => $type->getId(),
            'group' => $group->getId(),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testCreateWithoutGroupIsAllowed(): void
    {
        $alice = $this->createUser('alice');
        [$type] = $this->refs($alice);

        $this->client->loginUser($alice);
        $created = $this->jsonRequest('POST', '/api/vegetables', [
            'name' => 'Sauge',
            'type' => $type->getId(),
            // pas de group → « Sans groupe »
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertNull($created['group']);
    }

    public function testCreateWithInlinePorteGreffe(): void
    {
        $alice = $this->createUser('alice');
        [$type, $group] = $this->refs($alice);

        $this->client->loginUser($alice);
        $created = $this->jsonRequest('POST', '/api/vegetables', [
            'name' => 'Pommier',
            'type' => $type->getId(),
            'group' => $group->getId(),
            'porteGreffe' => '-new-',
            'newPorteGreffe' => 'Franc',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotNull($created['porteGreffe']);
        $this->assertSame('Franc', $created['porteGreffe']['name']);

        // Le nouveau porte-greffe est listé et rattaché au type sélectionné.
        $list = $this->jsonRequest('GET', '/api/porte-greffes');
        $this->assertContains('Franc', array_column($list['items'], 'name'));
        $pg = array_values(array_filter($list['items'], static fn ($p) => 'Franc' === $p['name']))[0];
        $this->assertSame($type->getId(), $pg['type']['id']);
    }

    public function testCannotShowOthersVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vegetable = $this->createVegetable($alice, 'Tomate Alice');

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/vegetables/'.$vegetable->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdate(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');

        $this->client->loginUser($alice);
        $updated = $this->jsonRequest('PUT', '/api/vegetables/'.$vegetable->getId(), [
            'name' => 'Tomate cerise',
            'type' => $vegetable->getType()->getId(),
            'group' => $vegetable->getGroup()->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('Tomate cerise', $updated['name']);
    }

    public function testDelete(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $id = $vegetable->getId(); // Doctrine remet l'id à null après remove()

        $this->client->loginUser($alice);
        $this->client->request('DELETE', '/api/vegetables/'.$id);

        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/vegetables/'.$id);
        $this->assertResponseStatusCodeSame(404);
    }
}
