<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use App\Entity\Product\ProductVariant as AppProductVariant;
use Cagrille\AliExpressBundle\Contract\AliExpressOrderPlacementServiceInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Contract\OrderEndpointInterface;
use Cagrille\AliExpressBundle\Dto\OrderItemDto;
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

        if ($orderId === null) {
            $this->logger->warning('[AliExpress] placeForOrder : commande sans ID ignorée.');

            return 0;
        }

        $address = $order->getShippingAddress();

        if ($address === null) {
            $this->logger->warning('[AliExpress] Commande #{id} : adresse de livraison manquante.', ['id' => $orderId]);

            return 0;
        }

        // Collecte tous les items AliExpress de la commande
        /** @var array<array{aliOrder: AliExpressOrder, itemDto: OrderItemDto}> $collected */
        $collected = [];

        foreach ($order->getItems() as $item) {
            $aliExpressInfo = $this->extractAliExpressInfo($item);

            if ($aliExpressInfo === null) {
                continue;
            }

            $itemId = $item->getId();

            if ($itemId === null) {
                continue;
            }

            $aliOrder = new AliExpressOrder(
                syliusOrderId:       (int) $orderId,
                syliusOrderItemId:   (int) $itemId,
                aliExpressProductId: $aliExpressInfo['productId'],
                quantity:            $item->getQuantity(),
                skuAttr:             $aliExpressInfo['skuAttr'],
            );

            $this->orderRepository->save($aliOrder, flush: false);

            $collected[] = [
                'aliOrder' => $aliOrder,
                'itemDto' => new OrderItemDto(
                    productId: $aliExpressInfo['productId'],
                    quantity:  $item->getQuantity(),
                    skuAttr:   $aliExpressInfo['skuAttr'],
                ),
            ];
        }

        if ($collected === []) {
            $this->orderRepository->flush();

            return 0;
        }

        // Flush tous les AliExpressOrder en attente en une seule requête DB
        $this->orderRepository->flush();

        // Un seul appel API regroupant tous les items
        $placed = $this->doPlaceGroupedOrder($collected, (int) $orderId, $address);

        $this->logger->info('[AliExpress] Commande Sylius #{id} : {count} article(s) groupé(s) en 1 commande AliExpress.', [
            'id' => $orderId,
            'count' => count($collected),
        ]);

        return $placed ? 1 : 0;
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

    /**
     * Extrait le productId AliExpress et le skuAttr depuis un OrderItem Sylius.
     * Retourne null si l'item n'est pas un produit AliExpress.
     *
     * @return array{productId: string, skuAttr: string}|null
     */
    private function extractAliExpressInfo(OrderItemInterface $item): ?array
    {
        $variant = $item->getVariant();

        if (!($variant instanceof ProductVariantInterface)) {
            return null;
        }

        $variantCode = $variant->getCode();

        if ($variantCode === null || !str_starts_with($variantCode, self::ALIEXPRESS_PRODUCT_PREFIX)) {
            return null;
        }

        // Code format : aliexpress_<productId>_sku_<skuId>  ou  aliexpress_<productId>_default
        $withoutPrefix = substr($variantCode, strlen(self::ALIEXPRESS_PRODUCT_PREFIX));
        $parts = explode('_', $withoutPrefix);
        $productId = $parts[0] !== '' ? $parts[0] : null;

        if ($productId === null) {
            return null;
        }

        $skuAttr = $variant instanceof AppProductVariant ? $variant->getAliExpressSkuAttr() : '';

        return ['productId' => $productId, 'skuAttr' => $skuAttr];
    }

    /**
     * Envoie un unique appel API pour tous les items groupés, puis met à jour chaque entité.
     *
     * @param array<array{aliOrder: AliExpressOrder, itemDto: OrderItemDto}> $collected
     */
    private function doPlaceGroupedOrder(array $collected, int $syliusOrderId, AddressInterface $address): bool
    {
        $items = array_map(static fn (array $entry): OrderItemDto => $entry['itemDto'], $collected);

        $street = $address->getStreet() ?? '';
        $city = $address->getCity() ?? '';
        $zip = $address->getPostcode() ?? '';
        $country = $address->getCountryCode() ?? 'FR';
        $firstName = $address->getFirstName() ?? '';
        $lastName = $address->getLastName() ?? '';
        $phone = $address->getPhoneNumber() ?? '';

        $request = new OrderRequestDto(
            syliusOrderId:   (string) $syliusOrderId,
            items:           $items,
            shippingAddress: $street,
            recipientName:   trim($firstName . ' ' . $lastName),
            recipientPhone:  $phone,
            country:         $country,
            city:            $city,
            zipCode:         $zip,
            logisticsService: self::DEFAULT_LOGISTICS_SERVICE,
        );

        try {
            $dto = $this->orderEndpoint->create($request);

            foreach ($collected as $entry) {
                $entry['aliOrder']->markAsPlaced(
                    aliExpressOrderId: $dto->aliExpressOrderId,
                    totalAmount:       $dto->totalAmount / count($collected),
                    currency:          $dto->currency,
                );
                $this->orderRepository->save($entry['aliOrder'], flush: false);
            }

            $this->orderRepository->flush();

            $this->logger->info('[AliExpress] Commande groupée placée : AliExpress#{ae} ← Sylius#{sylius}', [
                'ae' => $dto->aliExpressOrderId,
                'sylius' => $syliusOrderId,
            ]);

            return true;
        } catch (AliExpressApiException $e) {
            $this->markAllFailed($collected, $e->getMessage());

            $this->logger->error('[AliExpress] Erreur API placement groupé (code: {code}) : {msg}', [
                'code' => $e->apiCode,
                'msg' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->markAllFailed($collected, $e->getMessage());

            $this->logger->error('[AliExpress] Erreur inattendue lors du placement groupé : {msg}', [
                'msg' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @param array<array{aliOrder: AliExpressOrder, itemDto: OrderItemDto}> $collected
     */
    private function markAllFailed(array $collected, string $errorMessage): void
    {
        foreach ($collected as $entry) {
            $entry['aliOrder']->markAsFailed($errorMessage);
            $this->orderRepository->save($entry['aliOrder'], flush: false);
        }

        $this->orderRepository->flush();
    }

    private function doPlaceOrder(AliExpressOrder $aliOrder): bool
    {
        $aliExpressProductId = $aliOrder->getAliExpressProductId();
        $itemDto = new OrderItemDto(
            productId: $aliExpressProductId,
            quantity:  $aliOrder->getQuantity(),
            skuAttr:   $aliOrder->getSkuAttr(),
        );

        // Pour le retry on n'a pas l'adresse en mémoire — on reconstruit une requête minimale
        // avec un item unique (même comportement qu'avant le groupement)
        $request = new OrderRequestDto(
            syliusOrderId:   (string) $aliOrder->getSyliusOrderId(),
            items:           [$itemDto],
            shippingAddress: '',
            recipientName:   '',
            recipientPhone:  '',
            country:         'FR',
            city:            '',
            zipCode:         '',
            logisticsService: self::DEFAULT_LOGISTICS_SERVICE,
        );

        try {
            $dto = $this->orderEndpoint->create($request);

            $aliOrder->markAsPlaced(
                aliExpressOrderId: $dto->aliExpressOrderId,
                totalAmount:       $dto->totalAmount,
                currency:          $dto->currency,
            );

            $this->logger->info('[AliExpress] Retry réussi : AliExpress#{ae} ← Sylius item#{item}', [
                'ae' => $dto->aliExpressOrderId,
                'item' => $aliOrder->getSyliusOrderItemId(),
            ]);
        } catch (AliExpressApiException $e) {
            $aliOrder->markAsFailed($e->getMessage());

            $this->logger->error('[AliExpress] Erreur retry (code: {code}) : {msg}', [
                'code' => $e->apiCode,
                'msg' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $aliOrder->markAsFailed($e->getMessage());

            $this->logger->error('[AliExpress] Erreur inattendue lors du retry : {msg}', [
                'msg' => $e->getMessage(),
            ]);
        }

        $this->orderRepository->save($aliOrder, flush: true);

        return $aliOrder->getStatus()->value === 'placed';
    }
}
