<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use App\Entity\Vegetable;
use App\Entity\VegetableHistory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Trace les modifications des plantes : un VegetableHistory par champ modifié,
 * équivalent de l'enregistrement automatique du legacy (Model_Vegetable::apply()).
 *
 * preUpdate collecte les changements (le changeset n'est plus disponible après),
 * postFlush persiste l'historique dans un second flush pour éviter de perturber
 * l'unit of work en cours.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class VegetableHistoryListener
{
    /**
     * @var list<array{key: string, old: string, new: string, entity: Vegetable, user: Utilisateur}>
     */
    private array $pending = [];

    public function __construct(private readonly Security $security)
    {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Vegetable) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        foreach ($args->getEntityChangeSet() as $field => [$old, $new]) {
            $this->pending[] = [
                'key' => $field,
                'old' => $this->format($old),
                'new' => $this->format($new),
                'entity' => $entity,
                'user' => $user,
            ];
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        $em = $args->getObjectManager();
        foreach ($pending as $row) {
            $history = (new VegetableHistory())
                ->setKey($row['key'])
                ->setOldValue($row['old'])
                ->setNewValue($row['new'])
                ->setDate(new \DateTime())
                ->setEntity($row['entity'])
                ->setUtilisateur($row['user']);
            $em->persist($history);
        }

        $em->flush();
    }

    private function format(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (\is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return mb_substr((string) $value, 0, 255);
    }
}
