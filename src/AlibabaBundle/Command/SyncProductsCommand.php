<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Command;

use Cagrille\AlibabaBundle\Contract\ProductSyncServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de synchronisation des produits Alibaba vers Sylius.
 * Usage : php bin/console alibaba:sync:products [--category=<id>] [--product=<id>]
 */
#[AsCommand(
    name: 'alibaba:sync:products',
    description: 'Synchronise les produits Alibaba vers le catalogue Sylius',
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
            ->addOption('category', 'c', InputOption::VALUE_OPTIONAL, 'ID de catégorie Alibaba à synchroniser')
            ->addOption('product', 'p', InputOption::VALUE_OPTIONAL, 'ID produit Alibaba spécifique à synchroniser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation Alibaba → Sylius');

        $productId  = $input->getOption('product');
        $categoryId = $input->getOption('category');

        try {
            if ($productId !== null) {
                $this->syncService->syncOne((string) $productId);
                $io->success(sprintf('Produit %s synchronisé avec succès.', $productId));
                return Command::SUCCESS;
            }

            if ($categoryId !== null) {
                $count = $this->syncService->syncByCategory((string) $categoryId);
                $io->success(sprintf('%d produits synchronisés depuis la catégorie %s.', $count, $categoryId));
                return Command::SUCCESS;
            }

            $io->section('Synchronisation complète toutes catégories...');
            $count = $this->syncService->syncAll();
            $io->success(sprintf('Synchronisation complète : %d produits traités.', $count));

        } catch (\Throwable $e) {
            $io->error('Erreur lors de la synchronisation : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
