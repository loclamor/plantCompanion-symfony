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
 * Tests fonctionnels des actions en lot sur les semis (POST /api/semis/batch-action) :
 * application aux N plus anciens éligibles, préconditions de statut, scope graine,
 * garde saison clôturée, isolation propriétaire.
 */
class SemisBatchActionApiTest extends DatabaseTestCase
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

    private function makeSaison(Utilisateur $user): Saison
    {
        $s = (new Saison())
            ->setName('2026')->setAnnee(2026)
            ->setDateDebut(new \DateTimeImmutable('2026-03-01'))
            ->setStatut(Saison::STATUT_ACTIVE)->setUtilisateur($user);
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

    private function makeLot(Utilisateur $user, GraineType $gt, string $code = 'TC1'): GraineLot
    {
        $graine = (new Graine())->setGraineType($gt)->setCode($code)->setName('Sweet')->setUtilisateur($user);
        $this->em->persist($graine);
        $lot = (new GraineLot())
            ->setGraine($graine)->setSource('achat')
            ->setDateAcquisition(new \DateTime('2026-01-01'))
            ->setQuantiteInitiale(50)->setQuantiteRestante(50)
            ->setUtilisateur($user);
        $this->em->persist($lot);
        $this->em->flush();

        return $lot;
    }

    private function makeSemis(Utilisateur $user, Saison $saison, GraineType $gt, ?GraineLot $lot, string $dateSemis, ?string $dateLevee = null): Semis
    {
        $s = (new Semis())
            ->setSaison($saison)->setGraineType($gt)->setGraineLot($lot)
            ->setDateSemis(new \DateTimeImmutable($dateSemis))
            ->setDateLevee(null !== $dateLevee ? new \DateTimeImmutable($dateLevee) : null)
            ->setUtilisateur($user);
        $s->recomputeStatut();
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    /** @return array<string, string> dateSemis => statut */
    private function statutsByDate(Utilisateur $user, Saison $saison): array
    {
        $this->client->loginUser($user);
        $data = $this->json('GET', '/api/semis?saison='.$saison->getId());
        $out = [];
        foreach ($data['items'] as $it) {
            $out[$it['dateSemis']] = $it['statut'];
        }

        return $out;
    }

    public function testRequiresAuth(): void
    {
        $this->client->request('POST', '/api/semis/batch-action');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLeverAppliesToOldestEligible(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $lot = $this->makeLot($alice, $gt);
        $graineId = $lot->getGraine()->getId();

        // Trois semis « semé » de la même graine, dates distinctes.
        $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-01');
        $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-02');
        $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-03');

        $this->client->loginUser($alice);
        $resp = $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gt->getId(),
            'graine' => $graineId,
            'action' => 'lever',
            'count' => 2,
            'date' => '2026-03-20',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $resp['applied']);
        $this->assertSame(3, $resp['eligible']);

        // Les deux plus anciens sont levés, le plus récent reste semé.
        $byDate = $this->statutsByDate($alice, $saison);
        $this->assertSame('leve', $byDate['2026-03-01']);
        $this->assertSame('leve', $byDate['2026-03-02']);
        $this->assertSame('seme', $byDate['2026-03-03']);
    }

    public function testRempoterRequiresLeve(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $lot = $this->makeLot($alice, $gt);
        $graineId = $lot->getGraine()->getId();

        // Un semé (non éligible), un levé (éligible).
        $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-01');
        $leve = $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-02', '2026-03-10');
        $leveId = $leve->getId();

        $this->client->loginUser($alice);
        $resp = $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gt->getId(),
            'graine' => $graineId,
            'action' => 'rempoter',
            'count' => 5,
            'date' => '2026-03-25',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $resp['applied'], 'Seul le semis levé est éligible');
        $this->assertSame(1, $resp['eligible']);

        $detail = $this->json('GET', '/api/semis/'.$leveId);
        $this->assertCount(1, $detail['rempotages']);
        $this->assertSame('2026-03-25', $detail['rempotages'][0]['date']);
    }

    public function testEchecRejectsBadActionAndMissingDate(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gt = $this->makeGraineType($alice);
        $lot = $this->makeLot($alice, $gt);
        $this->makeSemis($alice, $saison, $gt, $lot, '2026-03-01');

        $this->client->loginUser($alice);

        // Action inconnue → 422.
        $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gt->getId(), 'graine' => $lot->getGraine()->getId(),
            'action' => 'arroser', 'count' => 1, 'date' => '2026-03-20',
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Lever sans date → 422.
        $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gt->getId(), 'graine' => $lot->getGraine()->getId(),
            'action' => 'lever', 'count' => 1,
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Échec sans date → autorisé.
        $resp = $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gt->getId(), 'graine' => $lot->getGraine()->getId(),
            'action' => 'echec', 'count' => 1,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $resp['applied']);
    }

    public function testIsolationBetweenUsers(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $saisonBob = $this->makeSaison($bob);
        $gtBob = $this->makeGraineType($bob);
        $lotBob = $this->makeLot($bob, $gtBob);
        $this->makeSemis($bob, $saisonBob, $gtBob, $lotBob, '2026-03-01');

        // Alice tente d'agir sur le type/graine de Bob → type non possédé → 422.
        $this->client->loginUser($alice);
        $this->json('POST', '/api/semis/batch-action', [
            'graineType' => $gtBob->getId(), 'graine' => $lotBob->getGraine()->getId(),
            'action' => 'lever', 'count' => 1, 'date' => '2026-03-20',
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Le semis de Bob reste « semé ».
        $byDate = $this->statutsByDate($bob, $saisonBob);
        $this->assertSame('seme', $byDate['2026-03-01']);
    }
}
