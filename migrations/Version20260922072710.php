<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922072710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5: customers and addresses, orders (draft = cart) and items, coupons, shipping methods, payments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coupon (id INT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(40) NOT NULL, percent NUMERIC(5, 2) DEFAULT NULL, amount INT UNSIGNED DEFAULT NULL, min_order_net INT UNSIGNED DEFAULT NULL, valid_from DATETIME DEFAULT NULL, valid_to DATETIME DEFAULT NULL, usage_limit INT UNSIGNED DEFAULT NULL, times_used INT UNSIGNED NOT NULL, is_active TINYINT NOT NULL, type VARCHAR(16) NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_coupon_store_code (store_id, code), INDEX IDX_64BF3F02B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, phone VARCHAR(32) DEFAULT NULL, created_at DATETIME NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, default_billing_address_id INT UNSIGNED DEFAULT NULL, default_shipping_address_id INT UNSIGNED DEFAULT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_81398E09B5B48B91 (public_id), UNIQUE INDEX uniq_customer_store_email (store_id, email), INDEX IDX_81398E091995CE08 (default_billing_address_id), INDEX IDX_81398E09E4901476 (default_shipping_address_id), INDEX IDX_81398E09B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer_address (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, label VARCHAR(60) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, company VARCHAR(150) DEFAULT NULL, vat_id VARCHAR(32) DEFAULT NULL, street VARCHAR(150) NOT NULL, house_number VARCHAR(20) NOT NULL, postcode VARCHAR(16) NOT NULL, city VARCHAR(100) NOT NULL, phone VARCHAR(32) DEFAULT NULL, usable_for_billing TINYINT NOT NULL, usable_for_shipping TINYINT NOT NULL, country_code CHAR(2) NOT NULL, customer_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_1193CB3FB5B48B91 (public_id), INDEX IDX_1193CB3FF026BB7C (country_code), INDEX IDX_1193CB3F9395C3F3 (customer_id), INDEX IDX_1193CB3FB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT UNSIGNED AUTO_INCREMENT NOT NULL, sku VARCHAR(64) NOT NULL, product_name VARCHAR(160) NOT NULL, variant_name VARCHAR(80) NOT NULL, unit_price_net INT NOT NULL, discount_net INT NOT NULL, tax_rate NUMERIC(5, 2) DEFAULT NULL, line_net INT NOT NULL, line_tax INT NOT NULL, line_gross INT NOT NULL, quantity INT UNSIGNED NOT NULL, product_variant_id INT UNSIGNED DEFAULT NULL, order_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_52EA1F09A80EF684 (product_variant_id), INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F09B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, order_number VARCHAR(32) DEFAULT NULL, customer_email VARCHAR(180) DEFAULT NULL, state VARCHAR(32) NOT NULL, coupon_code VARCHAR(40) DEFAULT NULL, shipping_method_name VARCHAR(100) DEFAULT NULL, tax_country_code CHAR(2) DEFAULT NULL, items_net INT NOT NULL, discount_net INT NOT NULL, shipping_net INT NOT NULL, shipping_tax_rate NUMERIC(5, 2) DEFAULT NULL, total_net INT NOT NULL, total_tax INT NOT NULL, total_gross INT NOT NULL, placed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INT DEFAULT 1 NOT NULL, currency_code CHAR(3) NOT NULL, billing_first_name VARCHAR(100) DEFAULT NULL, billing_last_name VARCHAR(100) DEFAULT NULL, billing_company VARCHAR(150) DEFAULT NULL, billing_vat_id VARCHAR(32) DEFAULT NULL, billing_street VARCHAR(150) DEFAULT NULL, billing_house_number VARCHAR(20) DEFAULT NULL, billing_postcode VARCHAR(16) DEFAULT NULL, billing_city VARCHAR(100) DEFAULT NULL, billing_country_code CHAR(2) DEFAULT NULL, billing_phone VARCHAR(32) DEFAULT NULL, shipping_first_name VARCHAR(100) DEFAULT NULL, shipping_last_name VARCHAR(100) DEFAULT NULL, shipping_company VARCHAR(150) DEFAULT NULL, shipping_vat_id VARCHAR(32) DEFAULT NULL, shipping_street VARCHAR(150) DEFAULT NULL, shipping_house_number VARCHAR(20) DEFAULT NULL, shipping_postcode VARCHAR(16) DEFAULT NULL, shipping_city VARCHAR(100) DEFAULT NULL, shipping_country_code CHAR(2) DEFAULT NULL, shipping_phone VARCHAR(32) DEFAULT NULL, customer_id INT UNSIGNED DEFAULT NULL, coupon_id INT UNSIGNED DEFAULT NULL, shipping_method_id INT UNSIGNED DEFAULT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_E52FFDEEB5B48B91 (public_id), INDEX idx_order_store_state (store_id, state, placed_at), INDEX idx_order_store_customer (store_id, customer_id, state), UNIQUE INDEX uniq_order_store_number (store_id, order_number), INDEX IDX_E52FFDEE9395C3F3 (customer_id), INDEX IDX_E52FFDEE66C5951B (coupon_id), INDEX IDX_E52FFDEE5F7D6850 (shipping_method_id), INDEX IDX_E52FFDEEB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, state VARCHAR(32) NOT NULL, external_reference VARCHAR(120) DEFAULT NULL, checkout_url VARCHAR(500) DEFAULT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, gateway_code VARCHAR(32) NOT NULL, amount INT UNSIGNED NOT NULL, currency_code CHAR(3) NOT NULL, order_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_6D28840DB5B48B91 (public_id), INDEX idx_payment_gateway_reference (gateway_code, external_reference), INDEX IDX_6D28840D8D9F6D38 (order_id), INDEX IDX_6D28840DB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shipping_method (id INT UNSIGNED AUTO_INCREMENT NOT NULL, is_active TINYINT NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL, calculator VARCHAR(40) NOT NULL, config JSON NOT NULL, allowed_countries JSON DEFAULT NULL, position INT NOT NULL, description VARCHAR(200) DEFAULT NULL, tax_category_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_shipping_method_store_code (store_id, code), INDEX IDX_7503FF2F9DF894ED (tax_category_id), INDEX IDX_7503FF2FB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE coupon ADD CONSTRAINT FK_64BF3F02B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E091995CE08 FOREIGN KEY (default_billing_address_id) REFERENCES customer_address (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09E4901476 FOREIGN KEY (default_shipping_address_id) REFERENCES customer_address (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_1193CB3FF026BB7C FOREIGN KEY (country_code) REFERENCES country (code)');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_1193CB3F9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_1193CB3FB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE66C5951B FOREIGN KEY (coupon_id) REFERENCES coupon (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE5F7D6850 FOREIGN KEY (shipping_method_id) REFERENCES shipping_method (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_7503FF2F9DF894ED FOREIGN KEY (tax_category_id) REFERENCES tax_category (id)');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_7503FF2FB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coupon DROP FOREIGN KEY FK_64BF3F02B092A811');
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E091995CE08');
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09E4901476');
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B092A811');
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_1193CB3FF026BB7C');
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_1193CB3F9395C3F3');
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_1193CB3FB092A811');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09A80EF684');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09B092A811');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE9395C3F3');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE66C5951B');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE5F7D6850');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEB092A811');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D8D9F6D38');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DB092A811');
        $this->addSql('ALTER TABLE shipping_method DROP FOREIGN KEY FK_7503FF2F9DF894ED');
        $this->addSql('ALTER TABLE shipping_method DROP FOREIGN KEY FK_7503FF2FB092A811');
        $this->addSql('DROP TABLE coupon');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE customer_address');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE shipping_method');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
