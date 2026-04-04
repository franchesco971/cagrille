<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Command;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderPlacementServiceInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Relance les commandes AliExpress échouées.
 *
 * Usage :
 *   bin/console aliexpress:order:retry               # toutes les commandes relançables (max 3 essais)
 *   bin/console aliexpress:order:retry --id=42       # relance une commande spécifique
 *   bin/console aliexpress:order:retry --max-retries=5
 */
#[AsCommand(
    name: 'aliexpress:order:retry',
    description: 'Relance les commandes AliExpress échouées',
)]
final class RetryFailedOrdersCommand extends Command
{
    public function __construct(
        private readonly AliExpressOrderPlacementServiceInterface $placementService,
        private readonly AliExpressOrderRepositoryInterface $orderRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'ID interne de la commande AliExpress à relancer')
            ->addOption('max-retries', null, InputOption::VALUE_REQUIRED, 'Nombre max de tentatives déjà effectuées (défaut : 3)', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rawId = $input->getOption('id');
        $rawMax = $input->getOption('max-retries');
        $maxRetries = is_string($rawMax) && ctype_digit($rawMax) ? (int) $rawMax : 3;

        // ── Relance d'une commande spécifique ─────────────────────────────
        if (is_string($rawId) && $rawId !== '') {
            if (!ctype_digit($rawId)) {
                $io->error('L\'option --id doit être un entier.');

                return Command::FAILURE;
            }

            $success = $this->placementService->retry((int) $rawId);

            if ($success) {
                $io->success(sprintf('Commande AliExpress #%s relancée avec succès.', $rawId));
            } else {
                $io->error(sprintf('Échec lors de la relance de la commande AliExpress #%s.', $rawId));
            }

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        // ── Relance en masse ───────────────────────────────────────────────
        $retryable = $this->orderRepository->findRetryable($maxRetries);

        if (empty($retryable)) {
            $io->info('Aucune commande AliExpress à relancer.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d commande(s) AliExpress à relancer', count($retryable)));

        $succeeded = 0;
        $failed = 0;

        foreach ($retryable as $order) {
            $success = $this->placementService->retry((int) $order->getId());

            $status = $order->getStatus()->getLabel();
            $aeId = $order->getAliExpressOrderId() ?? '—';

            if ($success) {
                ++$succeeded;
                $io->writeln(sprintf(
                    '  <info>✓</info> #%d → AliExpress %s [%s]',
                    (int) $order->getId(),
                    $aeId,
                    $status,
                ));
            } else {
                ++$failed;
                $io->writeln(sprintf(
                    '  <error>✗</error> #%d → %s',
                    (int) $order->getId(),
                    $order->getErrorMessage() ?? 'Erreur inconnue',
                ));
            }
        }

        $io->newLine();
        $io->table(['Statut', 'Nombre'], [
            ['Succès',  $succeeded],
            ['Échecs',  $failed],
            ['Total',   count($retryable)],
        ]);

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
