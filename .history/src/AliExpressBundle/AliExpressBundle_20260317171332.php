<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle;

use Cagrille\AliExpressBundle\DependencyInjection\AliExpressExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle de dropshipping AliExpress pour Cagrille.
 *
 * Fournit les services de recherche produits, création de commandes et suivi
 * logistique via l'API officielle AliExpress Open Platform (DS API).
 *
 * Documentation API : https://developers.aliexpress-solution.com/dropshipping
 */
class AliExpressBundle extends Bundle
{
    public function getContainerExtension(): AliExpressExtension
    {
        return new AliExpressExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
