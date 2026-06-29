<?php

namespace App\Tests\Controller\Api;

use App\Entity\Graine;
use App\Entity\GraineLot;
use App\Entity\GraineType;
use App\Entity\Saison;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels de l'API des semis (suivi individuel, batch, rempotages,
 * décrément/restitution de lot, garde saison clôturée).
 */
class SemisApiTest extends DatabaseTestCase
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

    private function makeSaison(Utilisateur $user, string $statut = Saison::STATUT_ACTIVE): Saison
    {
        $s = (new Saison())
            ->setName('2026')->setAnnee(2026)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut($statut)->setUtilisateur($user);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    private function makeGraineType(Utilisateur $user, string $code = 'TC'): GraineType
    {
        $gt = (new GraineType())->setName('Tomate Cerise')->setCode($code)->setUtilisateur($user);
        $this->em->persist($gt);
        $this->em->flush();

        return $gt;
    }

    private function makeGraine(Utilisateur $user, GraineType $gt, string $code = 'TC1'): Graine
    {
        $g = (new Graine())->setGraineType($gt)->setCode($code)->setName('Sweet')->setUtilisateur($user);
        $this->em->persist($g);
        $this->em->flush();

        return $g;
    }

    private function makeLot(Utilisateur $user, Graine $graine, int $qte = 50): GraineLot
    {
        $lot = (new GraineLot())
            ->setGraine($graine)->setSource('achat')
            ->setDateAcquisition(new \DateTime('2026-01-01'))
            ->setQuantiteInitiale($qte)->setQuantiteRestante($qte)
            ->setUtilisateur($user);
        $this->em->persist($lot);
        $this->em->flush();

        return $lot;
    }

    public function testListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/semis');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateDecrementsLot(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $lot = $this->makeLot($alice, $this->makeGraine($alice, $gt), 50);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/semis', [
            'saison' => $saison->getId(),
            'graineType' => $gt->getId(),
            'graineLot' => $lot->getId(),
            'methode' => 'godet',
            'dateSemis' => '2026-03-10',
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('seme', $created['statut']);
        $this->assertSame(49, $created['graineLot']['quantiteRestante']);

        $this->em->clear();
        $this->assertSame(49, $this->em->getRepository(GraineLot::class)->find($lot->getId())->getQuantiteRestante());
    }

    public function testBatchCreatesManyAndDebitsLots(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt1 = $this->makeGraineType($alice, 'TC');
        $gt2 = $this->makeGraineType($alice, 'CO');
        $lot1 = $this->makeLot($alice, $this->makeGraine($alice, $gt1, 'TC1'), 50);
        $lot2 = $this->makeLot($alice, $this->makeGraine($alice, $gt2, 'CO1'), 30);
        $this->client->loginUser($alice);

        $res = $this->json('POST', '/api/semis/batch', [
            'saison' => $saison->getId(),
            'entries' => [
                ['graineType' => $gt1->getId(), 'graineLot' => $lot1->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10', 'quantite' => 6],
                ['graineType' => $gt2->getId(), 'graineLot' => $lot2->getId(), 'methode' => 'direct', 'dateSemis' => '2026-03-10', 'quantite' => 4],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(10, $res['items']);

        $this->em->clear();
        $this->assertSame(44, $this->em->getRepository(GraineLot::class)->find($lot1->getId())->getQuantiteRestante());
        $this->assertSame(26, $this->em->getRepository(GraineLot::class)->find($lot2->getId())->getQuantiteRestante());
    }

    public function testBatchInvalidEntryReturns422(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $this->client->loginUser($alice);

        $res = $this->json('POST', '/api/semis/batch', [
            'saison' => $saison->getId(),
            'entries' => [
                ['graineType' => $gt->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10', 'quantite' => 0],
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('quantite', $res['errors']['entries'][0]);
    }

    public function testUpdateChangingLotRestoresOldDebitsNew(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $graine = $this->makeGraine($alice, $gt);
        $lotA = $this->makeLot($alice, $graine, 50);
        $lotB = $this->makeLot($alice, $graine, 50);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/semis', [
            'saison' => $saison->getId(), 'graineType' => $gt->getId(), 'graineLot' => $lotA->getId(),
            'methode' => 'godet', 'dateSemis' => '2026-03-10',
        ]);
        // lotA = 49

        $this->json('PUT', '/api/semis/'.$created['id'], [
            'saison' => $saison->getId(), 'graineType' => $gt->getId(), 'graineLot' => $lotB->getId(),
            'methode' => 'godet', 'dateSemis' => '2026-03-10',
        ]);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $this->assertSame(50, $this->em->getRepository(GraineLot::class)->find($lotA->getId())->getQuantiteRestante());
        $this->assertSame(49, $this->em->getRepository(GraineLot::class)->find($lotB->getId())->getQuantiteRestante());
    }

    public function testDeleteRestoresLot(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $lot = $this->makeLot($alice, $this->makeGraine($alice, $gt), 50);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/semis', [
            'saison' => $saison->getId(), 'graineType' => $gt->getId(), 'graineLot' => $lot->getId(),
            'methode' => 'godet', 'dateSemis' => '2026-03-10',
        ]);

        $this->client->request('DELETE', '/api/semis/'.$created['id']);
        $this->assertResponseStatusCodeSame(204);

        $this->em->clear();
        $this->assertSame(50, $this->em->getRepository(GraineLot::class)->find($lot->getId())->getQuantiteRestante());
    }

    public function testStatutDerivedFromDates(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $this->client->loginUser($alice);

        $base = ['saison' => $saison->getId(), 'graineType' => $gt->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10'];
        $created = $this->json('POST', '/api/semis', $base);
        $this->assertSame('seme', $created['statut']);

        $upd = $this->json('PUT', '/api/semis/'.$created['id'], $base + ['dateLevee' => '2026-03-20']);
        $this->assertSame('leve', $upd['statut']);

        $upd = $this->json('PUT', '/api/semis/'.$created['id'], $base + ['dateLevee' => '2026-03-20', 'datePlantation' => '2026-04-15']);
        $this->assertSame('plante', $upd['statut']);

        $upd = $this->json('PUT', '/api/semis/'.$created['id'], $base + ['datePlantation' => '2026-04-15', 'echec' => true]);
        $this->assertSame('echec', $upd['statut']);
    }

    public function testFilterByStatutAndGraineType(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt1 = $this->makeGraineType($alice, 'TC');
        $gt2 = $this->makeGraineType($alice, 'CO');
        $this->client->loginUser($alice);

        $this->json('POST', '/api/semis', ['saison' => $saison->getId(), 'graineType' => $gt1->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10']);
        $leve = $this->json('POST', '/api/semis', ['saison' => $saison->getId(), 'graineType' => $gt2->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10']);
        $this->json('PUT', '/api/semis/'.$leve['id'], ['saison' => $saison->getId(), 'graineType' => $gt2->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10', 'dateLevee' => '2026-03-20']);

        $byType = $this->json('GET', '/api/semis?graineType='.$gt1->getId());
        $this->assertCount(1, $byType['items']);

        $byStatut = $this->json('GET', '/api/semis?statut=leve');
        $this->assertCount(1, $byStatut['items']);
        $this->assertSame('leve', $byStatut['items'][0]['statut']);
    }

    public function testRempotageAddAndDelete(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $this->client->loginUser($alice);

        $created = $this->json('POST', '/api/semis', ['saison' => $saison->getId(), 'graineType' => $gt->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10']);

        $withR = $this->json('POST', '/api/semis/'.$created['id'].'/rempotages', ['date' => '2026-04-01', 'notes' => 'godet plus grand']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, $withR['rempotages']);
        $rid = $withR['rempotages'][0]['id'];

        $afterDel = $this->json('DELETE', '/api/semis/'.$created['id'].'/rempotages/'.$rid);
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $afterDel['rempotages']);
    }

    public function testClosedSeasonRefusesWrite(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice, Saison::STATUT_CLOTUREE);
        $gt = $this->makeGraineType($alice);
        $this->client->loginUser($alice);

        $this->json('POST', '/api/semis', ['saison' => $saison->getId(), 'graineType' => $gt->getId(), 'methode' => 'godet', 'dateSemis' => '2026-03-10']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCannotViewOthers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $semis = (new Semis())->setUtilisateur($alice)->setSaison($saison)->setGraineType($gt)
            ->setMethode('godet')->setDateSemis(new \DateTimeImmutable('2026-03-10'));
        $semis->recomputeStatut();
        $this->em->persist($semis);
        $this->em->flush();

        $this->client->loginUser($bob);
        $this->client->request('GET', '/api/semis/'.$semis->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
