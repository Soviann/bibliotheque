<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rattrape les tomes manquants pour les séries dont `latest_published_issue`
 * est connu mais dont la collection `tome` est incomplète.
 *
 * Contexte : avant le fix d'`ImportService::determineMaxTomeNumber`, un import où
 * seule la colonne « Parution » était remplie définissait `latest_published_issue`
 * sans créer les tomes correspondants, déclenchant ensuite des notifications
 * « tomes manquants » en boucle.
 */
final class Version20260411143853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattrape les tomes manquants pour les séries dont la parution est connue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET SESSION max_recursive_iterations = 10000');
        $this->addSql(<<<'SQL'
            INSERT INTO tome (comic_series_id, number, bought, on_nas, `read`, is_hors_serie, created_at, updated_at)
            WITH RECURSIVE numbers AS (
                SELECT 1 AS n
                UNION ALL
                SELECT n + 1 FROM numbers WHERE n < 10000
            )
            SELECT
                cs.id,
                n.n,
                cs.default_tome_bought,
                cs.default_tome_on_nas,
                cs.default_tome_read,
                0,
                NOW(),
                NOW()
            FROM comic_series cs
            CROSS JOIN numbers n
            WHERE cs.deleted_at IS NULL
              AND cs.latest_published_issue IS NOT NULL
              AND cs.latest_published_issue > 0
              AND n.n <= cs.latest_published_issue
              AND NOT EXISTS (
                  SELECT 1 FROM tome t
                  WHERE t.comic_series_id = cs.id
                    AND t.number = n.n
                    AND t.is_hors_serie = 0
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les tomes créés ne peuvent pas être rollback automatiquement.');
    }
}
