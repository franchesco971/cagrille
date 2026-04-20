<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420133319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne aliexpress_sku_attr sur sylius_product_variant pour le placement de commande dropship.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_product_variant ADD aliexpress_sku_attr VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_product_variant DROP aliexpress_sku_attr');
    }
}
