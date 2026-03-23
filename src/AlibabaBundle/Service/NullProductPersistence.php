<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Service;

use Cagrille\AlibabaBundle\Contract\ProductPersistenceInterface;
use Cagrille\AlibabaBundle\Dto\ProductDto;
use Psr\Log\LoggerInterface;

/**
 * Implémentation Null Object de ProductPersistenceInterface.
 *
 * Utilisée par défaut quand aucun adaptateur de persistance réel n'est configuré.
 * Permet au container de compiler et aux commandes de s'exécuter sans erreur fatale.
 * Patron Null Object (OCP) : pas de modification du code appelant nécessaire.
 *
 * Remplacer par SyliusProductPersistence une fois Sylius configuré.
 */
class NullProductPersistence implements ProductPersistenceInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function upsert(ProductDto $dto): void
    {
        $this->logger->warning(
            '[Alibaba] NullProductPersistence : aucun adaptateur de persistance configuré. '
            . 'Décommentez SyliusProductPersistence dans config/services.yaml '
            . 'après avoir configuré Sylius. Produit ignoré : {id}',
            ['id' => $dto->alibabaId]
        );
    }

    public function existsByAlibabaId(string $alibabaId): bool
    {
        return false;
    }
}
