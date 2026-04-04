<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderPlacementServiceInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Contract\OrderEndpointInterface;
use Cagrille\AliExpressBundle\Dto\OrderRequestDto;
use Cagrille\AliExpressBundle\Entity\AliExpressOrder;
use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Service de placement des commandes AliExpress depuis une commande Sylius.
 *
 * Principe SRP : gère uniquement la logique de passage de commande dropship.
 * Principe DIP : injecte OrderEndpointInterface (pas le client HTTP directement).
 * Principe OCP : les produits non-AliExpress sont ignorés sans modification du service.
 */
final class AliExpressOrderPlacementService implements AliExpressOrderPlacementServiceInterface
{
    private const ALIEXPRESS_PRODUCT_PREFIX = 'aliexpress_';

    private const DEFAULT_LOGISTICS_SERVICE = 'CAINIAO_STANDARD';

    public function __construct(
        private readonly OrderEndpointInterface $orderEndpoint,
        private readonly AliExpressOrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function placeForOrder(OrderInterface $order): int
    {
        $orderId = $order->getId();
        $created = 0;

        if ($orderId === null) {
            $this->logger->warning('[AliExpress] placeForOrder : commande sans ID ignorée.');

            return 0;
        }

        $address = $order->getShippingAddress();

        if ($address === null) {
            $this->logger->warning('[AliExpress] Commande #{id} : adresse de livraison manquante.', ['id' => $orderId]);

            return 0;
        }

        foreach ($order->getItems() as $item) {
            $aliExpressProductId = $this->extractAliExpressProductId($item);

            if ($aliExpressProductId === null) {
                continue;
            }

            $created += $this->placeItemOrder((int) $orderId, $item, $aliExpressProductId, $address);
        }

        $this->logger->info('[AliExpress] Commande Sylius #{id} : {count} commande(s) AliExpress créée(s).', [
            'id' => $orderId,
            'count' => $created,
        ]);

        return $created;
    }

    public function retry(int $aliExpressOrderId): bool
    {
        $aliOrder = $this->orderRepository->find($aliExpressOrderId);

        if ($aliOrder === null) {
            $this->logger->warning('[AliExpress] Retry : commande AliExpress #{id} introuvable.', [
                'id' => $aliExpressOrderId,
            ]);

            return false;
        }

        $aliOrder->resetToPending();
        $this->orderRepository->save($aliOrder, flush: false);

        return $this->doPlaceOrder($aliOrder);
    }

    // ── Privé ────────────────────────────────────────────────────────────────

    private function extractAliExpressProductId(OrderItemInterface $item): ?string
    {
        $variant = $item->getVariant();

        if (!($variant instanceof ProductVariantInterface)) {
            return null;
        }

        $variantCode = $variant->getCode();

        if ($variantCode === null || !str_starts_with($variantCode, self::ALIEXPRESS_PRODUCT_PREFIX)) {
            return null;
        }

        // Code format : aliexpress_<productId>_default  (ou aliexpress_<productId>)
        $withoutPrefix = substr($variantCode, strlen(self::ALIEXPRESS_PRODUCT_PREFIX));
        $parts = explode('_', $withoutPrefix);

        return $parts[0] !== '' ? $parts[0] : null;
    }

    /**
     * @return int 1 si succès, 0 sinon
     */
    private function placeItemOrder(
        int $syliusOrderId,
        OrderItemInterface $item,
        string $aliExpressProductId,
        AddressInterface $address,
    ): int {
        $itemId = $item->getId();

        if ($itemId === null) {
            return 0;
        }

        $aliOrder = new AliExpressOrder(
            syliusOrderId:      $syliusOrderId,
            syliusOrderItemId:  (int) $itemId,
            aliExpressProductId: $aliExpressProductId,
            quantity:           $item->getQuantity(),
        );

        $this->orderRepository->save($aliOrder, flush: true);

        $success = $this->doPlaceOrder($aliOrder, $address);

        return $success ? 1 : 0;
    }

    private function doPlaceOrder(AliExpressOrder $aliOrder, ?AddressInterface $address = null): bool
    {
        try {
            $request = $this->buildOrderRequest($aliOrder, $address);
            $dto = $this->orderEndpoint->create($request);

            $aliOrder->markAsPlaced(
                aliExpressOrderId: $dto->aliExpressOrderId,
                totalAmount:       $dto->totalAmount,
                currency:          $dto->currency,
            );

            $this->logger->info('[AliExpress] Commande placée : AliExpress#{ae} ← Sylius item#{item}', [
                'ae' => $dto->aliExpressOrderId,
                'item' => $aliOrder->getSyliusOrderItemId(),
            ]);
        } catch (AliExpressApiException $e) {
            $aliOrder->markAsFailed($e->getMessage());

            $this->logger->error('[AliExpress] Erreur placement (code: {code}) : {msg}', [
                'code' => $e->apiCode,
                'msg' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $aliOrder->markAsFailed($e->getMessage());

            $this->logger->error('[AliExpress] Erreur inattendue lors du placement : {msg}', [
                'msg' => $e->getMessage(),
            ]);
        }

        $this->orderRepository->save($aliOrder, flush: true);

        return $aliOrder->getStatus()->value === 'placed';
    }

    private function buildOrderRequest(AliExpressOrder $aliOrder, ?AddressInterface $address = null): OrderRequestDto
    {
        $street = $address?->getStreet() ?? '';
        $city = $address?->getCity() ?? '';
        $zip = $address?->getPostcode() ?? '';
        $country = $address?->getCountryCode() ?? 'FR';
        $firstName = $address?->getFirstName() ?? '';
        $lastName = $address?->getLastName() ?? '';
        $phone = $address?->getPhoneNumber() ?? '';

        return new OrderRequestDto(
            syliusOrderId:    (string) $aliOrder->getSyliusOrderId(),
            productId:        $aliOrder->getAliExpressProductId(),
            quantity:         $aliOrder->getQuantity(),
            skuAttr:          $aliOrder->getSkuAttr(),
            shippingAddress:  $street,
            recipientName:    trim($firstName . ' ' . $lastName),
            recipientPhone:   $phone,
            country:          $country,
            city:             $city,
            zipCode:          $zip,
            logisticsService: self::DEFAULT_LOGISTICS_SERVICE,
        );
    }
}
