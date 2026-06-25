<?php

namespace App\Entity;

use App\Repository\BacSaisonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Snapshot d'un Bac pour une saison donnée (module Potager).
 *
 * - Taille physique (largeur/longueur en cm) et position dans le plan (posX/posY)
 *   sont **figées** une fois la saison démarrée (immuables en cours de saison).
 * - Découpage de la grille (lignes/colonnes) reste **modifiable** tant que la
 *   saison est active.
 *
 * Modifier ou supprimer un Bac n'altère jamais les BacSaison des saisons passées
 * (immutabilité du passé).
 */
#[ORM\Entity(repositoryClass: BacSaisonRepository::class)]
class BacSaison implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bac $bac = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    /** Largeur physique figée pour la saison (cm). */
    #[ORM\Column]
    private int $largeur = 0;

    /** Longueur physique figée pour la saison (cm). */
    #[ORM\Column]
    private int $longueur = 0;

    /** Position X dans le plan du potager (figée pour la saison). */
    #[ORM\Column]
    private int $posX = 0;

    /** Position Y dans le plan du potager (figée pour la saison). */
    #[ORM\Column]
    private int $posY = 0;

    /** Nombre de lignes (découpage grille), éditable si saison active. */
    #[ORM\Column]
    private int $lignes = 1;

    /** Nombre de colonnes (découpage grille), éditable si saison active. */
    #[ORM\Column]
    private int $colonnes = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBac(): ?Bac
    {
        return $this->bac;
    }

    public function setBac(?Bac $bac): static
    {
        $this->bac = $bac;

        return $this;
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

    public function getLargeur(): int
    {
        return $this->largeur;
    }

    public function setLargeur(int $largeur): static
    {
        $this->largeur = $largeur;

        return $this;
    }

    public function getLongueur(): int
    {
        return $this->longueur;
    }

    public function setLongueur(int $longueur): static
    {
        $this->longueur = $longueur;

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

    public function getLignes(): int
    {
        return $this->lignes;
    }

    public function setLignes(int $lignes): static
    {
        $this->lignes = $lignes;

        return $this;
    }

    public function getColonnes(): int
    {
        return $this->colonnes;
    }

    public function setColonnes(int $colonnes): static
    {
        $this->colonnes = $colonnes;

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
