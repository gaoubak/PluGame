<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add deliverable tracking fields and media download token usedAt field
 */
final class Version20251116000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deliverable download tracking fields to booking table and usedAt field to media_download_token table';
    }

    public function up(Schema $schema): void
    {
        // Add deliverable tracking fields to booking table
        $this->addSql('ALTER TABLE booking ADD deliverable_download_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE booking ADD deliverable_downloaded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE booking ADD deliverable_tracking_token VARCHAR(64) DEFAULT NULL');

        // Add usedAt field to media_download_token table
        $this->addSql('ALTER TABLE media_download_token ADD used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove deliverable tracking fields from booking table
        $this->addSql('ALTER TABLE booking DROP deliverable_download_requested_at');
        $this->addSql('ALTER TABLE booking DROP deliverable_downloaded_at');
        $this->addSql('ALTER TABLE booking DROP deliverable_tracking_token');

        // Remove usedAt field from media_download_token table
        $this->addSql('ALTER TABLE media_download_token DROP used_at');
    }
}
