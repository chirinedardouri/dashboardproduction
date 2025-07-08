<?php

namespace App\Entity;

use App\Repository\ProductionFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductionFileRepository::class)]
class ProductionFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    private ?string $filename = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $importedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'productionFile', targetEntity: PosteProduction::class)]
    private Collection $posteProductions;

    #[ORM\OneToMany(mappedBy: 'productionFile', targetEntity: ShiftProduction::class)]
    private Collection $shiftProductions;

    #[ORM\Column]
    private ?bool $valid = null;

    #[ORM\Column]
    private ?bool $deleted = null;

    public function __construct()
    {
        $this->posteProductions = new ArrayCollection();
        $this->shiftProductions = new ArrayCollection();
        $this->importedAt = new \DateTime();
        $this->valid = true;
        $this->deleted = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getImportedAt(): ?\DateTime
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTime $importedAt): self
    {
        $this->importedAt = $importedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
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
            $posteProduction->setProductionFile($this);
        }
        return $this;
    }

    public function removePosteProduction(PosteProduction $posteProduction): self
    {
        if ($this->posteProductions->contains($posteProduction)) {
            $this->posteProductions->removeElement($posteProduction);
            if ($posteProduction->getProductionFile() === $this) {
                $posteProduction->setProductionFile(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function getShiftProductions(): Collection
    {
        return $this->shiftProductions;
    }

    public function addShiftProduction(ShiftProduction $shiftProduction): self
    {
        if (!$this->shiftProductions->contains($shiftProduction)) {
            $this->shiftProductions[] = $shiftProduction;
            $shiftProduction->setProductionFile($this);
        }
        return $this;
    }

    public function removeShiftProduction(ShiftProduction $shiftProduction): self
    {
        if ($this->shiftProductions->contains($shiftProduction)) {
            $this->shiftProductions->removeElement($shiftProduction);
            if ($shiftProduction->getProductionFile() === $this) {
                $shiftProduction->setProductionFile(null);
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
        return "$this->filename ($this->id)";
    }
}