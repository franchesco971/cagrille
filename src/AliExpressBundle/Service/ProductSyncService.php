<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\ProductEndpointInterface;
use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Contract\ProductSyncServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de synchronisation des produits AliExpress.
 *
 * Principe SRP : gère uniquement la logique de synchronisation (pagination, logs).
 * Principe DIP : dépend de ProductPersistenceInterface, pas de Sylius directement.
 */
class ProductSyncService implements ProductSyncServiceInterface
{
    public function __construct(
        private readonly ProductEndpointInterface $productEndpoint,
        private readonly ProductPersistenceInterface $persistence,
        private readonly LoggerInterface $logger,
        /** @var array<int, string> */
        private readonly array $keywords,
        private readonly int $batchSize,
    ) {
    }

    public function syncAll(): int
    {
        $total = 0;

        foreach ($this->keywords as $keyword) {
            $total += $this->syncByKeyword($keyword);
        }

        $this->logger->info('[AliExpress] Synchronisation complète : {total} produits traités', [
            'total' => $total,
        ]);

        return $total;
    }

    public function syncByKeyword(string $keyword): int
    {
        $page = 1;
        $count = 0;

        do {
            $products = $this->productEndpoint->search($keyword, $page, $this->batchSize);

            foreach ($products as $productDto) {
                $this->persistence->upsert($productDto);
                ++$count;
            }

            ++$page;
        } while (count($products) === $this->batchSize);

        $this->logger->info('[AliExpress] Keyword "{keyword}" : {count} produits traités', [
            'keyword' => $keyword,
            'count' => $count,
        ]);

        return $count;
    }

    public function importOne(string $aliExpressItemId): void
    {
        $productDto = $this->productEndpoint->getById($aliExpressItemId);
        $this->persistence->upsert($productDto);

        $this->logger->info('[AliExpress] Produit importé : {id}', ['id' => $aliExpressItemId]);
    }
}
