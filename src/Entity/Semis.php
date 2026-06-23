<?php

namespace App\Entity;

use App\Repository\SemisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un semis = UNE graine mise en terre, suivie individuellement (ses propres
 * dates de semis/levée/plantation et ses rempotages 0..n). Scopé à une saison,
 * consomme un GraineLot. Le statut est dérivé des dates (cf. recomputeStatut).
 */
#[ORM\Entity(repositoryClass: SemisRepository::class)]
class Semis implements UserOwnedInterface
{
    public const STATUT_SEME = 'seme';
    public const STATUT_LEVE = 'leve';
    public const STATUT_PLANTE = 'plante';
    public const STATUT_ECHEC = 'echec';

    public const METHODE_DIRECT = 'direct';
    public const METHODE_GODET = 'godet';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GraineType $graineType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?GraineLot $graineLot = null;

    #[ORM\Column(length: 20)]
    private string $methode = self::METHODE_GODET;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateSemis = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateLevee = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $datePlantation = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $datePlantationTheorique = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateRecolteTheorique = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_SEME;

    #[ORM\Column(type: 'boolean')]
    private bool $echec = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    /** @var Collection<int, Rempotage> */
    #[ORM\OneToMany(targetEntity: Rempotage::class, mappedBy: 'semis', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rempotages;

    public function __construct()
    {
        $this->rempotages = new ArrayCollection();
    }

    /**
     * Dérive le statut des dates et du drapeau échec : échec prioritaire, sinon
     * plantation → planté, levée → levé, sinon semé.
     */
    public function recomputeStatut(): void
    {
        if ($this->echec) {
            $this->statut = self::STATUT_ECHEC;
        } elseif (null !== $this->datePlantation) {
            $this->statut = self::STATUT_PLANTE;
        } elseif (null !== $this->dateLevee) {
            $this->statut = self::STATUT_LEVE;
        } else {
            $this->statut = self::STATUT_SEME;
        }
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

    public function getGraineType(): ?GraineType
    {
        return $this->graineType;
    }

    public function setGraineType(?GraineType $graineType): static
    {
        $this->graineType = $graineType;

        return $this;
    }

    public function getGraineLot(): ?GraineLot
    {
        return $this->graineLot;
    }

    public function setGraineLot(?GraineLot $graineLot): static
    {
        $this->graineLot = $graineLot;

        return $this;
    }

    public function getMethode(): string
    {
        return $this->methode;
    }

    public function setMethode(string $methode): static
    {
        $this->methode = $methode;

        return $this;
    }

    public function getDateSemis(): ?\DateTimeImmutable
    {
        return $this->dateSemis;
    }

    public function setDateSemis(\DateTimeImmutable $dateSemis): static
    {
        $this->dateSemis = $dateSemis;

        return $this;
    }

    public function getDateLevee(): ?\DateTimeImmutable
    {
        return $this->dateLevee;
    }

    public function setDateLevee(?\DateTimeImmutable $dateLevee): static
    {
        $this->dateLevee = $dateLevee;

        return $this;
    }

    public function getDatePlantation(): ?\DateTimeImmutable
    {
        return $this->datePlantation;
    }

    public function setDatePlantation(?\DateTimeImmutable $datePlantation): static
    {
        $this->datePlantation = $datePlantation;

        return $this;
    }

    public function getDatePlantationTheorique(): ?\DateTimeImmutable
    {
        return $this->datePlantationTheorique;
    }

    public function setDatePlantationTheorique(?\DateTimeImmutable $date): static
    {
        $this->datePlantationTheorique = $date;

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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function isEchec(): bool
    {
        return $this->echec;
    }

    public function setEchec(bool $echec): static
    {
        $this->echec = $echec;

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

    /** @return Collection<int, Rempotage> */
    public function getRempotages(): Collection
    {
        return $this->rempotages;
    }

    public function addRempotage(Rempotage $rempotage): static
    {
        if (!$this->rempotages->contains($rempotage)) {
            $this->rempotages->add($rempotage);
            $rempotage->setSemis($this);
        }

        return $this;
    }

    public function removeRempotage(Rempotage $rempotage): static
    {
        $this->rempotages->removeElement($rempotage);

        return $this;
    }
}
