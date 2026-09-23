<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922150816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 7: contact messages';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_message (id INT UNSIGNED AUTO_INCREMENT NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(40) NOT NULL, message LONGTEXT NOT NULL, order_number VARCHAR(32) DEFAULT NULL, store_id INT UNSIGNED NOT NULL, INDEX idx_contact_store_created (store_id, created_at), INDEX IDX_2C9211FEB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FEB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FEB092A811');
        $this->addSql('DROP TABLE contact_message');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
