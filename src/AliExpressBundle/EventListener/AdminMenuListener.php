<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\EventListener;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ajoute le lien "Synchronisation AliExpress" dans le menu admin Sylius.
 */
#[AsEventListener(event: MainMenuBuilder::EVENT_NAME)]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        /** @var ItemInterface|null $catalog */
        $catalog = $menu->getChild('catalog');

        if ($catalog === null) {
            return;
        }

        $catalog
            ->addChild('aliexpress_sync', ['route' => 'cagrille_admin_aliexpress_sync_index'])
            ->setLabel('Sync AliExpress')
            ->setLabelAttribute('icon', 'tabler:refresh')
        ;

        $catalog
            ->addChild('aliexpress_orders', ['route' => 'cagrille_admin_aliexpress_order_index'])
            ->setLabel('Commandes AliExpress')
            ->setLabelAttribute('icon', 'tabler:shopping-cart')
        ;
    }
}
