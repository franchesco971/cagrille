<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressShipmentSyncServiceInterface;
use Cagrille\AliExpressBundle\Contract\LogisticsEndpointInterface;
use Cagrille\AliExpressBundle\Entity\AliExpressOrder;
use Cagrille\AliExpressBundle\Entity\AliExpressOrderStatus;
use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;

/**
 * Service de synchronisation de la logistique AliExpress vers Sylius.
 *
 * Récupère le numéro de suivi depuis l'API AliExpress et le propage
 * dans le champ `tracking` du Shipment Sylius, permettant au client
 * de suivre sa livraison via le back-office Sylius.
 *
 * Principe SRP : gère uniquement la synchronisation du tracking.
 * Principe DIP : injecte les interfaces, pas les implémentations concrètes.
 */
final class AliExpressShipmentSyncService implements AliExpressShipmentSyncServiceInterface
{
    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private readonly LogisticsEndpointInterface $logisticsEndpoint,
        private readonly AliExpressOrderRepositoryInterface $aliExpressOrderRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncTracking(AliExpressOrder $aliExpressOrder): void
    {
        $aliExpressOrderId = $aliExpressOrder->getAliExpressOrderId();

        if ($aliExpressOrderId === null) {
            return;
        }

        $trackingNumber = $aliExpressOrder->getTrackingNumber() ?? '';

        try {
            $tracking = $this->logisticsEndpoint->getTracking($aliExpressOrderId, $trackingNumber);

            $aliExpressOrder->updateLogisticsStatus(
                trackingNumber:  $tracking->trackingNumber,
                carrier:         $tracking->carrier,
                logisticsStatus: $tracking->status,
            );

            if ($tracking->status === 'FINISH') {
                $aliExpressOrder->markAsDelivered();
            } elseif ($tracking->trackingNumber !== '') {
                $aliExpressOrder->markAsShipped(
                    $tracking->trackingNumber,
                    $tracking->carrier,
                    $tracking->status,
                );
            }

            $this->propagateToSyliusShipment($aliExpressOrder, $tracking->trackingNumber);
            $this->aliExpressOrderRepository->save($aliExpressOrder, flush: true);

            $this->logger->info('[AliExpress] Tracking synchronisé pour AliExpress#{id} : {status}', [
                'id' => $aliExpressOrderId,
                'status' => $tracking->status,
            ]);
        } catch (AliExpressApiException $e) {
            $this->logger->error('[AliExpress] Erreur tracking (code: {code}): {msg}', [
                'code' => $e->apiCode,
                'msg' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[AliExpress] Erreur inattendue lors du sync tracking : {msg}', [
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function syncAllPendingTracking(): int
    {
        $orders = $this->aliExpressOrderRepository->findPlacedWithoutTracking();
        $synced = 0;

        foreach ($orders as $aliExpressOrder) {
            $this->syncTracking($aliExpressOrder);
            ++$synced;
        }

        // Sync également les commandes déjà expédiées pour détecter la livraison
        $shipped = $this->aliExpressOrderRepository->findByStatus(AliExpressOrderStatus::Shipped);

        foreach ($shipped as $aliExpressOrder) {
            $this->syncTracking($aliExpressOrder);
            ++$synced;
        }

        $this->logger->info('[AliExpress] Sync tracking : {count} commande(s) traitée(s).', [
            'count' => $synced,
        ]);

        return $synced;
    }

    /**
     * Met à jour le tracking number sur le premier Shipment de la commande Sylius.
     * Si le Shipment est déjà trackable, on ne l'écrase pas.
     */
    private function propagateToSyliusShipment(AliExpressOrder $aliExpressOrder, string $trackingNumber): void
    {
        if ($trackingNumber === '') {
            return;
        }

        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->find($aliExpressOrder->getSyliusOrderId());

        if ($order === null) {
            return;
        }

        foreach ($order->getShipments() as $shipment) {
            /** @phpstan-ignore instanceof.alwaysTrue */
            if (!($shipment instanceof ShipmentInterface)) {
                continue;
            }

            if ($shipment->isTracked()) {
                // Déjà suivi — ne pas écraser
                continue;
            }

            $shipment->setTracking($trackingNumber);
            $this->entityManager->persist($shipment);

            $this->logger->info(
                '[AliExpress] Tracking {tracking} propagé sur le shipment #{id} (commande Sylius #{oid})',
                [
                    'tracking' => $trackingNumber,
                    'id' => $shipment->getId(),
                    'oid' => $aliExpressOrder->getSyliusOrderId(),
                ],
            );

            break; // Un seul shipment mis à jour par commande
        }

        $this->entityManager->flush();
    }
}
