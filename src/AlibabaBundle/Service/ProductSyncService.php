<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Service;

use Cagrille\AlibabaBundle\Contract\ProductEndpointInterface;
use Cagrille\AlibabaBundle\Contract\ProductPersistenceInterface;
use Cagrille\AlibabaBundle\Contract\ProductSyncServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de synchronisation des produits Alibaba.
 *
 * Principe SRP : gère uniquement la logique de synchronisation.
 * Principe DIP : dépend de ProductPersistenceInterface, pas de Sylius directement.
 */
class ProductSyncService implements ProductSyncServiceInterface
{
    public function __construct(
        private readonly ProductEndpointInterface    $productEndpoint,
        private readonly ProductPersistenceInterface $persistence,
        private readonly LoggerInterface             $logger,
        private readonly array                       $categories,
        private readonly int                         $batchSize,
    ) {
    }

    public function syncAll(): int
    {
        $total = 0;

        foreach ($this->categories as $category) {
            $total += $this->syncByCategory($category);
        }

        $this->logger->info('[Alibaba] Synchronisation complète : {total} produits traités', [
            'total' => $total,
        ]);

        return $total;
    }

    public function syncOne(string $alibabaProductId): void
    {
        $productDto = $this->productEndpoint->getById($alibabaProductId);
        $this->persistence->upsert($productDto);

        $this->logger->info('[Alibaba] Produit synchronisé : {id}', ['id' => $alibabaProductId]);
    }

    public function syncByCategory(string $categoryId): int
    {
        $page  = 1;
        $count = 0;

        do {
            $products = $this->productEndpoint->getByCategory($categoryId, $page, $this->batchSize);

            foreach ($products as $productDto) {
                $this->persistence->upsert($productDto);
                $count++;
            }

            $page++;
        } while (count($products) === $this->batchSize);

        return $count;
    }
}
