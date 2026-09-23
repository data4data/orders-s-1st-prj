<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922151304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 7: store email notification settings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE store ADD notifications JSON DEFAULT NULL');
        $this->addSql("UPDATE store SET notifications = '{}'");
        $this->addSql('ALTER TABLE store MODIFY notifications JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE store DROP notifications');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
