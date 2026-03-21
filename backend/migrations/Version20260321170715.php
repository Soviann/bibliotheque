<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321170715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE enrichment_log DROP FOREIGN KEY `FK_5C8F355A97A5F3D5`');
        $this->addSql('ALTER TABLE enrichment_log ADD CONSTRAINT FK_5C8F355A97A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrichment_proposal DROP FOREIGN KEY `FK_BA9F9B0097A5F3D5`');
        $this->addSql('ALTER TABLE enrichment_proposal ADD CONSTRAINT FK_BA9F9B0097A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE enrichment_log DROP FOREIGN KEY FK_5C8F355A97A5F3D5');
        $this->addSql('ALTER TABLE enrichment_log ADD CONSTRAINT `FK_5C8F355A97A5F3D5` FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
        $this->addSql('ALTER TABLE enrichment_proposal DROP FOREIGN KEY FK_BA9F9B0097A5F3D5');
        $this->addSql('ALTER TABLE enrichment_proposal ADD CONSTRAINT `FK_BA9F9B0097A5F3D5` FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
    }
}
