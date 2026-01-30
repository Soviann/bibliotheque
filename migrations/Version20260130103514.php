<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130103514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series ADD current_issue_complete TINYINT NOT NULL, ADD last_bought_complete TINYINT NOT NULL, ADD last_downloaded_complete TINYINT NOT NULL, ADD published_count_complete TINYINT NOT NULL, CHANGE last_bought last_bought INT DEFAULT NULL, CHANGE current_issue current_issue INT DEFAULT NULL, CHANGE published_count published_count INT DEFAULT NULL, CHANGE last_downloaded last_downloaded INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series DROP current_issue_complete, DROP last_bought_complete, DROP last_downloaded_complete, DROP published_count_complete, CHANGE current_issue current_issue VARCHAR(50) DEFAULT NULL, CHANGE last_bought last_bought VARCHAR(50) DEFAULT NULL, CHANGE last_downloaded last_downloaded VARCHAR(50) DEFAULT NULL, CHANGE published_count published_count VARCHAR(50) DEFAULT NULL');
    }
}
