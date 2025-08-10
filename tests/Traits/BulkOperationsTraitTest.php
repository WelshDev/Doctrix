<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\BulkOperationsTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class BulkOperationsTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        // Create a mock repository that uses the trait
        $this->repository = new class {
            use BulkOperationsTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $qb;
            
            public function setQueryBuilder($qb)
            {
                $this->qb = $qb;
            }
            
            public function createQueryBuilder($alias)
            {
                return $this->qb;
            }
            
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
        
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(AbstractQuery::class);
        
        $this->queryBuilder->method('expr')->willReturn(new Expr());
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testBulkUpdateBasic(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('update')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn(5);
        
        $result = $this->repository->bulkUpdate(
            ['status' => 'inactive', 'updatedAt' => new \DateTime()],
            []
        );
        
        $this->assertEquals(5, $result);
    }
    
    public function testBulkUpdateWithCriteria(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('update')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('set')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn(10);
        
        $result = $this->repository->bulkUpdate(
            ['status' => 'archived'],
            ['status' => 'inactive']
        );
        
        $this->assertEquals(10, $result);
    }
    
    public function testBulkUpdateWithNullValue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('update')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['e.email', 'NULL'],
                ['e.status', $this->anything()]
            )
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn(3);
        
        $result = $this->repository->bulkUpdate(
            ['email' => null, 'status' => 'deleted'],
            []
        );
        
        $this->assertEquals(3, $result);
    }
    
    public function testBulkDeleteBasic(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn(15);
        
        $result = $this->repository->bulkDelete([]);
        
        $this->assertEquals(15, $result);
    }
    
    public function testBulkDeleteWithCriteria(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn(7);
        
        $result = $this->repository->bulkDelete(['status' => 'expired']);
        
        $this->assertEquals(7, $result);
    }
    
    public function testCountMatching(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('42');
        
        $result = $this->repository->countMatching([]);
        
        $this->assertEquals(42, $result);
    }
    
    public function testCountMatchingWithCriteria(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('25');
        
        $result = $this->repository->countMatching(['status' => 'active']);
        
        $this->assertEquals(25, $result);
    }
    
    public function testConditionalBulkUpdateProceedsWhenConditionMet(): void
    {
        // First call for count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('50');
        
        // Second call for update
        $updateQb = $this->createMock(QueryBuilder::class);
        $updateQuery = $this->createMock(AbstractQuery::class);
        
        $updateQb->method('update')->willReturnSelf();
        $updateQb->method('set')->willReturnSelf();
        $updateQb->method('setParameter')->willReturnSelf();
        $updateQb->method('getQuery')->willReturn($updateQuery);
        $updateQuery->method('execute')->willReturn(50);
        
        $repository = new class {
            use BulkOperationsTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $callCount = 0;
            private $countQb;
            private $updateQb;
            
            public function setQueryBuilders($countQb, $updateQb)
            {
                $this->countQb = $countQb;
                $this->updateQb = $updateQb;
            }
            
            public function createQueryBuilder($alias)
            {
                $this->callCount++;
                return $this->callCount === 1 ? $this->countQb : $this->updateQb;
            }
            
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
        
        $repository->setQueryBuilders($countQb, $updateQb);
        
        $result = $repository->conditionalBulkUpdate(
            ['status' => 'archived'],
            ['status' => 'inactive'],
            fn($count) => $count < 100  // Condition is met (50 < 100)
        );
        
        $this->assertEquals(50, $result);
    }
    
    public function testConditionalBulkUpdateSkipsWhenConditionNotMet(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('150');
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('update');
        
        $result = $this->repository->conditionalBulkUpdate(
            ['status' => 'archived'],
            ['status' => 'inactive'],
            fn($count) => $count < 100  // Condition not met (150 > 100)
        );
        
        $this->assertEquals(0, $result);
    }
    
    public function testSafeBulkDeleteDryRun(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('25');
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('delete');
        
        $result = $this->repository->safeBulkDelete(['status' => 'expired'], true);
        
        $this->assertEquals(['count' => 25, 'deleted' => 0], $result);
    }
    
    public function testSafeBulkDeleteActual(): void
    {
        // First call for count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('25');
        
        // Second call for delete
        $deleteQb = $this->createMock(QueryBuilder::class);
        $deleteQuery = $this->createMock(AbstractQuery::class);
        
        $deleteQb->method('delete')->willReturnSelf();
        $deleteQb->method('getQuery')->willReturn($deleteQuery);
        $deleteQuery->method('execute')->willReturn(25);
        
        $repository = new class {
            use BulkOperationsTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $callCount = 0;
            private $countQb;
            private $deleteQb;
            
            public function setQueryBuilders($countQb, $deleteQb)
            {
                $this->countQb = $countQb;
                $this->deleteQb = $deleteQb;
            }
            
            public function createQueryBuilder($alias)
            {
                $this->callCount++;
                return $this->callCount === 1 ? $this->countQb : $this->deleteQb;
            }
            
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
        
        $repository->setQueryBuilders($countQb, $deleteQb);
        
        $result = $repository->safeBulkDelete(['status' => 'expired'], false);
        
        $this->assertEquals(['count' => 25, 'deleted' => 25], $result);
    }
    
    public function testBulkBatchInvalidOperation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid operation: invalid. Use 'update' or 'delete'.");
        
        $this->repository->bulkBatch('invalid', [], [], 100);
    }
}