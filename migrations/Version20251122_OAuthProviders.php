<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add OAuth provider support for Google and Apple login
 */
final class Version20251122_OAuthProviders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_provider table for Google and Apple authentication';
    }

    public function up(Schema $schema): void
    {
        // Create oauth_provider table
        $this->addSql('
            CREATE TABLE oauth_provider (
                id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                user_id INT NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_user_id VARCHAR(255) NOT NULL,
                provider_email VARCHAR(255) DEFAULT NULL,
                provider_name VARCHAR(255) DEFAULT NULL,
                provider_photo_url VARCHAR(500) DEFAULT NULL,
                provider_data JSON DEFAULT NULL,
                access_token LONGTEXT DEFAULT NULL,
                refresh_token LONGTEXT DEFAULT NULL,
                token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                INDEX IDX_C64AFFDCA76ED395 (user_id),
                INDEX idx_provider_user (provider, provider_user_id),
                UNIQUE INDEX unique_provider_user (provider, provider_user_id),
                CONSTRAINT FK_C64AFFDCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_provider');
    }
}
