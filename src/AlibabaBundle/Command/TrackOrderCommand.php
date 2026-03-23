<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Command;

use Cagrille\AlibabaBundle\Service\TrackingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de suivi de livraison.
 * Usage : php bin/console alibaba:track:order <order_id>
 *      ou php bin/console alibaba:track:order --tracking=<number> --carrier=<code>
 */
#[AsCommand(
    name: 'alibaba:track:order',
    description: 'Affiche le suivi de livraison d\'une commande Alibaba',
)]
class TrackOrderCommand extends Command
{
    public function __construct(
        private readonly TrackingService $trackingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order_id', InputArgument::OPTIONAL, 'ID de la commande Alibaba')
            ->addOption('tracking', 't', InputOption::VALUE_OPTIONAL, 'Numéro de suivi colis')
            ->addOption('carrier', null, InputOption::VALUE_OPTIONAL, 'Code transporteur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $orderId = $input->getArgument('order_id');
        $tracking = $input->getOption('tracking');
        $carrier  = $input->getOption('carrier');

        try {
            if ($tracking !== null && $carrier !== null) {
                $trackingDto = $this->trackingService->getTrackingByParcel((string) $tracking, (string) $carrier);
            } elseif ($orderId !== null) {
                $trackingDto = $this->trackingService->getTrackingForOrder((string) $orderId);
            } else {
                $io->error('Fournir un order_id ou --tracking + --carrier');
                return Command::FAILURE;
            }

            $io->title('Suivi commande ' . $trackingDto->orderId);
            $io->definitionList(
                ['Statut' => $trackingDto->status],
                ['Transporteur' => $trackingDto->carrier],
                ['N° suivi' => $trackingDto->trackingNumber],
                ['Position actuelle' => $trackingDto->currentLocation],
                ['Livraison estimée' => $trackingDto->estimatedDelivery?->format('d/m/Y') ?? 'N/A'],
            );

            $io->section('Historique des événements');
            foreach (array_reverse($trackingDto->events) as $event) {
                $io->writeln(sprintf(
                    '  [%s] %s — %s',
                    $event->occurredAt->format('d/m/Y H:i'),
                    $event->location,
                    $event->description,
                ));
            }

        } catch (\Throwable $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
