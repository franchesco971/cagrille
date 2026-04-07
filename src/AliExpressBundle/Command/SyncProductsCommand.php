<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Command;

use Cagrille\AliExpressBundle\Contract\ProductSyncServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise les produits AliExpress vers le catalogue Sylius.
 *
 * Usages :
 *   bin/console aliexpress:sync:products
 *   bin/console aliexpress:sync:products --keyword=barbecue
 *   bin/console aliexpress:sync:products --item=1234567890
 */
#[AsCommand(
    name: 'aliexpress:sync:products',
    description: 'Synchronise les produits AliExpress vers le catalogue Sylius',
)]
class SyncProductsCommand extends Command
{
    public function __construct(
        private readonly ProductSyncServiceInterface $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('keyword', 'k', InputOption::VALUE_OPTIONAL, 'Mot-clé de recherche AliExpress')
            ->addOption('item', 'i', InputOption::VALUE_OPTIONAL, 'Item ID AliExpress à importer individuellement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation AliExpress → Sylius');

        /** @var string|null $itemId */
        $itemId = $input->getOption('item');
        /** @var string|null $keyword */
        $keyword = $input->getOption('keyword');

        try {
            if ($itemId !== null) {
                $this->syncService->importOne((string) $itemId);
                $io->success(sprintf('Produit %s importé.', $itemId));

                return Command::SUCCESS;
            }

            if ($keyword !== null) {
                $count = $this->syncService->syncByKeyword((string) $keyword);
                $io->success(sprintf('%d produits synchronisés pour "%s".', $count, $keyword));

                return Command::SUCCESS;
            }

            $io->section('Synchronisation complète (tous les mots-clés configurés)...');
            $count = $this->syncService->syncAll();
            $io->success(sprintf('%d produits synchronisés au total.', $count));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
