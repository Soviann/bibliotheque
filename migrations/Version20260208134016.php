<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208134016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des index sur les colonnes fréquemment filtrées (status, type, title, isbn, on_nas)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_comic_series_status ON comic_series (status)');
        $this->addSql('CREATE INDEX idx_comic_series_title ON comic_series (title)');
        $this->addSql('CREATE INDEX idx_comic_series_type ON comic_series (type)');
        $this->addSql('CREATE INDEX idx_tome_isbn ON tome (isbn)');
        $this->addSql('CREATE INDEX idx_tome_on_nas ON tome (on_nas)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_comic_series_status ON comic_series');
        $this->addSql('DROP INDEX idx_comic_series_title ON comic_series');
        $this->addSql('DROP INDEX idx_comic_series_type ON comic_series');
        $this->addSql('DROP INDEX idx_tome_isbn ON tome');
        $this->addSql('DROP INDEX idx_tome_on_nas ON tome');
    }
}
