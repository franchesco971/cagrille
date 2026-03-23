<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Command;

use Cagrille\AlibabaBundle\Contract\ProductPersistenceInterface;
use Cagrille\AlibabaBundle\Dto\ProductDto;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alibaba:test:persist',
    description: '[TEST] Persiste un produit factice pour valider SyliusProductPersistence',
)]
class TestPersistCommand extends Command
{
    public function __construct(
        private readonly ProductPersistenceInterface $persistence,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[TEST] Persistance produit Alibaba → Sylius');

        $dto = new ProductDto(
            alibabaId:     'TEST001',
            name:          'Barbecue Test Alibaba',
            description:   'Produit de test importé depuis Alibaba (commande alibaba:test:persist)',
            price:         29.99,
            currency:      'USD',
            moq:           1,
            stock:         100,
            unit:          'piece',
            images:        [],
            attributes:    [],
            categoryId:    '200000345',
            supplierId:    'supplier_test',
            supplierName:  'Test Supplier',
            originCountry: 'CN',
            updatedAt:     new \DateTimeImmutable(),
        );

        $this->persistence->upsert($dto);

        $io->success('Produit persisté ! Cherchez "alibaba_TEST001" dans l\'admin Sylius.');

        return Command::SUCCESS;
    }
}
