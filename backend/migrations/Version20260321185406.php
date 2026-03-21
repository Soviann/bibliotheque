<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321185406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE series_suggestion (authors JSON NOT NULL, created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, source_series_id INT DEFAULT NULL, INDEX IDX_A4CCA7493AE6A252 (source_series_id), INDEX idx_suggestion_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE series_suggestion ADD CONSTRAINT FK_A4CCA7493AE6A252 FOREIGN KEY (source_series_id) REFERENCES comic_series (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE author ADD followed_for_new_series TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE series_suggestion DROP FOREIGN KEY FK_A4CCA7493AE6A252');
        $this->addSql('DROP TABLE series_suggestion');
        $this->addSql('ALTER TABLE author DROP followed_for_new_series');
    }
}
