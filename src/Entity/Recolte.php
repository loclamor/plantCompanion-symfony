<?php

namespace App\Entity;

use App\Repository\RecolteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une récolte sur une culture (0..n par culture, chacune sa date). Permet de
 * cumuler le rendement d'une culture produisant tout au long de la saison
 * (tomate, courgette, fraisier…). La culture reste « en_place » : le statut
 * « recolte » est un état final posé manuellement (arrachée/terminée).
 */
#[ORM\Entity(repositoryClass: RecolteRepository::class)]
class Recolte implements UserOwnedInterface
{
    public const UNITE_PIECES = 'pieces';
    public const UNITE_G = 'g';
    public const UNITE_KG = 'kg';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'recoltes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Culture $culture = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $quantite = null;

    #[ORM\Column(length: 20)]
    private string $unite = self::UNITE_PIECES;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCulture(): ?Culture
    {
        return $this->culture;
    }

    public function setCulture(?Culture $culture): static
    {
        $this->culture = $culture;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getQuantite(): ?float
    {
        return $this->quantite;
    }

    public function setQuantite(?float $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;

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
