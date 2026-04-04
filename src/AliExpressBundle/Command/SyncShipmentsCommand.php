<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Command;

use Cagrille\AliExpressBundle\Contract\AliExpressShipmentSyncServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise les informations de livraison AliExpress vers Sylius.
 *
 * Usage : bin/console aliexpress:shipment:sync
 */
#[AsCommand(
    name: 'aliexpress:shipment:sync',
    description: 'Synchronise le tracking AliExpress vers les expéditions Sylius',
)]
final class SyncShipmentsCommand extends Command
{
    public function __construct(
        private readonly AliExpressShipmentSyncServiceInterface $shipmentSyncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation du tracking AliExpress → Sylius');

        $count = $this->shipmentSyncService->syncAllPendingTracking();

        if ($count === 0) {
            $io->info('Aucune commande AliExpress en attente de tracking.');
        } else {
            $io->success(sprintf('%d commande(s) traitée(s).', $count));
        }

        return Command::SUCCESS;
    }
}
