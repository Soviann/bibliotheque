<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920100440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la contrainte d\'unicité uniq_proposal_series_field_pending sur enrichment_proposal pour autoriser plusieurs propositions successives.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_proposal_series_field_pending ON enrichment_proposal');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_proposal_series_field_pending ON enrichment_proposal (comic_series_id, field, status)');
    }
}
