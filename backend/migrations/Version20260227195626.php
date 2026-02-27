<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227195626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE author (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_BDAFD8C85E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comic_series (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, latest_published_issue INT DEFAULT NULL, latest_published_issue_complete TINYINT NOT NULL, is_one_shot TINYINT NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, description LONGTEXT DEFAULT NULL, published_date VARCHAR(50) DEFAULT NULL, publisher VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX idx_comic_series_deleted_at (deleted_at), INDEX idx_comic_series_status (status), INDEX idx_comic_series_title (title), INDEX idx_comic_series_type (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comic_series_author (comic_series_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_7573E30197A5F3D5 (comic_series_id), INDEX IDX_7573E301F675F31B (author_id), PRIMARY KEY (comic_series_id, author_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tome (id INT AUTO_INCREMENT NOT NULL, bought TINYINT NOT NULL, created_at DATETIME NOT NULL, downloaded TINYINT NOT NULL, isbn VARCHAR(20) DEFAULT NULL, number INT NOT NULL, on_nas TINYINT NOT NULL, `read` TINYINT NOT NULL, title VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, comic_series_id INT NOT NULL, INDEX IDX_6B19E4F797A5F3D5 (comic_series_id), INDEX idx_tome_isbn (isbn), INDEX idx_tome_on_nas (on_nas), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comic_series_author ADD CONSTRAINT FK_7573E30197A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comic_series_author ADD CONSTRAINT FK_7573E301F675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tome ADD CONSTRAINT FK_6B19E4F797A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series_author DROP FOREIGN KEY FK_7573E30197A5F3D5');
        $this->addSql('ALTER TABLE comic_series_author DROP FOREIGN KEY FK_7573E301F675F31B');
        $this->addSql('ALTER TABLE tome DROP FOREIGN KEY FK_6B19E4F797A5F3D5');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE comic_series');
        $this->addSql('DROP TABLE comic_series_author');
        $this->addSql('DROP TABLE tome');
        $this->addSql('DROP TABLE `user`');
    }
}
