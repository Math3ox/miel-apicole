<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryFirstName = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryLastName = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryStreet = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryCity = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryPostalCode = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryCountry = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'orderRef', orphanRemoval: true)]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeliveryFirstName(): ?string { return $this->deliveryFirstName; }
    public function setDeliveryFirstName(string $v): static { $this->deliveryFirstName = $v; return $this; }

    public function getDeliveryLastName(): ?string { return $this->deliveryLastName; }
    public function setDeliveryLastName(string $v): static { $this->deliveryLastName = $v; return $this; }

    public function getDeliveryStreet(): ?string { return $this->deliveryStreet; }
    public function setDeliveryStreet(string $v): static { $this->deliveryStreet = $v; return $this; }

    public function getDeliveryCity(): ?string { return $this->deliveryCity; }
    public function setDeliveryCity(string $v): static { $this->deliveryCity = $v; return $this; }

    public function getDeliveryPostalCode(): ?string { return $this->deliveryPostalCode; }
    public function setDeliveryPostalCode(string $v): static { $this->deliveryPostalCode = $v; return $this; }

    public function getDeliveryCountry(): ?string { return $this->deliveryCountry; }
    public function setDeliveryCountry(string $v): static { $this->deliveryCountry = $v; return $this; }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setOrderRef($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getOrderRef() === $this) {
                $orderItem->setOrderRef(null);
            }
        }

        return $this;
    }
}
