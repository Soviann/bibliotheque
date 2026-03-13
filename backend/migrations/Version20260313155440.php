<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313155440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un index composite (comic_series_id, number) sur la table tome';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_tome_series_number ON tome (comic_series_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tome_series_number ON tome');
    }
}
