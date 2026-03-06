<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306214212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des flags par défaut des tomes et de la date de MAJ de la parution';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series ADD default_tome_bought TINYINT NOT NULL, ADD default_tome_downloaded TINYINT NOT NULL, ADD default_tome_read TINYINT NOT NULL, ADD latest_published_issue_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series DROP default_tome_bought, DROP default_tome_downloaded, DROP default_tome_read, DROP latest_published_issue_updated_at');
    }
}
