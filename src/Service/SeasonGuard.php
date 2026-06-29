<?php

namespace App\Service;

use App\Entity\Saison;
use App\Exception\ClosedSeasonException;

/**
 * Garde « saison clôturée = lecture seule ». Helper réutilisable, non branché en
 * Phase 2 : les contrôleurs Semis/Bacs/Cultures (Phases 3-5) l'appelleront avant
 * toute écriture sur une entité scopée à une saison.
 */
final class SeasonGuard
{
    public function isWritable(Saison $saison): bool
    {
        return !$saison->isCloturee();
    }

    /**
     * @throws ClosedSeasonException si la saison est clôturée
     */
    public function assertWritable(Saison $saison): void
    {
        if (!$this->isWritable($saison)) {
            throw new ClosedSeasonException(sprintf('La saison « %s » est clôturée (lecture seule).', $saison->getName()));
        }
    }
}
