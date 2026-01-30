<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130133315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table author et la relation ManyToMany avec comic_series';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE author (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_BDAFD8C85E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comic_series_author (comic_series_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_7573E30197A5F3D5 (comic_series_id), INDEX IDX_7573E301F675F31B (author_id), PRIMARY KEY (comic_series_id, author_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comic_series_author ADD CONSTRAINT FK_7573E30197A5F3D5 FOREIGN KEY (comic_series_id) REFERENCES comic_series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comic_series_author ADD CONSTRAINT FK_7573E301F675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comic_series DROP author');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series_author DROP FOREIGN KEY FK_7573E30197A5F3D5');
        $this->addSql('ALTER TABLE comic_series_author DROP FOREIGN KEY FK_7573E301F675F31B');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE comic_series_author');
        $this->addSql('ALTER TABLE comic_series ADD author VARCHAR(255) DEFAULT NULL');
    }
}
