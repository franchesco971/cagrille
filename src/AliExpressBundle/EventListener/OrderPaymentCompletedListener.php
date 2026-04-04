<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\EventListener;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderPlacementServiceInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Déclenche la création des commandes AliExpress lorsqu'une commande Sylius
 * est payée (transition workflow `sylius_order_payment.completed.pay`).
 *
 * Principe SRP : gère uniquement le branchement entre l'événement Sylius et le service.
 * Priorité 50 : s'exécute après la résolution d'état Sylius (priorité 100 et 200).
 */
final class OrderPaymentCompletedListener
{
    public function __construct(
        private readonly AliExpressOrderPlacementServiceInterface $placementService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $count = $this->placementService->placeForOrder($order);

        if ($count > 0) {
            $this->logger->info(
                '[AliExpress] {count} commande(s) AliExpress déclenchée(s) suite au paiement de la commande Sylius #{id}.',
                [
                    'count' => $count,
                    'id' => $order->getNumber(),
                ],
            );
        }
    }
}
