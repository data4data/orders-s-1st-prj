<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922113047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 6: order status history, payment refunds, payment webhook events';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_status_history (id INT UNSIGNED AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, transition VARCHAR(32) NOT NULL, from_state VARCHAR(32) NOT NULL, to_state VARCHAR(32) NOT NULL, actor_type VARCHAR(16) NOT NULL, actor_id INT UNSIGNED DEFAULT NULL, actor_name VARCHAR(180) DEFAULT NULL, comment VARCHAR(500) DEFAULT NULL, order_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX idx_history_order (order_id, created_at), INDEX IDX_471AD77E8D9F6D38 (order_id), INDEX IDX_471AD77EB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_refund (id INT UNSIGNED AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, amount INT UNSIGNED NOT NULL, reason VARCHAR(255) NOT NULL, state VARCHAR(16) NOT NULL, external_reference VARCHAR(120) DEFAULT NULL, payment_id INT UNSIGNED NOT NULL, store_id INT UNSIGNED NOT NULL, INDEX IDX_7E148FCE4C3A3BB (payment_id), INDEX IDX_7E148FCEB092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_webhook_event (id INT UNSIGNED AUTO_INCREMENT NOT NULL, processed_at DATETIME DEFAULT NULL, result VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, gateway_code VARCHAR(32) NOT NULL, external_event_id VARCHAR(120) NOT NULL, payload JSON NOT NULL, store_id INT UNSIGNED NOT NULL, UNIQUE INDEX uniq_webhook_gateway_event (gateway_code, external_event_id), INDEX IDX_4C7AE613B092A811 (store_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77EB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_refund ADD CONSTRAINT FK_7E148FCE4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_refund ADD CONSTRAINT FK_7E148FCEB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_webhook_event ADD CONSTRAINT FK_4C7AE613B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77E8D9F6D38');
        $this->addSql('ALTER TABLE order_status_history DROP FOREIGN KEY FK_471AD77EB092A811');
        $this->addSql('ALTER TABLE payment_refund DROP FOREIGN KEY FK_7E148FCE4C3A3BB');
        $this->addSql('ALTER TABLE payment_refund DROP FOREIGN KEY FK_7E148FCEB092A811');
        $this->addSql('ALTER TABLE payment_webhook_event DROP FOREIGN KEY FK_4C7AE613B092A811');
        $this->addSql('DROP TABLE order_status_history');
        $this->addSql('DROP TABLE payment_refund');
        $this->addSql('DROP TABLE payment_webhook_event');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
