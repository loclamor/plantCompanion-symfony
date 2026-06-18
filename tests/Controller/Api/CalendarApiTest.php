<?php

namespace App\Tests\Controller\Api;

use App\Entity\Group;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Tests\DatabaseTestCase;

class CalendarApiTest extends DatabaseTestCase
{
    private function makeVegetable(Utilisateur $user, string $name, callable $configure): Vegetable
    {
        $type = (new Type())->setName('T')->setUtilisateur($user);
        $group = (new Group())->setName('G')->setUtilisateur($user);
        $this->em->persist($type);
        $this->em->persist($group);
        $v = (new Vegetable())->setName($name)->setType($type)->setGroup($group)->setUtilisateur($user)
            ->setCreationDate(new \DateTime())->setAddDate(new \DateTime());
        $configure($v);
        $this->em->persist($v);
        $this->em->flush();

        return $v;
    }

    public function testRequiresAuth(): void
    {
        $this->client->request('GET', '/api/calendar/fructification');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMonthsFromExplicitRange(): void
    {
        $alice = $this->createUser('alice');
        $this->makeVegetable($alice, 'Tomate', fn (Vegetable $v) => $v->setMoisFructiDebut(6)->setMoisFructiFin(8));

        $this->client->loginUser($alice);
        $this->client->request('GET', '/api/calendar/fructification');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame([6, 7, 8], $data['rows'][0]['fructi']);
    }

    public function testFallbackFromPeriode(): void
    {
        $alice = $this->createUser('alice');
        // Pas de mois précis : repli sur la période (Printemps = 3..5).
        $this->makeVegetable($alice, 'Cerisier', fn (Vegetable $v) => $v->setPFleur('Printemps'));

        $this->client->loginUser($alice);
        $this->client->request('GET', '/api/calendar/fructification');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame([3, 4, 5], $data['rows'][0]['fleur']);
    }

    public function testWrapAroundYear(): void
    {
        $alice = $this->createUser('alice');
        $this->makeVegetable($alice, 'Agrume', fn (Vegetable $v) => $v->setMoisFructiDebut(11)->setMoisFructiFin(2));

        $this->client->loginUser($alice);
        $this->client->request('GET', '/api/calendar/fructification');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame([11, 12, 1, 2], $data['rows'][0]['fructi']);
    }
}
