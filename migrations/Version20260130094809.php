<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130094809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comic_series (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, last_bought VARCHAR(50) DEFAULT NULL, current_issue VARCHAR(50) DEFAULT NULL, published_count VARCHAR(50) DEFAULT NULL, last_downloaded VARCHAR(50) DEFAULT NULL, on_nas TINYINT NOT NULL, is_wishlist TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, google_id VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE comic_series');
        $this->addSql('DROP TABLE `user`');
    }
}
