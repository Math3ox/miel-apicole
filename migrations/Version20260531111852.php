<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531111852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD delivery_first_name VARCHAR(255) NOT NULL, ADD delivery_last_name VARCHAR(255) NOT NULL, ADD delivery_street VARCHAR(255) NOT NULL, ADD delivery_city VARCHAR(255) NOT NULL, ADD delivery_postal_code VARCHAR(255) NOT NULL, ADD delivery_country VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP delivery_first_name, DROP delivery_last_name, DROP delivery_street, DROP delivery_city, DROP delivery_postal_code, DROP delivery_country');
    }
}
