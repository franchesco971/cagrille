<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle;

use Cagrille\AlibabaBundle\DependencyInjection\AlibabaExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle principal pour l'intégration Alibaba API.
 *
 * Expose les services de synchronisation produits, commandes et logistique
 * via l'API officielle Alibaba.com International.
 */
class AlibabaBundle extends Bundle
{
    public function getContainerExtension(): AlibabaExtension
    {
        return new AlibabaExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
