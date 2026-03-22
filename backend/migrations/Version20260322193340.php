<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme l'index FK sur comic_series_id pour EnrichmentProposal.
 */
final class Version20260322193340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme l\'index FK comic_series_id sur enrichment_proposal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrichment_proposal RENAME INDEX idx_ba9f9b0097a5f3d5 TO idx_enrichment_proposal_series');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrichment_proposal RENAME INDEX idx_enrichment_proposal_series TO IDX_BA9F9B0097A5F3D5');
    }
}
