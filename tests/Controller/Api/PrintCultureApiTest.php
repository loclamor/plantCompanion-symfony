<?php

namespace App\Tests\Controller\Api;

use App\Entity\Bac;
use App\Entity\BacSaison;
use App\Entity\Culture;
use App\Entity\Graine;
use App\Entity\GraineLot;
use App\Entity\GraineType;
use App\Entity\Saison;
use App\Entity\Semis;
use App\Entity\Utilisateur;
use App\Tests\DatabaseTestCase;

/**
 * Tests fonctionnels de l'impression d'étiquettes du potager (GET /api/print/cultures) :
 * scope saison courante, regroupement par (code, nom), exclusion des semis en échec
 * et des cultures issues d'un semis, isolation propriétaire.
 */
class PrintCultureApiTest extends DatabaseTestCase
{
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

    private function makeGraineType(Utilisateur $user, string $code, string $name): GraineType
    {
        $gt = (new GraineType())->setName($name)->setCode($code)->setUtilisateur($user);
        $this->em->persist($gt);
        $this->em->flush();

        return $gt;
    }

    private function makeLot(Utilisateur $user, GraineType $gt, string $code, string $name): GraineLot
    {
        $graine = (new Graine())->setGraineType($gt)->setCode($code)->setName($name)->setUtilisateur($user);
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

    private function makeSemis(Utilisateur $user, Saison $saison, GraineType $gt, ?GraineLot $lot, bool $echec = false): Semis
    {
        $s = (new Semis())
            ->setSaison($saison)->setGraineType($gt)->setGraineLot($lot)
            ->setDateSemis(new \DateTimeImmutable('2026-03-15'))
            ->setEchec($echec)->setUtilisateur($user);
        $s->recomputeStatut();
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    private function makeBacSaison(Utilisateur $user, Saison $saison): BacSaison
    {
        $bac = (new Bac())
            ->setName('Bac A')->setLargeurDefaut(120)->setLongueurDefaut(80)
            ->setLignesDefaut(4)->setColonnesDefaut(6)->setUtilisateur($user);
        $this->em->persist($bac);
        $bs = (new BacSaison())
            ->setBac($bac)->setSaison($saison)
            ->setLargeur(120)->setLongueur(80)->setPosX(0)->setPosY(0)
            ->setLignes(4)->setColonnes(6)->setUtilisateur($user);
        $this->em->persist($bs);
        $this->em->flush();

        return $bs;
    }

    private function makeCulture(Utilisateur $user, Saison $saison, BacSaison $bs, string $name, ?GraineType $gt, ?Semis $semis, int $posX = 0): Culture
    {
        $c = (new Culture())
            ->setSaison($saison)->setBacSaison($bs)->setName($name)
            ->setGraineType($gt)->setSemis($semis)
            ->setPosX($posX)->setPosY(0)
            ->setDatePlantation(new \DateTimeImmutable('2026-04-01'))
            ->setStatut(Culture::STATUT_EN_PLACE)->setUtilisateur($user);
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    public function testRequiresAuth(): void
    {
        $this->client->request('GET', '/api/print/cultures');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testReturnsGroupedAliveItems(): void
    {
        $alice = $this->createUser('alice');
        $saison = $this->makeSaison($alice);
        $gtTomate = $this->makeGraineType($alice, 'TC', 'Tomate Cerise');
        $lot = $this->makeLot($alice, $gtTomate, 'TC1', 'Sweet');

        // Deux semis vivants de la même graine → un groupe TC1/Sweet, count 2.
        $semis1 = $this->makeSemis($alice, $saison, $gtTomate, $lot);
        $this->makeSemis($alice, $saison, $gtTomate, $lot);
        // Semis en échec → exclu.
        $this->makeSemis($alice, $saison, $gtTomate, $lot, echec: true);

        $bs = $this->makeBacSaison($alice, $saison);
        $gtBasilic = $this->makeGraineType($alice, 'BA', 'Basilic');
        // Culture plantée en direct → un groupe BA/Basilic, count 1.
        $this->makeCulture($alice, $saison, $bs, 'Basilic', $gtBasilic, null, posX: 0);
        // Culture issue d'un semis → exclue (l'étiquette suit le semis).
        $this->makeCulture($alice, $saison, $bs, 'Tomate plantée', $gtTomate, $semis1, posX: 1);

        // Données d'un autre utilisateur → jamais visibles.
        $bob = $this->createUser('bob');
        $saisonBob = $this->makeSaison($bob);
        $gtBob = $this->makeGraineType($bob, 'BO', 'Bob');
        $this->makeSemis($bob, $saisonBob, $gtBob, null);

        $this->client->loginUser($alice);
        $this->client->request('GET', '/api/print/cultures');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $byKey = [];
        foreach ($data['items'] as $item) {
            $byKey[($item['code'] ?? '').'|'.$item['name']] = $item['count'];
        }

        $this->assertSame(2, $byKey['TC1|Sweet'] ?? null, 'Deux semis vivants regroupés');
        $this->assertSame(1, $byKey['BA|Basilic'] ?? null, 'Culture directe en place');
        $this->assertCount(2, $data['items'], 'Seuls les deux groupes vivants attendus');

        $names = array_column($data['items'], 'name');
        $this->assertNotContains('Bob', $names);
        $this->assertNotContains('Tomate plantée', $names);
    }
}
