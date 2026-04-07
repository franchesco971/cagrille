<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Entity\AliExpressOrder;
use Cagrille\AliExpressBundle\Entity\AliExpressOrderStatus;

/**
 * Interface repository pour les commandes AliExpress.
 *
 * Principe ISP : expose uniquement les méthodes nécessaires aux services consommateurs.
 */
interface AliExpressOrderRepositoryInterface
{
    public function save(AliExpressOrder $order, bool $flush = true): void;

    public function flush(): void;

    public function find(int $id): ?AliExpressOrder;

    /**
     * @return AliExpressOrder[]
     */
    public function findBySyliusOrderId(int $syliusOrderId): array;

    /**
     * @return AliExpressOrder[]
     */
    public function findByStatus(AliExpressOrderStatus $status): array;

    /**
     * Retourne les commandes échouées pouvant être relancées (retryCount < $maxRetries).
     *
     * @return AliExpressOrder[]
     */
    public function findRetryable(int $maxRetries = 3): array;

    /**
     * Retourne les commandes placées avec un numéro de suivi non encore synchronisé.
     *
     * @return AliExpressOrder[]
     */
    public function findPlacedWithoutTracking(): array;

    /**
     * @return AliExpressOrder[]
     */
    public function findAll(): array;

    /**
     * @return AliExpressOrder[]
     */
    public function findPaginated(int $page, int $limit): array;

    public function countAll(): int;

    public function countByStatus(AliExpressOrderStatus $status): int;
}
