<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322164323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_comic_series_cover ON comic_series (cover_url, cover_image)');
        $this->addSql('CREATE INDEX idx_comic_series_missing_tome ON comic_series (status, is_one_shot, latest_published_issue)');
        $this->addSql('CREATE INDEX idx_comic_series_release_check ON comic_series (status, latest_published_issue_complete, is_one_shot, new_releases_checked_at)');
        $this->addSql('CREATE INDEX idx_notification_type_entity ON notification (type, related_entity_type, related_entity_id, read_status)');
        $this->addSql('CREATE INDEX idx_tome_series_read ON tome (comic_series_id, `read`)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_comic_series_cover ON comic_series');
        $this->addSql('DROP INDEX idx_comic_series_missing_tome ON comic_series');
        $this->addSql('DROP INDEX idx_comic_series_release_check ON comic_series');
        $this->addSql('DROP INDEX idx_notification_type_entity ON notification');
        $this->addSql('DROP INDEX idx_tome_series_read ON tome');
    }
}
