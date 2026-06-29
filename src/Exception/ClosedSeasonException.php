<?php

namespace App\Exception;

/**
 * Levée quand une écriture est tentée sur une saison clôturée (lecture seule).
 * Les contrôleurs (Phases 3-5) la traduisent en HTTP 409.
 */
class ClosedSeasonException extends \RuntimeException
{
}
