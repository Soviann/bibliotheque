<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130155416 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tome (id INT AUTO_INCREMENT NOT NULL, bought TINYINT NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, downloaded TINYINT NOT NULL, isbn VARCHAR(20) DEFAULT NULL, number INT NOT NULL, on_nas TINYINT NOT NULL, title VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, comic_series_id INT NOT NULL, INDEX IDX_6B19E4F797A5F3D5 (comic_series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tome ADD CONSTRAINT FK_6B19E4F797A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
        $this->addSql('ALTER TABLE comic_series ADD latest_published_issue INT DEFAULT NULL, ADD latest_published_issue_complete TINYINT NOT NULL, DROP last_bought, DROP current_issue, DROP published_count, DROP last_downloaded, DROP on_nas, DROP current_issue_complete, DROP last_bought_complete, DROP last_downloaded_complete, DROP published_count_complete, DROP missing_issues, DROP owned_issues, DROP isbn');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tome DROP FOREIGN KEY FK_6B19E4F797A5F3D5');
        $this->addSql('DROP TABLE tome');
        $this->addSql('ALTER TABLE comic_series ADD current_issue INT DEFAULT NULL, ADD published_count INT DEFAULT NULL, ADD last_downloaded INT DEFAULT NULL, ADD current_issue_complete TINYINT NOT NULL, ADD last_bought_complete TINYINT NOT NULL, ADD last_downloaded_complete TINYINT NOT NULL, ADD published_count_complete TINYINT NOT NULL, ADD missing_issues VARCHAR(255) DEFAULT NULL, ADD owned_issues VARCHAR(255) DEFAULT NULL, ADD isbn VARCHAR(20) DEFAULT NULL, CHANGE latest_published_issue last_bought INT DEFAULT NULL, CHANGE latest_published_issue_complete on_nas TINYINT NOT NULL');
    }
}
