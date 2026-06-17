<?php

namespace App\Tests\Controller;

use App\Entity\VegetableHistory;
use App\Tests\DatabaseTestCase;

class AuthenticatedVegetableTest extends DatabaseTestCase
{
    public function testIndexShowsOnlyOwnVegetables(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $this->createVegetable($alice, 'Tomate Alice');
        $this->createVegetable($bob, 'Courgette Bob');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/vegetable');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Tomate Alice');
        $this->assertSelectorTextNotContains('body', 'Courgette Bob');
    }

    public function testFilterFormWithEmptyTypeDoesNotError(): void
    {
        $alice = $this->createUser('alice');
        $this->createVegetable($alice, 'Tomate');

        $this->client->loginUser($alice);
        // Reproduit la soumission du formulaire de recherche (type vide = "Tous les types").
        $this->client->request('GET', '/vegetable?q=Tom&type=&sort=name&dir=asc');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Tomate');
    }

    public function testCannotShowOthersVegetable(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $vegetable = $this->createVegetable($alice, 'Tomate Alice');

        $this->client->loginUser($bob);
        $this->client->request('GET', '/vegetable/'.$vegetable->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testEditCreatesHistory(): void
    {
        $alice = $this->createUser('alice');
        $vegetable = $this->createVegetable($alice, 'Tomate');

        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/vegetable/'.$vegetable->getId().'/edit');
        $form = $crawler->selectButton('Update')->form();
        $form['vegetable[name]'] = 'Tomate cerise';
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $this->em->clear();
        $histories = $this->em->getRepository(VegetableHistory::class)->findBy(['entity' => $vegetable->getId()]);
        $names = array_map(static fn (VegetableHistory $h) => $h->getKey(), $histories);

        $this->assertContains('name', $names, 'Une entrée d\'historique doit tracer le changement de "name".');
    }
}
