<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Command;

use Cagrille\AliExpressBundle\Contract\LogisticsEndpointInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Affiche le suivi logistique d'une commande AliExpress.
 *
 * Usage : bin/console aliexpress:order:track --order=123456 --tracking=JX123456789CN
 */
#[AsCommand(
    name: 'aliexpress:order:track',
    description: 'Affiche le suivi logistique d\'une commande AliExpress',
)]
class TrackOrderCommand extends Command
{
    public function __construct(
        private readonly LogisticsEndpointInterface $logisticsEndpoint,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'ID commande AliExpress')
            ->addOption('tracking', 't', InputOption::VALUE_REQUIRED, 'Numéro de suivi transporteur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $orderId     = (string) $input->getOption('order');
        $trackingNum = (string) $input->getOption('tracking');

        if ($orderId === '' || $trackingNum === '') {
            $io->error('Les options --order et --tracking sont obligatoires.');
            return Command::FAILURE;
        }

        try {
            $tracking = $this->logisticsEndpoint->getTracking($orderId, $trackingNum);

            $io->title(sprintf('Suivi commande %s', $orderId));
            $io->table(['Champ', 'Valeur'], [
                ['N° de suivi', $tracking->trackingNumber],
                ['Transporteur', $tracking->carrier],
                ['Statut',       $tracking->status],
            ]);

            if (!empty($tracking->events)) {
                $io->section('Événements de livraison');
                $rows = array_map(
                    static fn($e) => [$e->occurredAt->format('d/m/Y H:i'), $e->location, $e->description],
                    $tracking->events,
                );
                $io->table(['Date', 'Lieu', 'Description'], $rows);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
