<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[Vich\Uploadable]
class Photo implements UserOwnedInterface
{
    /**
     * Fichier uploadé (non persisté). Vich renseigne automatiquement $path
     * (nom de fichier généré) lors du flush.
     */
    #[Vich\UploadableField(mapping: 'photo', fileNameProperty: 'path')]
    private ?File $imageFile = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vegetable $vegetable = null;

    #[ORM\ManyToOne]
    private ?Action $action = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function __toString(): string
    {
        return $this->path ?? '';
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    /**
     * Chemin relatif normalisé sous public/ (ex. "uploads/xxx.jpg"), utilisable
     * avec asset()/Liip. Gère les chemins legacy ("./uploads/12/foo.jpg") comme
     * les nouveaux fichiers Vich (nom de fichier seul).
     */
    public function getRelativePath(): ?string
    {
        if (null === $this->path || '' === $this->path) {
            return null;
        }

        if (str_contains($this->path, '/')) {
            return ltrim(str_replace('./', '', $this->path), '/');
        }

        return 'uploads/'.$this->path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getVegetable(): ?Vegetable
    {
        return $this->vegetable;
    }

    public function setVegetable(?Vegetable $vegetable): static
    {
        $this->vegetable = $vegetable;

        return $this;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): static
    {
        $this->action = $action;

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
