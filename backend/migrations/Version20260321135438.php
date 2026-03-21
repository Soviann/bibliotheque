<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321135438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enrichment_log (action VARCHAR(255) NOT NULL, confidence VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, field VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, new_value JSON NOT NULL, old_value JSON DEFAULT NULL, source VARCHAR(100) NOT NULL, comic_series_id INT NOT NULL, INDEX idx_enrichment_log_series (comic_series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE enrichment_proposal (confidence VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, current_value JSON DEFAULT NULL, field VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, proposed_value JSON NOT NULL, reviewed_at DATETIME DEFAULT NULL, source VARCHAR(100) NOT NULL, status VARCHAR(255) NOT NULL, comic_series_id INT NOT NULL, INDEX IDX_BA9F9B0097A5F3D5 (comic_series_id), INDEX idx_enrichment_proposal_status (status), UNIQUE INDEX uniq_proposal_series_field_pending (comic_series_id, field, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE enrichment_log ADD CONSTRAINT FK_5C8F355A97A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
        $this->addSql('ALTER TABLE enrichment_proposal ADD CONSTRAINT FK_BA9F9B0097A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enrichment_log DROP FOREIGN KEY FK_5C8F355A97A5F3D5');
        $this->addSql('ALTER TABLE enrichment_proposal DROP FOREIGN KEY FK_BA9F9B0097A5F3D5');
        $this->addSql('DROP TABLE enrichment_log');
        $this->addSql('DROP TABLE enrichment_proposal');
    }
}
