<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321172701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, metadata JSON DEFAULT NULL, read_status TINYINT NOT NULL, related_entity_id INT DEFAULT NULL, related_entity_type VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_notification_user_read (user_id, read_status, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification_preference (channel VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, user_id INT NOT NULL, INDEX IDX_A61B1571A76ED395 (user_id), UNIQUE INDEX uniq_pref_user_type (user_id, type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_subscription (auth_token VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, endpoint LONGTEXT NOT NULL, expiration_time DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, public_key VARCHAR(255) NOT NULL, user_id INT NOT NULL, INDEX IDX_562830F3A76ED395 (user_id), UNIQUE INDEX uniq_push_endpoint (endpoint), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_preference ADD CONSTRAINT FK_A61B1571A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification_preference DROP FOREIGN KEY FK_A61B1571A76ED395');
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY FK_562830F3A76ED395');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE notification_preference');
        $this->addSql('DROP TABLE push_subscription');
    }
}
