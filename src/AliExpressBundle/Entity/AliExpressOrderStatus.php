<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Entity;

/**
 * Statuts possibles d'une commande AliExpress générée depuis Sylius.
 */
enum AliExpressOrderStatus: string
{
    /** En attente de placement (commande Sylius payée, AliExpress pas encore sollicitée) */
    case Pending = 'pending';

    /** Commande passée avec succès sur AliExpress */
    case Placed = 'placed';

    /** Erreur lors du placement (voir errorMessage) */
    case Failed = 'failed';

    /** Commande expédiée — numéro de suivi disponible */
    case Shipped = 'shipped';

    /** Commande livrée */
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Placed => 'Passée',
            self::Failed => 'Échouée',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Placed => 'bg-info',
            self::Failed => 'bg-danger',
            self::Shipped => 'bg-warning',
            self::Delivered => 'bg-success',
        };
    }
}
