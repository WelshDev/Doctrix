<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\PaginationTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Pagination\PaginationResult;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class PaginationTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use PaginationTrait;
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
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testPaginateBasic(): void
    {
        // First call for count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('100');
        
        // Second call for fetch
        $fetchQb = $this->createMock(QueryBuilder::class);
        $fetchQuery = $this->createMock(AbstractQuery::class);
        
        $fetchQb->method('setMaxResults')->willReturnSelf();
        $fetchQb->method('setFirstResult')->willReturnSelf();
        $fetchQb->method('getQuery')->willReturn($fetchQuery);
        
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2']
        ];
        $fetchQuery->method('getResult')->willReturn($items);
        
        $repository = new class {
            use PaginationTrait;
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
        
        $result = $repository->paginate([], 1, 10);
        
        $this->assertInstanceOf(PaginationResult::class, $result);
        $this->assertEquals($items, $result->items);
        $this->assertEquals(100, $result->total);
        $this->assertEquals(1, $result->page);
        $this->assertEquals(10, $result->perPage);
        $this->assertEquals(10, $result->lastPage);
    }
    
    public function testPaginateWithOrderBy(): void
    {
        // First call for count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('50');
        
        // Second call for fetch
        $fetchQb = $this->createMock(QueryBuilder::class);
        $fetchQuery = $this->createMock(AbstractQuery::class);
        
        $fetchQb->method('setMaxResults')->willReturnSelf();
        $fetchQb->method('setFirstResult')->willReturnSelf();
        $fetchQb->method('orderBy')->willReturnSelf();
        $fetchQb->method('getQuery')->willReturn($fetchQuery);
        
        $items = [
            ['id' => 3, 'name' => 'Item 3'],
            ['id' => 4, 'name' => 'Item 4']
        ];
        $fetchQuery->method('getResult')->willReturn($items);
        
        $repository = new class {
            use PaginationTrait;
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
        
        $result = $repository->paginate([], 2, 20, ['createdAt' => 'DESC']);
        
        $this->assertInstanceOf(PaginationResult::class, $result);
        $this->assertEquals(2, $result->page);
        $this->assertEquals(20, $result->perPage);
        $this->assertEquals(3, $result->lastPage);
    }
    
    public function testSimplePaginate(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(11) // perPage + 1 to check for more
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(10)
            ->willReturnSelf();
        
        $items = array_map(fn($i) => ['id' => $i], range(11, 21));
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($items);
        
        $result = $this->repository->simplePaginate(['status' => 'active'], 2, 10);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('hasMore', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        
        $this->assertCount(10, $result['items']); // Should only return perPage items
        $this->assertTrue($result['hasMore']);
        $this->assertEquals(2, $result['page']);
        $this->assertEquals(10, $result['perPage']);
    }
    
    public function testSimplePaginateNoMore(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(11)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(20)
            ->willReturnSelf();
        
        $items = array_map(fn($i) => ['id' => $i], range(21, 25));
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($items);
        
        $result = $this->repository->simplePaginate([], 3, 10);
        
        $this->assertCount(5, $result['items']);
        $this->assertFalse($result['hasMore']);
    }
    
    public function testCursorPaginate(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, '>');
            }))
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.id')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(11)
            ->willReturnSelf();
        
        $items = array_map(fn($i) => ['id' => $i], range(11, 21));
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($items);
        
        $result = $this->repository->cursorPaginate([], 10, 10);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('hasMore', $result);
        
        $this->assertCount(10, $result['items']);
        $this->assertEquals(20, $result['cursor']); // Last item's ID
        $this->assertTrue($result['hasMore']);
    }
    
    public function testCursorPaginateCustomColumn(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'e.uuid');
            }))
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.uuid')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(6)
            ->willReturnSelf();
        
        $items = array_map(fn($i) => ['uuid' => "uuid-$i"], range(1, 5));
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($items);
        
        $result = $this->repository->cursorPaginate([], 'uuid-0', 5, 'uuid');
        
        $this->assertCount(5, $result['items']);
        $this->assertEquals('uuid-5', $result['cursor']);
        $this->assertFalse($result['hasMore']);
    }
    
    public function testForPage(): void
    {
        $items = [
            ['id' => 21],
            ['id' => 22],
            ['id' => 23]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(20)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($items);
        
        $result = $this->repository->forPage(3, 10);
        
        $this->assertSame($items, $result);
    }
    
    public function testPaginateFromRequest(): void
    {
        $_GET = ['page' => '2', 'per_page' => '25'];
        
        // First call for count
        $countQb = $this->createMock(QueryBuilder::class);
        $countQuery = $this->createMock(AbstractQuery::class);
        
        $countQb->method('select')->willReturnSelf();
        $countQb->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn('100');
        
        // Second call for fetch
        $fetchQb = $this->createMock(QueryBuilder::class);
        $fetchQuery = $this->createMock(AbstractQuery::class);
        
        $fetchQb->method('setMaxResults')->willReturnSelf();
        $fetchQb->method('setFirstResult')->willReturnSelf();
        $fetchQb->method('getQuery')->willReturn($fetchQuery);
        
        $items = array_map(fn($i) => ['id' => $i], range(26, 50));
        $fetchQuery->method('getResult')->willReturn($items);
        
        $repository = new class {
            use PaginationTrait;
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
        
        $result = $repository->paginateFromRequest([]);
        
        $this->assertInstanceOf(PaginationResult::class, $result);
        $this->assertEquals(2, $result->page);
        $this->assertEquals(25, $result->perPage);
        
        $_GET = [];
    }
}