<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315115920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute isHorsSerie sur Tome et notInterestedBuy/notInterestedNas sur ComicSeries';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series ADD not_interested_buy TINYINT NOT NULL, ADD not_interested_nas TINYINT NOT NULL');
        $this->addSql('DROP INDEX idx_tome_series_number ON tome');
        $this->addSql('ALTER TABLE tome ADD is_hors_serie TINYINT NOT NULL');
        $this->addSql('CREATE INDEX idx_tome_series_number ON tome (comic_series_id, is_hors_serie, number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series DROP not_interested_buy, DROP not_interested_nas');
        $this->addSql('DROP INDEX idx_tome_series_number ON tome');
        $this->addSql('ALTER TABLE tome DROP is_hors_serie');
        $this->addSql('CREATE INDEX idx_tome_series_number ON tome (comic_series_id, number)');
    }
}
