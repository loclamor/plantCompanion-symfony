<?php

namespace App\Entity;

use App\Repository\GraineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Graine concrète de la grainothèque : rattachée à un GraineType, avec un code
 * unique (ex. TC1), un nom (ex. « Sweet ») et une saisonnalité/méthode de semis
 * théoriques. La méthode de semis effective sera portée par le plant (Phase 5).
 */
#[ORM\Entity(repositoryClass: GraineRepository::class)]
class Graine implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GraineType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?GraineType $graineType = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** Méthode de semis conseillée (indicative) : pleine_terre | couvert. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $methodeSemisConseillee = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $moisSemis = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $moisPlantationTheorique = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $moisRecolteTheorique = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function __toString(): string
    {
        return $this->code ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGraineType(): ?GraineType
    {
        return $this->graineType;
    }

    public function setGraineType(?GraineType $graineType): static
    {
        $this->graineType = $graineType;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMethodeSemisConseillee(): ?string
    {
        return $this->methodeSemisConseillee;
    }

    public function setMethodeSemisConseillee(?string $methodeSemisConseillee): static
    {
        $this->methodeSemisConseillee = $methodeSemisConseillee;

        return $this;
    }

    public function getMoisSemis(): ?int
    {
        return $this->moisSemis;
    }

    public function setMoisSemis(?int $moisSemis): static
    {
        $this->moisSemis = $moisSemis;

        return $this;
    }

    public function getMoisPlantationTheorique(): ?int
    {
        return $this->moisPlantationTheorique;
    }

    public function setMoisPlantationTheorique(?int $moisPlantationTheorique): static
    {
        $this->moisPlantationTheorique = $moisPlantationTheorique;

        return $this;
    }

    public function getMoisRecolteTheorique(): ?int
    {
        return $this->moisRecolteTheorique;
    }

    public function setMoisRecolteTheorique(?int $moisRecolteTheorique): static
    {
        $this->moisRecolteTheorique = $moisRecolteTheorique;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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
