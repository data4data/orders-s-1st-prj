<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenancy;

use App\Application\Tenancy\Exception\MissingTenantException;
use App\Application\Tenancy\Exception\ReadOnlyTenantException;
use App\Entity\Store;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Tenancy\TenantContext;
use App\Tests\Fixtures\Entity\TenantNote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Proves the single-database isolation: a store only ever sees and writes its own rows.
 */
final class TenantIsolationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private EntityManagerInterface $entityManager;
    private TenantContext $tenantContext;
    private Store $storeA;
    private Store $storeB;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->tenantContext = self::getContainer()->get(TenantContext::class);

        $this->storeA = StoreFactory::createOne(['code' => 'store-a']);
        $this->storeB = StoreFactory::createOne(['code' => 'store-b']);

        $this->addNote($this->storeA, 'note of A');
        $this->addNote($this->storeB, 'note of B');
    }

    public function testAStoreSeesOnlyItsOwnRows(): void
    {
        $this->tenantContext->useStore($this->storeA);

        self::assertSame(['note of A'], $this->noteTexts());
    }

    public function testQueryBuilderQueriesAreScopedToo(): void
    {
        $this->tenantContext->useStore($this->storeB);
        $this->entityManager->clear();

        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')->from(TenantNote::class, 'n')
            ->getQuery()->getSingleScalarResult();

        self::assertSame(1, (int) $count);
    }

    public function testWithoutAStoreNothingIsVisible(): void
    {
        $this->tenantContext->clear();

        self::assertSame([], $this->noteTexts());
    }

    public function testPlatformModeSeesEveryStore(): void
    {
        $texts = $this->tenantContext->runAsPlatform(fn (): array => $this->noteTexts());

        self::assertEqualsCanonicalizing(['note of A', 'note of B'], $texts);
    }

    public function testRunAsStoreRestoresThePreviousStore(): void
    {
        $this->tenantContext->useStore($this->storeA);

        $inside = $this->tenantContext->runAsStore($this->storeB, fn (): array => $this->noteTexts());

        self::assertSame(['note of B'], $inside);
        self::assertSame(['note of A'], $this->noteTexts());
    }

    public function testNewTenantRowsGetTheActiveStore(): void
    {
        $this->tenantContext->useStore($this->storeB);

        $note = new TenantNote('new note');
        $this->entityManager->persist($note);
        $this->entityManager->flush();

        self::assertSame($this->storeB->getId(), $note->getStore()?->getId());
    }

    public function testSavingTenantRowsWithoutAStoreFails(): void
    {
        $this->tenantContext->clear();

        $this->expectException(MissingTenantException::class);
        $this->entityManager->persist(new TenantNote('orphan'));
    }

    public function testSavingARowForAnotherStoreFails(): void
    {
        $this->tenantContext->useStore($this->storeA);
        $note = new TenantNote('sneaky');
        $note->assignStore($this->storeB);

        $this->expectException(\LogicException::class);
        $this->entityManager->persist($note);
    }

    public function testAllStoresViewIsReadOnly(): void
    {
        $this->tenantContext->usePlatform(readOnly: true);
        $note = new TenantNote('write attempt');
        $note->assignStore($this->storeA);
        $this->entityManager->persist($note);

        $this->expectException(ReadOnlyTenantException::class);
        $this->entityManager->flush();
    }

    private function addNote(Store $store, string $text): void
    {
        $this->tenantContext->runAsStore($store, function () use ($text): void {
            $this->entityManager->persist(new TenantNote($text));
            $this->entityManager->flush();
        });
    }

    /**
     * @return list<string>
     */
    private function noteTexts(): array
    {
        $this->entityManager->clear();

        return array_map(
            static fn (TenantNote $note): string => $note->getText(),
            $this->entityManager->getRepository(TenantNote::class)->findAll(),
        );
    }
}
