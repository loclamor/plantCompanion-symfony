<?php

namespace App\Tests\Service;

use App\Entity\Saison;
use App\Exception\ClosedSeasonException;
use App\Service\SeasonGuard;
use PHPUnit\Framework\TestCase;

class SeasonGuardTest extends TestCase
{
    public function testActiveIsWritable(): void
    {
        $guard = new SeasonGuard();
        $saison = (new Saison())->setName('2026')->setStatut(Saison::STATUT_ACTIVE);

        $this->assertTrue($guard->isWritable($saison));
        $guard->assertWritable($saison); // ne lève pas
    }

    public function testClosedIsNotWritable(): void
    {
        $guard = new SeasonGuard();
        $saison = (new Saison())->setName('2025')->setStatut(Saison::STATUT_CLOTUREE);

        $this->assertFalse($guard->isWritable($saison));

        $this->expectException(ClosedSeasonException::class);
        $guard->assertWritable($saison);
    }
}
