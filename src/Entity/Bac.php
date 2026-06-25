<?php

namespace App\Entity;

use App\Repository\BacRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Identité logique persistante d'un bac de culture, réutilisée d'une saison à
 * l'autre (module Potager). Porte des valeurs *par défaut* (taille physique +
 * découpage) recopiées dans un BacSaison au démarrage d'une saison. Persistant,
 * hors saison ; peut être archivé pour ne plus être reporté.
 */
#[ORM\Entity(repositoryClass: BacRepository::class)]
class Bac implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** Largeur physique par défaut (cm). */
    #[ORM\Column]
    private int $largeurDefaut = 0;

    /** Longueur physique par défaut (cm). */
    #[ORM\Column]
    private int $longueurDefaut = 0;

    /** Nombre de lignes (découpage grille) par défaut. */
    #[ORM\Column]
    private int $lignesDefaut = 1;

    /** Nombre de colonnes (découpage grille) par défaut. */
    #[ORM\Column]
    private int $colonnesDefaut = 1;

    #[ORM\Column]
    private bool $archived = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLargeurDefaut(): int
    {
        return $this->largeurDefaut;
    }

    public function setLargeurDefaut(int $largeurDefaut): static
    {
        $this->largeurDefaut = $largeurDefaut;

        return $this;
    }

    public function getLongueurDefaut(): int
    {
        return $this->longueurDefaut;
    }

    public function setLongueurDefaut(int $longueurDefaut): static
    {
        $this->longueurDefaut = $longueurDefaut;

        return $this;
    }

    public function getLignesDefaut(): int
    {
        return $this->lignesDefaut;
    }

    public function setLignesDefaut(int $lignesDefaut): static
    {
        $this->lignesDefaut = $lignesDefaut;

        return $this;
    }

    public function getColonnesDefaut(): int
    {
        return $this->colonnesDefaut;
    }

    public function setColonnesDefaut(int $colonnesDefaut): static
    {
        $this->colonnesDefaut = $colonnesDefaut;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
