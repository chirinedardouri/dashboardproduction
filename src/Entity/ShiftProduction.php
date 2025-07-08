<?php

namespace App\Entity;

use App\Repository\ShiftProductionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShiftProductionRepository::class)]
class ShiftProduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\Choice(choices: ['P1', 'P2', 'P3'], message: 'Doit être: P1, P2 ou P3')]
    private ?string $posteType = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    private ?string $ref = null;

    #[ORM\ManyToOne(targetEntity: Bras::class, inversedBy: 'shiftProductions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Bras $bras = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $objectifParPoste = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $targetParPoste = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $cadenceHoraire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $realiseParPoste = null;

    #[ORM\ManyToOne(targetEntity: ProductionFile::class, inversedBy: 'shiftProductions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?ProductionFile $productionFile = null;

    #[ORM\Column]
    private ?bool $valid = null;

    #[ORM\Column]
    private ?bool $deleted = null;

    public function __construct()
    {
        $this->valid = true;
        $this->deleted = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getPosteType(): ?string
    {
        return $this->posteType;
    }

    public function setPosteType(string $posteType): self
    {
        $this->posteType = $posteType;
        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;
        return $this;
    }

    public function getBras(): ?Bras
    {
        return $this->bras;
    }

    public function setBras(?Bras $bras): self
    {
        $this->bras = $bras;
        return $this;
    }

    public function getObjectifParPoste(): ?float
    {
        return $this->objectifParPoste;
    }

    public function setObjectifParPoste(float $objectifParPoste): self
    {
        $this->objectifParPoste = $objectifParPoste;
        return $this;
    }

    public function getTargetParPoste(): ?float
    {
        return $this->targetParPoste;
    }

    public function setTargetParPoste(float $targetParPoste): self
    {
        $this->targetParPoste = $targetParPoste;
        return $this;
    }

    public function getCadenceHoraire(): ?float
    {
        return $this->cadenceHoraire;
    }

    public function setCadenceHoraire(float $cadenceHoraire): self
    {
        $this->cadenceHoraire = $cadenceHoraire;
        return $this;
    }

    public function getRealiseParPoste(): ?float
    {
        return $this->realiseParPoste;
    }

    public function setRealiseParPoste(float $realiseParPoste): self
    {
        $this->realiseParPoste = $realiseParPoste;
        return $this;
    }

    public function getProductionFile(): ?ProductionFile
    {
        return $this->productionFile;
    }

    public function setProductionFile(?ProductionFile $productionFile): self
    {
        $this->productionFile = $productionFile;
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
        return "ShiftProduction {$this->date?->format('Y-m-d')} - {$this->posteType} - {$this->ref} ({$this->id})";
    }
}