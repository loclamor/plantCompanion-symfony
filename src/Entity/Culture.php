<?php

namespace App\Entity;

use App\Repository\CultureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un plant posé dans une case d'un bac sur une saison donnée (module Potager).
 * Chaînon semis → bac : peut être issu d'un Semis (qui bascule alors « planté »)
 * ou ajouté directement. Scopé à une saison. La position (posX/posY) et l'emprise
 * (largeurCases/hauteurCases) localisent la culture dans la grille du BacSaison.
 *
 * Les cultures pérennes « en_place » sont reportées dans la saison suivante au
 * démarrage d'un nouveau cycle (lignage via parentCulture).
 */
#[ORM\Entity(repositoryClass: CultureRepository::class)]
class Culture implements UserOwnedInterface
{
    public const STATUT_EN_PLACE = 'en_place';
    public const STATUT_RECOLTE = 'recolte';
    public const STATUT_MORT = 'mort';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?BacSaison $bacSaison = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?GraineType $graineType = null;

    /** Semis d'origine si la culture en est issue (sinon ajout direct). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Semis $semis = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** Colonne d'ancrage dans la grille du bac (0-indexée). */
    #[ORM\Column]
    private int $posX = 0;

    /** Ligne d'ancrage dans la grille du bac (0-indexée). */
    #[ORM\Column]
    private int $posY = 0;

    /** Emprise en colonnes (≥ 1). */
    #[ORM\Column]
    private int $largeurCases = 1;

    /** Emprise en lignes (≥ 1). */
    #[ORM\Column]
    private int $hauteurCases = 1;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $datePlantation = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateRecolteTheorique = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_PLACE;

    #[ORM\Column(type: 'boolean')]
    private bool $perenne = false;

    /** Lignage de report inter-saisons : culture pérenne dont celle-ci est issue. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Culture $parentCulture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    /** @var Collection<int, Recolte> */
    #[ORM\OneToMany(targetEntity: Recolte::class, mappedBy: 'culture', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $recoltes;

    public function __construct()
    {
        $this->recoltes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaison(): ?Saison
    {
        return $this->saison;
    }

    public function setSaison(?Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function getBacSaison(): ?BacSaison
    {
        return $this->bacSaison;
    }

    public function setBacSaison(?BacSaison $bacSaison): static
    {
        $this->bacSaison = $bacSaison;

        return $this;
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

    public function getSemis(): ?Semis
    {
        return $this->semis;
    }

    public function setSemis(?Semis $semis): static
    {
        $this->semis = $semis;

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

    public function getPosX(): int
    {
        return $this->posX;
    }

    public function setPosX(int $posX): static
    {
        $this->posX = $posX;

        return $this;
    }

    public function getPosY(): int
    {
        return $this->posY;
    }

    public function setPosY(int $posY): static
    {
        $this->posY = $posY;

        return $this;
    }

    public function getLargeurCases(): int
    {
        return $this->largeurCases;
    }

    public function setLargeurCases(int $largeurCases): static
    {
        $this->largeurCases = $largeurCases;

        return $this;
    }

    public function getHauteurCases(): int
    {
        return $this->hauteurCases;
    }

    public function setHauteurCases(int $hauteurCases): static
    {
        $this->hauteurCases = $hauteurCases;

        return $this;
    }

    public function getDatePlantation(): ?\DateTimeImmutable
    {
        return $this->datePlantation;
    }

    public function setDatePlantation(\DateTimeImmutable $datePlantation): static
    {
        $this->datePlantation = $datePlantation;

        return $this;
    }

    public function getDateRecolteTheorique(): ?\DateTimeImmutable
    {
        return $this->dateRecolteTheorique;
    }

    public function setDateRecolteTheorique(?\DateTimeImmutable $date): static
    {
        $this->dateRecolteTheorique = $date;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $date): static
    {
        $this->dateFin = $date;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPerenne(): bool
    {
        return $this->perenne;
    }

    public function setPerenne(bool $perenne): static
    {
        $this->perenne = $perenne;

        return $this;
    }

    public function getParentCulture(): ?self
    {
        return $this->parentCulture;
    }

    public function setParentCulture(?self $parentCulture): static
    {
        $this->parentCulture = $parentCulture;

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

    /** @return Collection<int, Recolte> */
    public function getRecoltes(): Collection
    {
        return $this->recoltes;
    }

    public function addRecolte(Recolte $recolte): static
    {
        if (!$this->recoltes->contains($recolte)) {
            $this->recoltes->add($recolte);
            $recolte->setCulture($this);
        }

        return $this;
    }

    public function removeRecolte(Recolte $recolte): static
    {
        $this->recoltes->removeElement($recolte);

        return $this;
    }
}
