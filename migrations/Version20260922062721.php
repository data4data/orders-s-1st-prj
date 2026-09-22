<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922062721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: tax categories and dated VAT rates, category tree, products with pack-size variants, attributes and options, spec values, images and documents';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribute (id INT UNSIGNED AUTO_INCREMENT NOT NULL, unit VARCHAR(20) DEFAULT NULL, is_filterable TINYINT NOT NULL, position INT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(16) NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_attribute_store_code (store_id, code), INDEX IDX_FA7AEFFBB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE attribute_option (id INT UNSIGNED AUTO_INCREMENT NOT NULL, position INT NOT NULL, value VARCHAR(120) NOT NULL, attribute_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_78672EEAB6E62EFA (attribute_id), INDEX IDX_78672EEAB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT UNSIGNED AUTO_INCREMENT NOT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, slug VARCHAR(120) NOT NULL, name VARCHAR(120) NOT NULL, parent_id INT UNSIGNED DEFAULT NULL, store_id INT UNSIGNED NOT NULL, INDEX idx_category_store_parent (store_id, parent_id, position), UNIQUE INDEX uniq_category_store_slug (store_id, slug), INDEX IDX_64C19C1727ACA70 (parent_id), INDEX IDX_64C19C1B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, brand VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, slug VARCHAR(160) NOT NULL, name VARCHAR(160) NOT NULL, tax_category_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_D34A04ADB5B48B91 (public_id), UNIQUE INDEX uniq_product_store_slug (store_id, slug), INDEX IDX_D34A04AD9DF894ED (tax_category_id), INDEX IDX_D34A04ADB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (product_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, INDEX IDX_CDFC73564584665A (product_id), INDEX IDX_CDFC735612469DE2 (category_id), PRIMARY KEY (product_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_attribute_value (id INT UNSIGNED AUTO_INCREMENT NOT NULL, value_text VARCHAR(255) DEFAULT NULL, value_number NUMERIC(12, 3) DEFAULT NULL, product_id INT UNSIGNED NOT NULL, attribute_id INT UNSIGNED NOT NULL, attribute_option_id INT UNSIGNED DEFAULT NULL, store_id INT UNSIGNED NOT NULL, INDEX idx_pav_store_option (store_id, attribute_option_id), INDEX IDX_CCC4BE1F4584665A (product_id), INDEX IDX_CCC4BE1FB6E62EFA (attribute_id), INDEX IDX_CCC4BE1F1AE56DE9 (attribute_option_id), INDEX IDX_CCC4BE1FB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_document (id INT UNSIGNED AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(16) NOT NULL, title VARCHAR(200) NOT NULL, url VARCHAR(500) NOT NULL, locale VARCHAR(10) NOT NULL, product_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_13D9E0E14584665A (product_id), INDEX IDX_13D9E0E1B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_image (id INT UNSIGNED AUTO_INCREMENT NOT NULL, url VARCHAR(500) NOT NULL, alt_text VARCHAR(200) NOT NULL, position INT NOT NULL, product_id INT UNSIGNED NOT NULL, product_variant_id INT UNSIGNED DEFAULT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_64617F034584665A (product_id), INDEX IDX_64617F03A80EF684 (product_variant_id), INDEX IDX_64617F03B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variant (id INT UNSIGNED AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, on_hand INT UNSIGNED NOT NULL, reserved INT UNSIGNED NOT NULL, is_active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, sku VARCHAR(64) NOT NULL, name VARCHAR(60) NOT NULL, volume_ml INT UNSIGNED NOT NULL, weight_g INT UNSIGNED NOT NULL, price_net INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_209AA41DB5B48B91 (public_id), UNIQUE INDEX uniq_variant_store_sku (store_id, sku), INDEX IDX_209AA41D4584665A (product_id), INDEX IDX_209AA41DB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tax_category (id INT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_E6D8B87F77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tax_rate (id INT UNSIGNED AUTO_INCREMENT NOT NULL, rate NUMERIC(5, 2) NOT NULL, valid_from DATE NOT NULL, valid_to DATE DEFAULT NULL, country_code CHAR(2) NOT NULL, tax_category_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_tax_rate_period (country_code, tax_category_id, valid_from), INDEX IDX_C36330C1F026BB7C (country_code), INDEX IDX_C36330C19DF894ED (tax_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE attribute ADD CONSTRAINT FK_FA7AEFFBB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attribute_option ADD CONSTRAINT FK_78672EEAB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attribute_option ADD CONSTRAINT FK_78672EEAB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD9DF894ED FOREIGN KEY (tax_category_id) REFERENCES tax_category (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC73564584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC735612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT FK_CCC4BE1F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT FK_CCC4BE1FB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT FK_CCC4BE1F1AE56DE9 FOREIGN KEY (attribute_option_id) REFERENCES attribute_option (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT FK_CCC4BE1FB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_document ADD CONSTRAINT FK_13D9E0E14584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_document ADD CONSTRAINT FK_13D9E0E1B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F03A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F03B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41DB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tax_rate ADD CONSTRAINT FK_C36330C1F026BB7C FOREIGN KEY (country_code) REFERENCES country (code)');
        $this->addSql('ALTER TABLE tax_rate ADD CONSTRAINT FK_C36330C19DF894ED FOREIGN KEY (tax_category_id) REFERENCES tax_category (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFBB092A811');
        $this->addSql('ALTER TABLE attribute_option DROP FOREIGN KEY FK_78672EEAB6E62EFA');
        $this->addSql('ALTER TABLE attribute_option DROP FOREIGN KEY FK_78672EEAB092A811');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1B092A811');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD9DF894ED');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB092A811');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC73564584665A');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC735612469DE2');
        $this->addSql('ALTER TABLE product_attribute_value DROP FOREIGN KEY FK_CCC4BE1F4584665A');
        $this->addSql('ALTER TABLE product_attribute_value DROP FOREIGN KEY FK_CCC4BE1FB6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_value DROP FOREIGN KEY FK_CCC4BE1F1AE56DE9');
        $this->addSql('ALTER TABLE product_attribute_value DROP FOREIGN KEY FK_CCC4BE1FB092A811');
        $this->addSql('ALTER TABLE product_document DROP FOREIGN KEY FK_13D9E0E14584665A');
        $this->addSql('ALTER TABLE product_document DROP FOREIGN KEY FK_13D9E0E1B092A811');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F03A80EF684');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F03B092A811');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41DB092A811');
        $this->addSql('ALTER TABLE tax_rate DROP FOREIGN KEY FK_C36330C1F026BB7C');
        $this->addSql('ALTER TABLE tax_rate DROP FOREIGN KEY FK_C36330C19DF894ED');
        $this->addSql('DROP TABLE attribute');
        $this->addSql('DROP TABLE attribute_option');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE product_attribute_value');
        $this->addSql('DROP TABLE product_document');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE tax_category');
        $this->addSql('DROP TABLE tax_rate');
    }
}
