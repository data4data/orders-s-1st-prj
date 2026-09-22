<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\Infrastructure\Fixtures\Story\MainStory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The demo data (PLAN Phase 8) is built through the real services; this checks its promises.
 */
final class MainStoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        MainStory::load();
        $this->db = self::getContainer()->get(Connection::class);
    }

    public function testEveryShopHasARangeCustomersAndOrdersInEveryPlace(): void
    {
        $shops = $this->db->fetchAllAssociative(
            'SELECT s.code,
                (SELECT COUNT(*) FROM product p WHERE p.store_id = s.id) AS products,
                (SELECT COUNT(*) FROM customer c WHERE c.store_id = s.id) AS customers,
                (SELECT COUNT(DISTINCT o.state) FROM orders o WHERE o.store_id = s.id) AS places,
                (SELECT MAX(depth) FROM (SELECT c1.store_id, CASE WHEN c3.id IS NOT NULL THEN 3 WHEN c2.id IS NOT NULL THEN 2 ELSE 1 END AS depth
                    FROM category c1 LEFT JOIN category c2 ON c2.id = c1.parent_id LEFT JOIN category c3 ON c3.id = c2.parent_id) d WHERE d.store_id = s.id) AS depth
             FROM store s ORDER BY s.code',
        );

        foreach ($shops as $shop) {
            self::assertSame(15, (int) $shop['products'], $shop['code']);
            self::assertSame(5, (int) $shop['customers'], $shop['code']);
            self::assertSame(8, (int) $shop['places'], $shop['code'].': draft, payment_pending, paid, processing, shipped, delivered, cancelled, refunded');
            self::assertSame(3, (int) $shop['depth'], $shop['code']);
        }
    }

    public function testEveryCustomerHasABillingAndADeliveryAddress(): void
    {
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM customer WHERE default_billing_address_id IS NULL OR default_shipping_address_id IS NULL'));
        self::assertGreaterThan(5, (int) $this->db->fetchOne('SELECT COUNT(*) FROM customer_address WHERE company IS NOT NULL'));
    }

    public function testStockAgreesWithTheOrders(): void
    {
        // Reserved = quantities of orders still awaiting payment; nothing negative anywhere.
        $rows = $this->db->fetchAllAssociative(
            "SELECT v.sku, v.on_hand, v.reserved,
                COALESCE((SELECT SUM(i.quantity) FROM order_item i JOIN orders o ON o.id = i.order_id WHERE i.product_variant_id = v.id AND o.state = 'payment_pending'), 0) AS pending
             FROM product_variant v",
        );
        foreach ($rows as $row) {
            self::assertGreaterThanOrEqual(0, (int) $row['on_hand'], $row['sku']);
            self::assertSame((int) $row['pending'], (int) $row['reserved'], $row['sku']);
        }
    }

    public function testPaymentsAndHistoryMatchTheOrderStates(): void
    {
        $mismatches = $this->db->fetchAllAssociative(
            "SELECT o.order_number, o.state, p.state AS payment
             FROM orders o JOIN payment p ON p.order_id = o.id
             WHERE (o.state IN ('paid', 'processing', 'shipped', 'delivered') AND p.state <> 'captured')
                OR (o.state = 'refunded' AND p.state <> 'refunded')",
        );
        self::assertSame([], $mismatches);

        // Refunds add up to the payment amount.
        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM payment p WHERE p.state = 'refunded' AND p.amount <> (SELECT SUM(r.amount) FROM payment_refund r WHERE r.payment_id = p.id)"));
        // Every placed order starts its history with checkout, oldest first.
        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM orders o WHERE o.state <> 'draft' AND (SELECT h.transition FROM order_status_history h WHERE h.order_id = o.id ORDER BY h.id LIMIT 1) <> 'checkout'"));
        // Placed over the last weeks (for the dashboard chart), never in the future.
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM orders WHERE placed_at > NOW() + INTERVAL 1 MINUTE'));
        self::assertGreaterThan(5, (int) $this->db->fetchOne('SELECT COUNT(DISTINCT DATE(placed_at)) FROM orders WHERE placed_at IS NOT NULL'));
    }
}
