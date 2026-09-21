<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921204801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenancy core: country, store, store_domain, staff_user, store_membership, store_sequence and the Messenger queue table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE country (code CHAR(2) NOT NULL, name VARCHAR(100) NOT NULL, is_eu TINYINT NOT NULL, PRIMARY KEY (code)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE staff_user (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_super_admin TINYINT NOT NULL, UNIQUE INDEX UNIQ_6FA5A0C6B5B48B91 (public_id), UNIQUE INDEX UNIQ_6FA5A0C6E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE store (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, currency_code CHAR(3) NOT NULL, default_locale VARCHAR(10) NOT NULL, payment_gateway_code VARCHAR(32) NOT NULL, contact_email VARCHAR(180) DEFAULT NULL, logo_url VARCHAR(500) DEFAULT NULL, favicon_url VARCHAR(500) DEFAULT NULL, primary_color CHAR(7) NOT NULL, accent_color CHAR(7) NOT NULL, low_stock_threshold INT UNSIGNED NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, order_number_prefix VARCHAR(16) NOT NULL, country_code CHAR(2) NOT NULL, UNIQUE INDEX UNIQ_FF575877B5B48B91 (public_id), UNIQUE INDEX UNIQ_FF57587777153098 (code), INDEX IDX_FF575877F026BB7C (country_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE store_domain (id INT UNSIGNED AUTO_INCREMENT NOT NULL, host VARCHAR(190) NOT NULL, is_primary TINYINT NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_C3BE889DCF2713FD (host), INDEX IDX_C3BE889DB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE store_membership (id INT UNSIGNED AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, role VARCHAR(16) NOT NULL, staff_user_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_membership_staff_store (staff_user_id, store_id), INDEX IDX_A8168968FBD8C423 (staff_user_id), INDEX IDX_A8168968B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE store_sequence (next_value INT UNSIGNED NOT NULL, name VARCHAR(32) NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_6CF1A8D1B092A811 (store_id), PRIMARY KEY (store_id, name)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF575877F026BB7C FOREIGN KEY (country_code) REFERENCES country (code)');
        $this->addSql('ALTER TABLE store_domain ADD CONSTRAINT FK_C3BE889DB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_membership ADD CONSTRAINT FK_A8168968FBD8C423 FOREIGN KEY (staff_user_id) REFERENCES staff_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_membership ADD CONSTRAINT FK_A8168968B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_sequence ADD CONSTRAINT FK_6CF1A8D1B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE store DROP FOREIGN KEY FK_FF575877F026BB7C');
        $this->addSql('ALTER TABLE store_domain DROP FOREIGN KEY FK_C3BE889DB092A811');
        $this->addSql('ALTER TABLE store_membership DROP FOREIGN KEY FK_A8168968FBD8C423');
        $this->addSql('ALTER TABLE store_membership DROP FOREIGN KEY FK_A8168968B092A811');
        $this->addSql('ALTER TABLE store_sequence DROP FOREIGN KEY FK_6CF1A8D1B092A811');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE staff_user');
        $this->addSql('DROP TABLE store');
        $this->addSql('DROP TABLE store_domain');
        $this->addSql('DROP TABLE store_membership');
        $this->addSql('DROP TABLE store_sequence');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
