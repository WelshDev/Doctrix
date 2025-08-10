<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\ExistenceCheckTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class ExistenceCheckTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use ExistenceCheckTrait;
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
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testExistsReturnsTrue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(1);
        
        $result = $this->repository->exists([]);
        
        $this->assertTrue($result);
    }
    
    public function testExistsReturnsFalse(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
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
        
        $result = $this->repository->exists(['status' => 'nonexistent']);
        
        $this->assertFalse($result);
    }
    
    public function testDoesntExistReturnsTrue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
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
        
        $result = $this->repository->doesntExist(['status' => 'nonexistent']);
        
        $this->assertTrue($result);
    }
    
    public function testDoesntExistReturnsFalse(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(1);
        
        $result = $this->repository->doesntExist([]);
        
        $this->assertFalse($result);
    }
    
    public function testCountExistence(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(5);
        
        $result = $this->repository->countExistence(['status' => 'active']);
        
        $this->assertEquals(5, $result);
    }
    
    public function testExistsWithId(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(1);
        
        $result = $this->repository->existsWithId(123);
        
        $this->assertTrue($result);
    }
    
    public function testAnyReturnsTrue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(1);
        
        $result = $this->repository->any();
        
        $this->assertTrue($result);
    }
    
    public function testAnyReturnsFalse(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
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
        
        $result = $this->repository->any();
        
        $this->assertFalse($result);
    }
    
    public function testNoneReturnsTrue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
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
        
        $result = $this->repository->none();
        
        $this->assertTrue($result);
    }
    
    public function testNoneReturnsFalse(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(1);
        
        $result = $this->repository->none();
        
        $this->assertFalse($result);
    }
    
    public function testWhereExistsBasic(): void
    {
        $subquery = $this->createMock(QueryBuilder::class);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'EXISTS');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([['id' => 1], ['id' => 2]]);
        
        $result = $this->repository->whereExists(function($qb) {
            return $qb;
        });
        
        $this->assertCount(2, $result);
    }
    
    public function testWhereNotExistsBasic(): void
    {
        $subquery = $this->createMock(QueryBuilder::class);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'NOT EXISTS');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([['id' => 3], ['id' => 4]]);
        
        $result = $this->repository->whereNotExists(function($qb) {
            return $qb;
        });
        
        $this->assertCount(2, $result);
    }
}