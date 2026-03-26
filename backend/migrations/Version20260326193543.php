<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326193543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la table enrichment_log (remplacée par enrichment_proposal)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enrichment_log DROP FOREIGN KEY `FK_5C8F355A97A5F3D5`');
        $this->addSql('DROP TABLE enrichment_log');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enrichment_log (action VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, confidence VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, field VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id INT AUTO_INCREMENT NOT NULL, new_value JSON NOT NULL, old_value JSON DEFAULT NULL, source VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, comic_series_id INT NOT NULL, INDEX idx_enrichment_log_series (comic_series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE enrichment_log ADD CONSTRAINT `FK_5C8F355A97A5F3D5` FOREIGN KEY (comic_series_id) REFERENCES comic_series (id) ON DELETE CASCADE');
    }
}
