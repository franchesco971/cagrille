<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Commande AliExpress dropshipping générée depuis une commande Sylius.
 *
 * Lie un OrderItem Sylius à une commande AliExpress DS, avec son statut,
 * son numéro de suivi et l'historique des erreurs éventuelles.
 *
 * Principe SRP : uniquement un modèle de données — aucune logique métier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cagrille_aliexpress_order')]
#[ORM\Index(columns: ['sylius_order_id'], name: 'idx_aliexpress_order_sylius')]
#[ORM\Index(columns: ['status'], name: 'idx_aliexpress_order_status')]
#[ORM\Index(columns: ['aliexpress_order_id'], name: 'idx_aliexpress_order_id')]
#[ORM\HasLifecycleCallbacks]
class AliExpressOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    // @phpstan-ignore-next-line property.unusedType
    private ?int $id = null;

    /** Clé étrangère vers sylius_order.id */
    #[ORM\Column(name: 'sylius_order_id', type: 'integer')]
    private int $syliusOrderId;

    /** Numéro de l'item dans la commande Sylius (sylius_order_item.id) */
    #[ORM\Column(name: 'sylius_order_item_id', type: 'integer')]
    private int $syliusOrderItemId;

    /** Identifiant de la commande retourné par l'API AliExpress (nullable tant que non placée) */
    #[ORM\Column(name: 'aliexpress_order_id', type: 'string', length: 64, nullable: true)]
    private ?string $aliExpressOrderId = null;

    /** ID du produit AliExpress commandé */
    #[ORM\Column(name: 'aliexpress_product_id', type: 'string', length: 64)]
    private string $aliExpressProductId;

    /** Attributs SKU AliExpress (ex : "200000182:193;200007763:201336100") */
    #[ORM\Column(name: 'sku_attr', type: 'string', length: 512, options: ['default' => ''])]
    private string $skuAttr = '';

    /** Quantité commandée */
    #[ORM\Column(name: 'quantity', type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: AliExpressOrderStatus::class)]
    private AliExpressOrderStatus $status;

    /** Message d'erreur retourné par l'API en cas d'échec (nullable) */
    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** Numéro de suivi colis (disponible après expédition) */
    #[ORM\Column(name: 'tracking_number', type: 'string', length: 128, nullable: true)]
    private ?string $trackingNumber = null;

    /** Nom du transporteur AliExpress */
    #[ORM\Column(name: 'carrier', type: 'string', length: 128, nullable: true)]
    private ?string $carrier = null;

    /** Statut logistique retourné par l'API de suivi */
    #[ORM\Column(name: 'logistics_status', type: 'string', length: 64, nullable: true)]
    private ?string $logisticsStatus = null;

    /** Montant total de la commande AliExpress (en devise AliExpress) */
    #[ORM\Column(name: 'total_amount', type: 'float', options: ['default' => 0.0])]
    private float $totalAmount = 0.0;

    /** Devise de la commande AliExpress */
    #[ORM\Column(name: 'currency', type: 'string', length: 8, options: ['default' => 'USD'])]
    private string $currency = 'USD';

    /** Nombre de tentatives de placement */
    #[ORM\Column(name: 'retry_count', type: 'integer', options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Date/heure de placement effectif sur AliExpress */
    #[ORM\Column(name: 'placed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $placedAt = null;

    public function __construct(
        int $syliusOrderId,
        int $syliusOrderItemId,
        string $aliExpressProductId,
        int $quantity,
        string $skuAttr = '',
    ) {
        $this->syliusOrderId = $syliusOrderId;
        $this->syliusOrderItemId = $syliusOrderItemId;
        $this->aliExpressProductId = $aliExpressProductId;
        $this->quantity = $quantity;
        $this->skuAttr = $skuAttr;
        $this->status = AliExpressOrderStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Getters ─────────────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyliusOrderId(): int
    {
        return $this->syliusOrderId;
    }

    public function getSyliusOrderItemId(): int
    {
        return $this->syliusOrderItemId;
    }

    public function getAliExpressOrderId(): ?string
    {
        return $this->aliExpressOrderId;
    }

    public function getAliExpressProductId(): string
    {
        return $this->aliExpressProductId;
    }

    public function getSkuAttr(): string
    {
        return $this->skuAttr;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStatus(): AliExpressOrderStatus
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function getLogisticsStatus(): ?string
    {
        return $this->logisticsStatus;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPlacedAt(): ?\DateTimeImmutable
    {
        return $this->placedAt;
    }

    // ── Setters métier ──────────────────────────────────────────────────────

    public function markAsPlaced(string $aliExpressOrderId, float $totalAmount, string $currency): void
    {
        $this->aliExpressOrderId = $aliExpressOrderId;
        $this->status = AliExpressOrderStatus::Placed;
        $this->totalAmount = $totalAmount;
        $this->currency = $currency;
        $this->errorMessage = null;
        $this->placedAt = new \DateTimeImmutable();
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = AliExpressOrderStatus::Failed;
        $this->errorMessage = $errorMessage;
        ++$this->retryCount;
    }

    public function markAsShipped(string $trackingNumber, string $carrier, string $logisticsStatus): void
    {
        $this->status = AliExpressOrderStatus::Shipped;
        $this->trackingNumber = $trackingNumber;
        $this->carrier = $carrier;
        $this->logisticsStatus = $logisticsStatus;
    }

    public function markAsDelivered(): void
    {
        $this->status = AliExpressOrderStatus::Delivered;
    }

    public function updateLogisticsStatus(string $trackingNumber, string $carrier, string $logisticsStatus): void
    {
        $this->trackingNumber = $trackingNumber;
        $this->carrier = $carrier;
        $this->logisticsStatus = $logisticsStatus;
    }

    public function resetToPending(): void
    {
        $this->status = AliExpressOrderStatus::Pending;
        $this->errorMessage = null;
    }
}
