<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\RandomSelectionTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class RandomSelectionTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use RandomSelectionTrait;
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
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testRandomSingle(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->random();
        
        $this->assertSame($entity, $result);
    }
    
    public function testRandomWithCriteria(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->random(['status' => 'active']);
        
        $this->assertSame($entity, $result);
    }
    
    public function testRandomReturnsNull(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->random(['status' => 'nonexistent']);
        
        $this->assertNull($result);
    }
    
    public function testRandomMultiple(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2],
            (object)['id' => 3]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(3)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->randomMultiple(3);
        
        $this->assertCount(3, $result);
        $this->assertSame($entities, $result);
    }
    
    public function testRandomMultipleWithCriteria(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(2)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->randomMultiple(2, ['status' => 'active']);
        
        $this->assertCount(2, $result);
        $this->assertSame($entities, $result);
    }
    
    public function testInRandomOrder(): void
    {
        $entities = [
            (object)['id' => 3],
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->inRandomOrder(['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testSampleBasic(): void
    {
        // First call to count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('10');
        
        // Second call to fetch random
        $fetchQb = $this->createMock(QueryBuilder::class);
        $fetchQuery = $this->createMock(AbstractQuery::class);
        
        $fetchQb->method('orderBy')->willReturnSelf();
        $fetchQb->method('setMaxResults')->willReturnSelf();
        $fetchQb->method('getQuery')->willReturn($fetchQuery);
        $fetchQuery->method('getResult')->willReturn([
            (object)['id' => 1],
            (object)['id' => 2],
            (object)['id' => 3]
        ]);
        
        $repository = new class {
            use RandomSelectionTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $callCount = 0;
            private $countQb;
            private $fetchQb;
            
            public function setQueryBuilders($countQb, $fetchQb)
            {
                $this->countQb = $countQb;
                $this->fetchQb = $fetchQb;
            }
            
            public function createQueryBuilder($alias)
            {
                $this->callCount++;
                return $this->callCount === 1 ? $this->countQb : $this->fetchQb;
            }
            
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
        
        $repository->setQueryBuilders($countQb, $fetchQb);
        
        $result = $repository->sample(0.3); // 30% of 10 = 3
        
        $this->assertCount(3, $result);
    }
    
    public function testSampleWithMinimum(): void
    {
        // First call to count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('10');
        
        // Second call to fetch random
        $fetchQb = $this->createMock(QueryBuilder::class);
        $fetchQuery = $this->createMock(AbstractQuery::class);
        
        $fetchQb->method('orderBy')->willReturnSelf();
        $fetchQb->method('setMaxResults')->willReturnSelf();
        $fetchQb->method('getQuery')->willReturn($fetchQuery);
        $fetchQuery->method('getResult')->willReturn([
            (object)['id' => 1],
            (object)['id' => 2],
            (object)['id' => 3],
            (object)['id' => 4],
            (object)['id' => 5]
        ]);
        
        $repository = new class {
            use RandomSelectionTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $callCount = 0;
            private $countQb;
            private $fetchQb;
            
            public function setQueryBuilders($countQb, $fetchQb)
            {
                $this->countQb = $countQb;
                $this->fetchQb = $fetchQb;
            }
            
            public function createQueryBuilder($alias)
            {
                $this->callCount++;
                return $this->callCount === 1 ? $this->countQb : $this->fetchQb;
            }
            
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
        
        $repository->setQueryBuilders($countQb, $fetchQb);
        
        // 10% of 10 = 1, but minimum is 5
        $result = $repository->sample(0.1, [], 5);
        
        $this->assertCount(5, $result);
    }
    
    public function testRandomOrFailSuccess(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->randomOrFail();
        
        $this->assertSame($entity, $result);
    }
    
    public function testRandomOrFailThrowsException(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('RAND()')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No entities found for random selection');
        
        $this->repository->randomOrFail();
    }
}