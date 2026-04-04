<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403150030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cagrille_aliexpress_order (id INT AUTO_INCREMENT NOT NULL, sylius_order_id INT NOT NULL, sylius_order_item_id INT NOT NULL, aliexpress_order_id VARCHAR(64) DEFAULT NULL, aliexpress_product_id VARCHAR(64) NOT NULL, sku_attr VARCHAR(512) DEFAULT \'\' NOT NULL, quantity INT NOT NULL, status VARCHAR(32) NOT NULL, error_message LONGTEXT DEFAULT NULL, tracking_number VARCHAR(128) DEFAULT NULL, carrier VARCHAR(128) DEFAULT NULL, logistics_status VARCHAR(64) DEFAULT NULL, total_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, currency VARCHAR(8) DEFAULT \'USD\' NOT NULL, retry_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', placed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_aliexpress_order_sylius (sylius_order_id), INDEX idx_aliexpress_order_status (status), INDEX idx_aliexpress_order_id (aliexpress_order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cagrille_aliexpress_order');
    }
}
