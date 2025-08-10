<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Tests\BaseTestCase;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class EnhancedQueryTraitTest extends BaseTestCase
{
    private $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new class {
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
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testFetchAll(): void
    {
        $entities = [
            ['id' => 1, 'name' => 'Entity 1'],
            ['id' => 2, 'name' => 'Entity 2']
        ];
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->fetchAll();
        
        $this->assertSame($entities, $result);
    }
    
    public function testFetchWithCriteria(): void
    {
        $entities = [
            ['id' => 1, 'status' => 'active']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->fetch(['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testFetchOne(): void
    {
        $entity = ['id' => 1, 'name' => 'Entity 1'];
        
        $query = $this->createMockQuery([$entity]);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->fetchOne(['id' => 1]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testCount(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $query = $this->createMockQuery([5]);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->count(['status' => 'active']);
        
        $this->assertEquals(5, $result);
    }
    
    public function testBuildQueryWithOrderBy(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.createdAt', 'DESC')
            ->willReturnSelf();
        
        $result = $this->repository->buildQuery([], ['createdAt' => 'DESC']);
        
        $this->assertSame($this->queryBuilder, $result);
    }
    
    public function testBuildQueryWithMultipleOrderBy(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.status', 'ASC')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('e.createdAt', 'DESC')
            ->willReturnSelf();
        
        $result = $this->repository->buildQuery([], [
            'status' => 'ASC',
            'createdAt' => 'DESC'
        ]);
        
        $this->assertSame($this->queryBuilder, $result);
    }
    
    public function testWhere(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->where('status', 'active');
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereWithOperator(): void
    {
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->where('age', '>', 18);
        
        $this->assertSame($entities, $result);
    }
    
    public function testOrWhere(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orWhere')
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->orWhere('status', 'inactive');
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereIn(): void
    {
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IN');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereIn('status', ['active', 'pending']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereNotIn(): void
    {
        $entities = [['id' => 3]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'NOT IN');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereNotIn('status', ['deleted', 'archived']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereBetween(): void
    {
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'BETWEEN');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereBetween('age', 18, 65);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereNull(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IS NULL');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereNull('deletedAt');
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IS NOT NULL');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereNotNull('verifiedAt');
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereLike(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'LIKE');
            }))
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->whereLike('name', '%john%');
        
        $this->assertSame($entities, $result);
    }
    
    public function testOrderBy(): void
    {
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.createdAt', 'DESC')
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->orderBy('createdAt', 'DESC');
        
        $this->assertSame($entities, $result);
    }
    
    public function testLimit(): void
    {
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->limit(10);
        
        $this->assertSame($entities, $result);
    }
    
    public function testOffset(): void
    {
        $entities = [['id' => 11], ['id' => 12]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setFirstResult')
            ->with(10)
            ->willReturnSelf();
        
        $query = $this->createMockQuery($entities);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->repository->offset(10);
        
        $this->assertSame($entities, $result);
    }
    
    public function testRegisterFilterFunction(): void
    {
        $this->repository->registerFilterFunction('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('e.status = :status')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('status', 'active')
            ->willReturnSelf();
        
        $query = $this->createMockQuery([]);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $this->repository->applyFilter('active');
    }
    
    public function testRegisterOperator(): void
    {
        $customOperator = $this->createMock(\WelshDev\Doctrix\Interfaces\OperatorInterface::class);
        
        $customOperator
            ->expects($this->once())
            ->method('apply')
            ->with($this->queryBuilder, 'e.field', 'value', 'param_0');
        
        $this->repository->registerOperator('custom', $customOperator);
        
        $query = $this->createMockQuery([]);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $this->repository->where('field', 'custom', 'value');
    }
}