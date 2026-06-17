<?php

namespace App\Entity;

use App\Repository\VegetableRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VegetableRepository::class)]
class Vegetable implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $addDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $parent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typeOrigine = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'porte_greffe')]
    private ?PorteGreffe $porteGreffe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lieu_origine')]
    private ?Lieu $lieuOrigine = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomLatin = null;

    #[ORM\Column(nullable: true)]
    private ?int $rusticite = null;

    #[ORM\Column(nullable: true)]
    private ?int $moisFructiDebut = null;

    #[ORM\Column(nullable: true)]
    private ?int $moisFructiFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $moisFleurDebut = null;

    #[ORM\Column(nullable: true)]
    private ?int $moisFleurFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pFleur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pFructi = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'default_photo')]
    private ?Photo $defaultPhoto = null;

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

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): static
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getAddDate(): ?\DateTimeInterface
    {
        return $this->addDate;
    }

    public function setAddDate(\DateTimeInterface $addDate): static
    {
        $this->addDate = $addDate;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getTypeOrigine(): ?string
    {
        return $this->typeOrigine;
    }

    public function setTypeOrigine(?string $typeOrigine): static
    {
        $this->typeOrigine = $typeOrigine;

        return $this;
    }

    public function getPorteGreffe(): ?PorteGreffe
    {
        return $this->porteGreffe;
    }

    public function setPorteGreffe(?PorteGreffe $porteGreffe): static
    {
        $this->porteGreffe = $porteGreffe;

        return $this;
    }

    public function getLieuOrigine(): ?Lieu
    {
        return $this->lieuOrigine;
    }

    public function setLieuOrigine(?Lieu $lieuOrigine): static
    {
        $this->lieuOrigine = $lieuOrigine;

        return $this;
    }

    public function getNomLatin(): ?string
    {
        return $this->nomLatin;
    }

    public function setNomLatin(?string $nomLatin): static
    {
        $this->nomLatin = $nomLatin;

        return $this;
    }

    public function getRusticite(): ?int
    {
        return $this->rusticite;
    }

    public function setRusticite(?int $rusticite): static
    {
        $this->rusticite = $rusticite;

        return $this;
    }

    public function getMoisFructiDebut(): ?int
    {
        return $this->moisFructiDebut;
    }

    public function setMoisFructiDebut(?int $moisFructiDebut): static
    {
        $this->moisFructiDebut = $moisFructiDebut;

        return $this;
    }

    public function getMoisFructiFin(): ?int
    {
        return $this->moisFructiFin;
    }

    public function setMoisFructiFin(?int $moisFructiFin): static
    {
        $this->moisFructiFin = $moisFructiFin;

        return $this;
    }

    public function getMoisFleurDebut(): ?int
    {
        return $this->moisFleurDebut;
    }

    public function setMoisFleurDebut(?int $moisFleurDebut): static
    {
        $this->moisFleurDebut = $moisFleurDebut;

        return $this;
    }

    public function getMoisFleurFin(): ?int
    {
        return $this->moisFleurFin;
    }

    public function setMoisFleurFin(?int $moisFleurFin): static
    {
        $this->moisFleurFin = $moisFleurFin;

        return $this;
    }

    public function getPFleur(): ?string
    {
        return $this->pFleur;
    }

    public function setPFleur(?string $pFleur): static
    {
        $this->pFleur = $pFleur;

        return $this;
    }

    public function getPFructi(): ?string
    {
        return $this->pFructi;
    }

    public function setPFructi(?string $pFructi): static
    {
        $this->pFructi = $pFructi;

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

    public function getDefaultPhoto(): ?Photo
    {
        return $this->defaultPhoto;
    }

    public function setDefaultPhoto(?Photo $defaultPhoto): static
    {
        $this->defaultPhoto = $defaultPhoto;

        return $this;
    }
}
