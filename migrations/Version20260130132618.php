<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130132618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs author, coverUrl, description, publishedDate et publisher';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series ADD author VARCHAR(255) DEFAULT NULL, ADD cover_url VARCHAR(500) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD published_date VARCHAR(50) DEFAULT NULL, ADD publisher VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comic_series DROP author, DROP cover_url, DROP description, DROP published_date, DROP publisher');
    }
}
