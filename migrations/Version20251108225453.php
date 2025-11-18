<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251108225453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE athlete_profiles (user_id INT NOT NULL, display_name VARCHAR(255) NOT NULL, sport VARCHAR(255) DEFAULT NULL, level VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, home_city VARCHAR(255) DEFAULT NULL, cover_photo VARCHAR(255) DEFAULT NULL, achievements JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', stats JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE availability_slot (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', creator_user_id INT NOT NULL, start_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_booked TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_1C11DC9E29FC6AE1 (creator_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE booking (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', athlete_user_id INT NOT NULL, creator_user_id INT NOT NULL, service_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', cancelled_by_user_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, start_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) NOT NULL, currency VARCHAR(10) DEFAULT \'EUR\' NOT NULL, notes LONGTEXT DEFAULT NULL, location_text VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, creator_notes LONGTEXT DEFAULT NULL, cancel_reason VARCHAR(255) DEFAULT NULL, completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', lat DOUBLE PRECISION DEFAULT NULL, lng DOUBLE PRECISION DEFAULT NULL, subtotal_cents INT NOT NULL, fee_cents INT NOT NULL, tax_cents INT DEFAULT 0 NOT NULL, total_cents INT NOT NULL, deposit_amount_cents INT DEFAULT NULL, remaining_amount_cents INT DEFAULT NULL, deposit_paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', remaining_paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', payout_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', stripe_transfer_id VARCHAR(64) DEFAULT NULL, creator_amount_cents INT DEFAULT NULL, platform_fee_cents INT DEFAULT NULL, stripe_payment_intent_id VARCHAR(64) DEFAULT NULL, stripe_subscription_id VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E00CEDDED8ACBFFA (athlete_user_id), INDEX IDX_E00CEDDE29FC6AE1 (creator_user_id), INDEX IDX_E00CEDDEED5CA9E6 (service_id), INDEX IDX_E00CEDDEE3AC1365 (cancelled_by_user_id), INDEX IDX_E00CEDDEC76F1F52 (deleted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE booking_segment (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', booking_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', slot_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', start_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_time DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', price_cents INT UNSIGNED NOT NULL, is_from_slot TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_423FB0453301C60 (booking_id), INDEX IDX_423FB04559E5119C (slot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE bookmarks (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, target_user_id INT NOT NULL, note VARCHAR(255) DEFAULT NULL, collection VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_78D2C140A76ED395 (user_id), INDEX IDX_78D2C1406C066AFE (target_user_id), UNIQUE INDEX unique_user_target (user_id, target_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment_likes (comment_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, INDEX IDX_E050D68CF8697D13 (comment_id), INDEX IDX_E050D68CA76ED395 (user_id), PRIMARY KEY(comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comments (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', post_id INT NOT NULL, user_id INT NOT NULL, parent_comment_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', deleted_by_id INT DEFAULT NULL, content LONGTEXT NOT NULL, likes_count INT DEFAULT 0 NOT NULL, replies_count INT DEFAULT 0 NOT NULL, is_deleted TINYINT(1) DEFAULT 0 NOT NULL, is_edited TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5F9E962A4B89032C (post_id), INDEX IDX_5F9E962AA76ED395 (user_id), INDEX IDX_5F9E962ABF2AF943 (parent_comment_id), INDEX IDX_5F9E962AC76F1F52 (deleted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comments_likes (comment_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, INDEX IDX_1FCA91BF8697D13 (comment_id), INDEX IDX_1FCA91BA76ED395 (user_id), PRIMARY KEY(comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', booking_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', athlete_id INT NOT NULL, creator_id INT NOT NULL, last_message_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_message_preview VARCHAR(512) DEFAULT NULL, unread_count INT DEFAULT 0 NOT NULL, archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', muted_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8A8E26E93301C60 (booking_id), INDEX IDX_8A8E26E9FE6BCB8B (athlete_id), INDEX IDX_8A8E26E961220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE creator_profiles (user_id INT NOT NULL, display_name VARCHAR(255) NOT NULL, bio LONGTEXT DEFAULT NULL, base_city VARCHAR(255) DEFAULT NULL, travel_radius_km INT DEFAULT NULL, hourly_rate_cents INT DEFAULT NULL, gear JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', specialties JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', cover_photo VARCHAR(255) DEFAULT NULL, response_time INT DEFAULT NULL, acceptance_rate DOUBLE PRECISION DEFAULT NULL, completion_rate DOUBLE PRECISION DEFAULT NULL, verified TINYINT(1) DEFAULT 0 NOT NULL, featured_work JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', avg_rating VARCHAR(255) DEFAULT NULL, ratings_count INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE deal (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', proposer_id INT NOT NULL, counterparty_id INT NOT NULL, status VARCHAR(20) NOT NULL, currency VARCHAR(3) NOT NULL, amount_cents INT UNSIGNED NOT NULL, description LONGTEXT DEFAULT NULL, stripe_product_id VARCHAR(255) DEFAULT NULL, stripe_price_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E3FEC116B13FA634 (proposer_id), INDEX IDX_E3FEC116DB1FAD05 (counterparty_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE follow (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', follower_id INT NOT NULL, following_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_68344470AC24F853 (follower_id), INDEX IDX_683444701816E3A3 (following_id), UNIQUE INDEX unique_follow (follower_id, following_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE follower (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, follower_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B9D60946A76ED395 (user_id), INDEX IDX_B9D60946AC24F853 (follower_id), UNIQUE INDEX uniq_user_follower (user_id, follower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE likes (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, liked_user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_49CA4E7DA76ED395 (user_id), INDEX IDX_49CA4E7DDD7690DF (liked_user_id), UNIQUE INDEX unique_user_target (user_id, liked_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_asset (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', owner_id INT NOT NULL, booking_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', creator_profile_user_id INT DEFAULT NULL, athlete_profile_user_id INT DEFAULT NULL, storage_key VARCHAR(1024) NOT NULL, public_url VARCHAR(2048) DEFAULT NULL, purpose VARCHAR(32) DEFAULT \'MISC\' NOT NULL, filename VARCHAR(255) NOT NULL, content_type VARCHAR(255) DEFAULT NULL, bytes BIGINT UNSIGNED NOT NULL, type VARCHAR(5) DEFAULT \'IMAGE\' NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, duration_sec INT DEFAULT NULL, thumbnail_url VARCHAR(2048) DEFAULT NULL, caption LONGTEXT DEFAULT NULL, visibility VARCHAR(12) DEFAULT \'PUBLIC\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1DB69EED7E3C61F9 (owner_id), INDEX IDX_1DB69EED3301C60 (booking_id), INDEX IDX_1DB69EEDB75F21AB (creator_profile_user_id), INDEX IDX_1DB69EED16AF512E (athlete_profile_user_id), INDEX idx_media_created (created_at), INDEX idx_media_owner_created (owner_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_download_token (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', media_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used TINYINT(1) NOT NULL, INDEX IDX_D178D9C7EA9FDD75 (media_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', conversation_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', sender_id INT NOT NULL, media_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', reply_to_message_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', deleted_by_id INT DEFAULT NULL, content LONGTEXT NOT NULL, read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B6BD307FEA9FDD75 (media_id), INDEX IDX_B6BD307F5518525D (reply_to_message_id), INDEX IDX_B6BD307FC76F1F52 (deleted_by_id), INDEX idx_message_conversation (conversation_id), INDEX idx_message_sender (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, `type` VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, data JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', read_at DATETIME DEFAULT NULL, action_url VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_notification_user (user_id), INDEX idx_notification_read (read_at), INDEX idx_notification_created (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, booking_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', promo_code_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', amount_cents INT NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, payment_method VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, payment_gateway VARCHAR(50) DEFAULT \'stripe\' NOT NULL, metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, stripe_charge_id VARCHAR(255) DEFAULT NULL, original_amount_cents INT DEFAULT NULL, discount_amount_cents INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6D28840DA76ED395 (user_id), UNIQUE INDEX UNIQ_6D28840D3301C60 (booking_id), INDEX IDX_6D28840D2FAE4625 (promo_code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payout_method (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, type VARCHAR(50) NOT NULL, bank_name VARCHAR(255) DEFAULT NULL, account_last4 VARCHAR(50) DEFAULT NULL, stripe_account_id VARCHAR(255) DEFAULT NULL, stripe_bank_account_id VARCHAR(255) DEFAULT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, is_verified TINYINT(1) DEFAULT 1 NOT NULL, metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BDC3287A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE promo_codes (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', creator_id INT NOT NULL, code VARCHAR(50) NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value INT NOT NULL, description VARCHAR(255) DEFAULT NULL, max_uses INT DEFAULT NULL, used_count INT DEFAULT 0 NOT NULL, max_uses_per_user INT DEFAULT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_active TINYINT(1) DEFAULT 1 NOT NULL, stripe_coupon_id VARCHAR(255) DEFAULT NULL, min_amount INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_C84FDDB77153098 (code), INDEX idx_promo_code (code), INDEX idx_promo_creator (creator_id), INDEX idx_promo_active (is_active), INDEX idx_promo_expires (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(128) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked TINYINT(1) DEFAULT 0 NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_9BACE7E15F37A13B (token), INDEX idx_refresh_token (token), INDEX idx_refresh_user (user_id), INDEX idx_refresh_expires (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE review (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', booking_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', reviewer_id INT NOT NULL, creator_id INT NOT NULL, deleted_by_id INT DEFAULT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_794381C63301C60 (booking_id), INDEX IDX_794381C670574616 (reviewer_id), INDEX IDX_794381C661220EA6 (creator_id), INDEX IDX_794381C6C76F1F52 (deleted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_offering (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', creator_user_id INT NOT NULL, deleted_by_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, duration_min INT DEFAULT 60 NOT NULL, price_cents INT DEFAULT 0 NOT NULL, deliverables LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, stripe_product_id VARCHAR(64) DEFAULT NULL, stripe_price_id VARCHAR(64) DEFAULT NULL, kind VARCHAR(16) DEFAULT NULL, asset_type VARCHAR(16) DEFAULT NULL, price_per_asset_cents INT DEFAULT NULL, price_total_cents INT DEFAULT NULL, includes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, featured TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5C1E718C29FC6AE1 (creator_user_id), INDEX IDX_5C1E718CC76F1F52 (deleted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_stats (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, followers_count INT DEFAULT 0 NOT NULL, following_count INT DEFAULT 0 NOT NULL, total_bookings INT DEFAULT 0 NOT NULL, completed_bookings INT DEFAULT 0 NOT NULL, total_earnings_cents BIGINT DEFAULT 0 NOT NULL, total_reviews INT DEFAULT 0 NOT NULL, average_rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL, response_time_minutes INT DEFAULT 60 NOT NULL, last_updated DATETIME NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_B5859CF2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', user_photo VARCHAR(255) DEFAULT NULL, is_verified TINYINT(1) DEFAULT 0 NOT NULL, online_status VARCHAR(16) DEFAULT \'offline\' NOT NULL, last_seen_at DATETIME DEFAULT NULL, phone_number VARCHAR(32) DEFAULT NULL, cover_photo VARCHAR(255) DEFAULT NULL, locale VARCHAR(8) DEFAULT \'en\', timezone VARCHAR(64) DEFAULT NULL, description LONGTEXT DEFAULT NULL, full_name VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, avatar_url VARCHAR(255) DEFAULT NULL, cover_url VARCHAR(255) DEFAULT NULL, sport VARCHAR(50) DEFAULT NULL, location VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, stripe_customer_id VARCHAR(64) DEFAULT NULL, stripe_payment_methods JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', stripe_account_id VARCHAR(255) DEFAULT NULL, is_plug_plus TINYINT(1) DEFAULT 0 NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallet_credit (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id INT NOT NULL, payment_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', booking_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', amount_cents INT NOT NULL, type VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, expires_at DATETIME DEFAULT NULL, is_expired TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AC61F1BAA76ED395 (user_id), INDEX IDX_AC61F1BA4C3A3BB (payment_id), INDEX IDX_AC61F1BA3301C60 (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE athlete_profiles ADD CONSTRAINT FK_56423B95A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE availability_slot ADD CONSTRAINT FK_1C11DC9E29FC6AE1 FOREIGN KEY (creator_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDED8ACBFFA FOREIGN KEY (athlete_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE29FC6AE1 FOREIGN KEY (creator_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_offering (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEE3AC1365 FOREIGN KEY (cancelled_by_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE booking_segment ADD CONSTRAINT FK_423FB0453301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_segment ADD CONSTRAINT FK_423FB04559E5119C FOREIGN KEY (slot_id) REFERENCES availability_slot (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bookmarks ADD CONSTRAINT FK_78D2C140A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmarks ADD CONSTRAINT FK_78D2C1406C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CF8697D13 FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962A4B89032C FOREIGN KEY (post_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962ABF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE comments_likes ADD CONSTRAINT FK_1FCA91BF8697D13 FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments_likes ADD CONSTRAINT FK_1FCA91BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E93301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9FE6BCB8B FOREIGN KEY (athlete_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E961220EA6 FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE creator_profiles ADD CONSTRAINT FK_DCD6A2ACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC116B13FA634 FOREIGN KEY (proposer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deal ADD CONSTRAINT FK_E3FEC116DB1FAD05 FOREIGN KEY (counterparty_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470AC24F853 FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_683444701816E3A3 FOREIGN KEY (following_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follower ADD CONSTRAINT FK_B9D60946A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follower ADD CONSTRAINT FK_B9D60946AC24F853 FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE likes ADD CONSTRAINT FK_49CA4E7DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE likes ADD CONSTRAINT FK_49CA4E7DDD7690DF FOREIGN KEY (liked_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EEDB75F21AB FOREIGN KEY (creator_profile_user_id) REFERENCES creator_profiles (user_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED16AF512E FOREIGN KEY (athlete_profile_user_id) REFERENCES athlete_profiles (user_id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media_download_token ADD CONSTRAINT FK_D178D9C7EA9FDD75 FOREIGN KEY (media_id) REFERENCES media_asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FEA9FDD75 FOREIGN KEY (media_id) REFERENCES media_asset (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F5518525D FOREIGN KEY (reply_to_message_id) REFERENCES message (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payout_method ADD CONSTRAINT FK_BDC3287A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE promo_codes ADD CONSTRAINT FK_C84FDDB61220EA6 FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C670574616 FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C661220EA6 FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE service_offering ADD CONSTRAINT FK_5C1E718C29FC6AE1 FOREIGN KEY (creator_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_offering ADD CONSTRAINT FK_5C1E718CC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_stats ADD CONSTRAINT FK_B5859CF2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wallet_credit ADD CONSTRAINT FK_AC61F1BAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wallet_credit ADD CONSTRAINT FK_AC61F1BA4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wallet_credit ADD CONSTRAINT FK_AC61F1BA3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE athlete_profiles DROP FOREIGN KEY FK_56423B95A76ED395');
        $this->addSql('ALTER TABLE availability_slot DROP FOREIGN KEY FK_1C11DC9E29FC6AE1');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDED8ACBFFA');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE29FC6AE1');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEED5CA9E6');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEE3AC1365');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEC76F1F52');
        $this->addSql('ALTER TABLE booking_segment DROP FOREIGN KEY FK_423FB0453301C60');
        $this->addSql('ALTER TABLE booking_segment DROP FOREIGN KEY FK_423FB04559E5119C');
        $this->addSql('ALTER TABLE bookmarks DROP FOREIGN KEY FK_78D2C140A76ED395');
        $this->addSql('ALTER TABLE bookmarks DROP FOREIGN KEY FK_78D2C1406C066AFE');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CF8697D13');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CA76ED395');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962A4B89032C');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA76ED395');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962ABF2AF943');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AC76F1F52');
        $this->addSql('ALTER TABLE comments_likes DROP FOREIGN KEY FK_1FCA91BF8697D13');
        $this->addSql('ALTER TABLE comments_likes DROP FOREIGN KEY FK_1FCA91BA76ED395');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E93301C60');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9FE6BCB8B');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E961220EA6');
        $this->addSql('ALTER TABLE creator_profiles DROP FOREIGN KEY FK_DCD6A2ACA76ED395');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC116B13FA634');
        $this->addSql('ALTER TABLE deal DROP FOREIGN KEY FK_E3FEC116DB1FAD05');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_68344470AC24F853');
        $this->addSql('ALTER TABLE follow DROP FOREIGN KEY FK_683444701816E3A3');
        $this->addSql('ALTER TABLE follower DROP FOREIGN KEY FK_B9D60946A76ED395');
        $this->addSql('ALTER TABLE follower DROP FOREIGN KEY FK_B9D60946AC24F853');
        $this->addSql('ALTER TABLE likes DROP FOREIGN KEY FK_49CA4E7DA76ED395');
        $this->addSql('ALTER TABLE likes DROP FOREIGN KEY FK_49CA4E7DDD7690DF');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EED7E3C61F9');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EED3301C60');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EEDB75F21AB');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EED16AF512E');
        $this->addSql('ALTER TABLE media_download_token DROP FOREIGN KEY FK_D178D9C7EA9FDD75');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FEA9FDD75');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F5518525D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FC76F1F52');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D3301C60');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2FAE4625');
        $this->addSql('ALTER TABLE payout_method DROP FOREIGN KEY FK_BDC3287A76ED395');
        $this->addSql('ALTER TABLE promo_codes DROP FOREIGN KEY FK_C84FDDB61220EA6');
        $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_9BACE7E1A76ED395');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C63301C60');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C670574616');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C661220EA6');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6C76F1F52');
        $this->addSql('ALTER TABLE service_offering DROP FOREIGN KEY FK_5C1E718C29FC6AE1');
        $this->addSql('ALTER TABLE service_offering DROP FOREIGN KEY FK_5C1E718CC76F1F52');
        $this->addSql('ALTER TABLE user_stats DROP FOREIGN KEY FK_B5859CF2A76ED395');
        $this->addSql('ALTER TABLE wallet_credit DROP FOREIGN KEY FK_AC61F1BAA76ED395');
        $this->addSql('ALTER TABLE wallet_credit DROP FOREIGN KEY FK_AC61F1BA4C3A3BB');
        $this->addSql('ALTER TABLE wallet_credit DROP FOREIGN KEY FK_AC61F1BA3301C60');
        $this->addSql('DROP TABLE athlete_profiles');
        $this->addSql('DROP TABLE availability_slot');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE booking_segment');
        $this->addSql('DROP TABLE bookmarks');
        $this->addSql('DROP TABLE comment_likes');
        $this->addSql('DROP TABLE comments');
        $this->addSql('DROP TABLE comments_likes');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE creator_profiles');
        $this->addSql('DROP TABLE deal');
        $this->addSql('DROP TABLE follow');
        $this->addSql('DROP TABLE follower');
        $this->addSql('DROP TABLE likes');
        $this->addSql('DROP TABLE media_asset');
        $this->addSql('DROP TABLE media_download_token');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payout_method');
        $this->addSql('DROP TABLE promo_codes');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE service_offering');
        $this->addSql('DROP TABLE user_stats');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE wallet_credit');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
