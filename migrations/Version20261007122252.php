<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007122252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement Stripe : stripe_session_id et paid_at sur order';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD stripe_session_id VARCHAR(255) DEFAULT NULL, ADD paid_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F52993981A314A57 ON `order` (stripe_session_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_F52993981A314A57 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP stripe_session_id, DROP paid_at');
    }
}
