<?php

namespace App\Entity;

use App\Repository\GraineTypeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Type de graine générique de la grainothèque : un nom (ex. « Tomate Cerise ») et
 * un préfixe de code (ex. « TC ») servant à classer les graines et à générer leurs
 * codes (TC1, TC2…). Persistant, hors saison.
 */
#[ORM\Entity(repositoryClass: GraineTypeRepository::class)]
class GraineType implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** Préfixe de code (ex. « TC »). */
    #[ORM\Column(length: 20)]
    private ?string $code = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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
