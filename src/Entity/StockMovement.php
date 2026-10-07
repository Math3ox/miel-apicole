<?php

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\ORM\Mapping as ORM;

// une ligne de l'historique du stock : combien, pourquoi, quand et par qui
#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Index(columns: ['created_at'])]
class StockMovement
{
    public const TYPES = [
        'initial'   => 'Stock de départ',
        'sale'      => 'Vente',
        'cancel'    => 'Commande annulée',
        'restock'   => 'Réassort',
        'loss'      => 'Perte / casse',
        'inventory' => 'Inventaire',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProductVariant $variant = null;

    // variation signée : -2 pour une vente de 2 pots, +10 pour un réassort
    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private int $stockAfter = 0;

    #[ORM\Column(length: 20)]
    private string $type = 'inventory';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(ProductVariant $variant, int $quantity, string $type)
    {
        $this->variant    = $variant;
        $this->quantity   = $quantity;
        $this->type       = $type;
        $this->stockAfter = $variant->getStock();
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStockAfter(): int
    {
        return $this->stockAfter;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
