<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531203516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute is_active, reset_token et reset_token_expires_at sur user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP is_active, DROP reset_token, DROP reset_token_expires_at');
    }
}
