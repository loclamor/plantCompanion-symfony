<?php

namespace App\Service;

use App\Entity\Photo;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;

/**
 * Sérialise une Photo pour l'API JSON : URLs de miniature (plant_thumb) et
 * d'affichage (plant_large) + drapeau « photo par défaut ». Partagé entre
 * VegetableApiController et PhotoApiController.
 */
final class PhotoPresenter
{
    public function __construct(private readonly CacheManager $imagine)
    {
    }

    /** @return array<string, mixed> */
    public function present(Photo $photo, ?Photo $default): array
    {
        $rel = $photo->getRelativePath();

        return [
            'id' => $photo->getId(),
            'url' => null !== $rel ? $this->imagine->getBrowserPath($rel, 'plant_thumb') : null,
            'urlLarge' => null !== $rel ? $this->imagine->getBrowserPath($rel, 'plant_large') : null,
            'isDefault' => null !== $default && $default->getId() === $photo->getId(),
        ];
    }

    /**
     * @param Photo[] $photos
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $photos, ?Photo $default): array
    {
        return array_map(fn (Photo $p) => $this->present($p, $default), array_values($photos));
    }
}
