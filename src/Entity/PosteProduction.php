<?php

namespace App\Entity;

use App\Repository\PosteProductionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PosteProductionRepository::class)]
class PosteProduction
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
    #[Assert\Choice(choices: ['matin', 'am', 'nuit'], message: 'Doit être: matin, am ou nuit')]
    private ?string $posteType = null;

    #[ORM\ManyToOne(targetEntity: Bras::class, inversedBy: 'posteProductions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Bras $bras = null;

    #[ORM\ManyToOne(targetEntity: Poste::class, inversedBy: 'posteProductions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Poste $poste = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $realisePoste = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $targetPoste = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $targetJournee = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Ne doit pas être vide')]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou zéro')]
    private ?float $totalJournee = null;

    #[ORM\ManyToOne(targetEntity: ProductionFile::class, inversedBy: 'posteProductions')]
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

    public function getBras(): ?Bras
    {
        return $this->bras;
    }

    public function setBras(?Bras $bras): self
    {
        $this->bras = $bras;
        return $this;
    }

    public function getPoste(): ?Poste
    {
        return $this->poste;
    }

    public function setPoste(?Poste $poste): self
    {
        $this->poste = $poste;
        return $this;
    }

    public function getRealisePoste(): ?float
    {
        return $this->realisePoste;
    }

    public function setRealisePoste(float $realisePoste): self
    {
        $this->realisePoste = $realisePoste;
        return $this;
    }

    public function getTargetPoste(): ?float
    {
        return $this->targetPoste;
    }

    public function setTargetPoste(float $targetPoste): self
    {
        $this->targetPoste = $targetPoste;
        return $this;
    }

    public function getTargetJournee(): ?float
    {
        return $this->targetJournee;
    }

    public function setTargetJournee(float $targetJournee): self
    {
        $this->targetJournee = $targetJournee;
        return $this;
    }

    public function getTotalJournee(): ?float
    {
        return $this->totalJournee;
    }

    public function setTotalJournee(float $totalJournee): self
    {
        $this->totalJournee = $totalJournee;
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
        return "PosteProduction {$this->date?->format('Y-m-d')} - {$this->posteType} ({$this->id})";
    }
}