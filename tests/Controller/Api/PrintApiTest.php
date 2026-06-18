<?php

namespace App\Tests\Controller\Api;

use App\Entity\Vegetable;
use App\Tests\DatabaseTestCase;

class PrintApiTest extends DatabaseTestCase
{
    public function testRequiresAuth(): void
    {
        $this->client->request('GET', '/api/print/labels');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testReturnsOnlyOwnLabelsWithFields(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $v = $this->createVegetable($alice, 'Tomate');
        $v->setNomLatin('Solanum lycopersicum')->setRusticite(-2);
        $this->em->flush();
        $this->createVegetable($bob, 'Courgette Bob');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/api/print/labels');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $names = array_column($data['items'], 'name');
        $this->assertContains('Tomate', $names);
        $this->assertNotContains('Courgette Bob', $names);

        $label = $data['items'][0];
        $this->assertArrayHasKey('nomLatin', $label);
        $this->assertArrayHasKey('rusticite', $label);
        $this->assertArrayHasKey('defaultPhotoUrl', $label);
    }
}
