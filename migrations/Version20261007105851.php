<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007105851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique du stock (stock_movement), initialisé avec le stock actuel de chaque variante';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, stock_after INT NOT NULL, type VARCHAR(20) NOT NULL, comment VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, variant_id INT NOT NULL, order_id INT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_BB1BC1B53B69A9AF (variant_id), INDEX IDX_BB1BC1B58D9F6D38 (order_id), INDEX IDX_BB1BC1B5A76ED395 (user_id), INDEX IDX_BB1BC1B58B8E8428 (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B53B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B58D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');

        // point de départ de l'historique : le stock tel qu'il est au moment de la migration
        $this->addSql("INSERT INTO stock_movement (variant_id, quantity, stock_after, type, comment, created_at)
            SELECT id, stock, stock, 'initial', 'Stock existant à la mise en place de l''historique', NOW()
            FROM product_variant WHERE stock > 0");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B53B69A9AF');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B58D9F6D38');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5A76ED395');
        $this->addSql('DROP TABLE stock_movement');
    }
}
