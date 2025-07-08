<?php

namespace App\Entity;

use App\Repository\PosteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PosteRepository::class)]
class Poste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    private ?string $nom = null;

    #[ORM\OneToMany(mappedBy: 'poste', targetEntity: PosteProduction::class)]
    private Collection $posteProductions;

    #[ORM\Column]
    private ?bool $valid = null;

    #[ORM\Column]
    private ?bool $deleted = null;

    public function __construct()
    {
        $this->posteProductions = new ArrayCollection();
        $this->valid = true;
        $this->deleted = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getPosteProductions(): Collection
    {
        return $this->posteProductions;
    }

    public function addPosteProduction(PosteProduction $posteProduction): self
    {
        if (!$this->posteProductions->contains($posteProduction)) {
            $this->posteProductions[] = $posteProduction;
            $posteProduction->setPoste($this);
        }
        return $this;
    }

    public function removePosteProduction(PosteProduction $posteProduction): self
    {
        if ($this->posteProductions->contains($posteProduction)) {
            $this->posteProductions->removeElement($posteProduction);
            if ($posteProduction->getPoste() === $this) {
                $posteProduction->setPoste(null);
            }
        }
        return $this;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;
        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function __toString(): string
    {
        return "$this->nom ($this->id)";
    }
}
