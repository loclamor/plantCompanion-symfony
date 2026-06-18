<?php

namespace App\Tests\Controller\Api;

use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels de l'API des interventions (actions).
 */
class ActionApiTest extends DatabaseTestCase
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

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/actions');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAndList(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'taille',
            'title' => 'Taille de printemps',
            'comment' => 'RAS',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Taille de printemps', $created['title']);
        $this->assertSame($vegetable->getId(), $created['vegetable']['id']);

        $list = $this->json('GET', '/api/actions');
        $this->assertCount(1, $list['items']);
    }

    public function testInvalidTypeReturns422(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'inexistant',
            'title' => 'X',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('typeAction', $data['errors']);
    }

    public function testMissingTitleAndVegetableReturns422(): void
    {
        $alice = $this->createUser('alice');
        $this->client->loginUser($alice);

        $data = $this->json('POST', '/api/actions', ['typeAction' => 'observation', 'title' => '']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('title', $data['errors']);
        $this->assertArrayHasKey('vegetable', $data['errors']);
    }

    public function testCannotAttachToOthersVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vegetable = $this->createVegetable($alice, 'Tomate Alice');

        $this->client->loginUser($bob);
        $data = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'observation',
            'title' => 'Hack',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('vegetable', $data['errors']);
    }

    public function testUpdateAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/actions', [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'observation',
            'title' => 'Note',
        ]);
        $id = $created['id'];

        $updated = $this->json('PUT', '/api/actions/'.$id, [
            'vegetable' => $vegetable->getId(),
            'typeAction' => 'ceuillette',
            'title' => 'Récolte',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('ceuillette', $updated['typeAction']);

        $this->client->request('DELETE', '/api/actions/'.$id);
        $this->assertResponseStatusCodeSame(204);
    }
}
