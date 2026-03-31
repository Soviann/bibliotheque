<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331180034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime Tome.downloaded (redondant avec onNas) et renomme ComicSeries.defaultTomeDownloaded en defaultTomeOnNas';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series CHANGE default_tome_downloaded default_tome_on_nas TINYINT NOT NULL');
        $this->addSql('ALTER TABLE tome DROP downloaded');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series CHANGE default_tome_on_nas default_tome_downloaded TINYINT NOT NULL');
        $this->addSql('ALTER TABLE tome ADD downloaded TINYINT NOT NULL');
    }
}
