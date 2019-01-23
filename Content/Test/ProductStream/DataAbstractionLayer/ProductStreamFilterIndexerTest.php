<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamFilterIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\ProductStream\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductStreamFilterIndexerTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var EventIdExtractor|MockObject
     */
    private $eventIdExtractor;

    /**
     * @var EntityRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var ProductStreamFilterIndexer
     */
    private $indexer;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepo;

    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        $this->context = Context::createDefaultContext();
        $this->productRepo = $this->getContainer()->get('product.repository');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventIdExtractor = $this->createMock(EventIdExtractor::class);
        $this->repository = $this->getContainer()->get('product_stream.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $serializer = $this->getContainer()->get('serializer');
        $cacheKeyGenerator = $this->getContainer()->get(EntityCacheKeyGenerator::class);
        $cache = $this->getContainer()->get('shopware.cache');
        $this->indexer = new ProductStreamFilterIndexer(
            $eventDispatcher, $this->eventIdExtractor, $this->repository, $this->connection,
            $serializer, $cacheKeyGenerator, $cache
        );
    }

    public function testValidRefresh()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'))',
                Uuid::uuid4()->getHex(), 'equals', 'product.id', $productId, $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);
        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getFilter());
        static::assertCount(1, $entity->getFilter());
        static::assertSame('equals', $entity->getFilter()[0]['type']);
        static::assertSame('product.id', $entity->getFilter()[0]['field']);
        static::assertSame($productId, $entity->getFilter()[0]['value']);
        static::assertFalse($entity->isInvalid());
    }

    public function testWithChildren()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $multiId = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', 1, UNHEX(\'%s\'))',
                $multiId, 'multi', $id
            )
        );
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, operator, value, position, parent_id, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), UNHEX(\'%s\'))',
                Uuid::uuid4()->getHex(), 'equals', 'product.id', 'equals', $productId, $multiId, $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);
        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getFilter());
        static::assertCount(1, $entity->getFilter());
        static::assertSame('multi', $entity->getFilter()[0]['type']);
        static::assertSame(MultiFilter::CONNECTION_AND, $entity->getFilter()[0]['operator']);
        static::assertCount(1, $entity->getFilter()[0]['queries']);
        static::assertSame('equals', $entity->getFilter()[0]['queries'][0]['type']);
        static::assertSame('product.id', $entity->getFilter()[0]['queries'][0]['field']);
        static::assertSame($productId, $entity->getFilter()[0]['queries'][0]['value']);
        static::assertFalse($entity->isInvalid());
    }

    public function testInvalidType()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $multiId = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'))',
                $multiId, 'invalid', 'product.id', $productId, $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyField()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $multiId = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'))',
                $multiId, 'equals', null, $productId, $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyValue()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $multiId = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'))',
                $multiId, 'equals', 'id', null, $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testWithParameters()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $id = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, name, created_at, filter, invalid) VALUES (UNHEX(\'%s\'), \'%s\', NOW(), null, 1)', $id, 'Stream')
        );
        $multiId = Uuid::uuid4()->getHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, parameters, position, product_stream_id) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'))',
                $multiId, 'range', 'price.gross', json_encode([RangeFilter::GTE => 10]), $id
            )
        );

        $this->eventIdExtractor->expects($this->once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getFilter());
        static::assertCount(1, $entity->getFilter());
        static::assertSame('range', $entity->getFilter()[0]['type']);
        static::assertSame([RangeFilter::GTE => 10], $entity->getFilter()[0]['parameters']);
        static::assertFalse($entity->isInvalid());
    }
}
