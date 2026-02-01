<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime la colonne is_wishlist redondante.
 * isWishlist est maintenant calculé à partir du statut (status = 'wishlist').
 */
final class Version20260201132408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la colonne is_wishlist (redondante avec status=wishlist)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series DROP is_wishlist');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series ADD is_wishlist TINYINT NOT NULL');
    }
}
