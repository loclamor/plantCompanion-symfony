<?php

namespace App\Entity;

use App\Repository\GraineLotRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lot de graines rattaché à une Graine : date d'acquisition (achat/récolte) et
 * quantités. Plusieurs lots par Graine (la quantité est liée à la date). Le
 * décrément automatique de la quantité restante arrive avec les semis (Phase 3).
 */
#[ORM\Entity(repositoryClass: GraineLotRepository::class)]
class GraineLot implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Graine::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Graine $graine = null;

    /** achat | recolte */
    #[ORM\Column(length: 20)]
    private ?string $source = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column]
    private ?int $quantiteInitiale = null;

    #[ORM\Column]
    private ?int $quantiteRestante = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fournisseur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGraine(): ?Graine
    {
        return $this->graine;
    }

    public function setGraine(?Graine $graine): static
    {
        $this->graine = $graine;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getDateAcquisition(): ?\DateTimeInterface
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(\DateTimeInterface $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;

        return $this;
    }

    public function getQuantiteInitiale(): ?int
    {
        return $this->quantiteInitiale;
    }

    public function setQuantiteInitiale(int $quantiteInitiale): static
    {
        $this->quantiteInitiale = $quantiteInitiale;

        return $this;
    }

    public function getQuantiteRestante(): ?int
    {
        return $this->quantiteRestante;
    }

    public function setQuantiteRestante(int $quantiteRestante): static
    {
        $this->quantiteRestante = $quantiteRestante;

        return $this;
    }

    public function getFournisseur(): ?string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?string $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

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
