<?php

namespace WelshDev\Doctrix\Tests\QueryBuilder;

use WelshDev\Doctrix\Tests\BaseTestCase;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;

class FluentQueryBuilderTest extends BaseTestCase
{
    private FluentQueryBuilder $fluent;
    private $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock repository with EnhancedQueryTrait
        $this->repository = $this->getMockForTrait(EnhancedQueryTrait::class);
        $this->repository->method('buildQuery')->willReturn($this->queryBuilder);
        $this->repository->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->repository->method('getAlias')->willReturn('e');
        
        $this->fluent = new FluentQueryBuilder($this->repository);
    }
    
    public function testWhereMethod(): void
    {
        $result = $this->fluent->where('status', 'active');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
        $this->assertSame($this->fluent, $result);
    }
    
    public function testWhereWithOperator(): void
    {
        $result = $this->fluent->where('age', '>=', 18);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereWithCallable(): void
    {
        $result = $this->fluent->where(function($q) {
            $q->where('status', 'active')
              ->where('verified', true);
        });
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testOrWhere(): void
    {
        $result = $this->fluent
            ->where('status', 'active')
            ->orWhere('role', 'admin');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereIn(): void
    {
        $result = $this->fluent->whereIn('status', ['active', 'pending']);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereNotIn(): void
    {
        $result = $this->fluent->whereNotIn('status', ['banned', 'deleted']);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereBetween(): void
    {
        $result = $this->fluent->whereBetween('age', 18, 65);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereNull(): void
    {
        $result = $this->fluent->whereNull('deletedAt');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereNotNull(): void
    {
        $result = $this->fluent->whereNotNull('verifiedAt');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereLike(): void
    {
        $result = $this->fluent->whereLike('name', '%john%');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testWhereContains(): void
    {
        $result = $this->fluent->whereContains('email', 'gmail');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testOrderBy(): void
    {
        $result = $this->fluent->orderBy('created', 'DESC');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testMultipleOrderBy(): void
    {
        $result = $this->fluent
            ->orderBy('status', 'ASC')
            ->orderBy('created', 'DESC');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testLimit(): void
    {
        $result = $this->fluent->limit(10);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testOffset(): void
    {
        $result = $this->fluent->offset(20);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testLimitAndOffset(): void
    {
        $result = $this->fluent->limit(10)->offset(20);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testGet(): void
    {
        $expectedResult = [['id' => 1], ['id' => 2]];
        $query = $this->createMockQuery($expectedResult);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->where('status', 'active')->get();
        
        $this->assertEquals($expectedResult, $result);
    }
    
    public function testFirst(): void
    {
        $expectedResult = ['id' => 1, 'name' => 'Test'];
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getOneOrNullResult')->willReturn($expectedResult);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->where('id', 1)->first();
        
        $this->assertEquals($expectedResult, $result);
    }
    
    public function testCount(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(42);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->where('status', 'active')->count();
        
        $this->assertEquals(42, $result);
    }
    
    public function testExists(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(1);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();
        
        $result = $this->fluent->where('status', 'active')->exists();
        
        $this->assertTrue($result);
    }
    
    public function testSum(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(1000.50);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->sum('amount');
        
        $this->assertEquals(1000.50, $result);
    }
    
    public function testAvg(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(75.5);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->avg('score');
        
        $this->assertEquals(75.5, $result);
    }
    
    public function testMax(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(100);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->max('score');
        
        $this->assertEquals(100, $result);
    }
    
    public function testMin(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(10);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->min('score');
        
        $this->assertEquals(10, $result);
    }
    
    public function testComplexChaining(): void
    {
        $result = $this->fluent
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'moderator'])
            ->whereNotNull('verifiedAt')
            ->whereBetween('age', 18, 65)
            ->orderBy('created', 'DESC')
            ->limit(10)
            ->offset(0);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testGetQueryBuilder(): void
    {
        $qb = $this->fluent->getQueryBuilder();
        
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }
    
    public function testToSql(): void
    {
        $expectedSql = 'SELECT e FROM Entity e WHERE e.status = :status';
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSQL')->willReturn($expectedSql);
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $sql = $this->fluent->where('status', 'active')->toSql();
        
        $this->assertEquals($expectedSql, $sql);
    }
    
    public function testGetParameters(): void
    {
        $parameters = new \Doctrine\Common\Collections\ArrayCollection([
            'status' => 'active',
            'role' => 'admin'
        ]);
        
        $this->queryBuilder->method('getParameters')->willReturn($parameters);
        
        $result = $this->fluent
            ->where('status', 'active')
            ->where('role', 'admin')
            ->getParameters();
        
        $this->assertEquals($parameters, $result);
    }
    
    public function testCache(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->expects($this->once())
            ->method('enableResultCache')
            ->with(3600, 'cache_key');
        
        $this->queryBuilder->method('getQuery')->willReturn($query);
        
        $result = $this->fluent->cache(3600, 'cache_key');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testApplyFilter(): void
    {
        // This would need the repository to have defined filters
        $result = $this->fluent->applyFilter('active');
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
    }
    
    public function testPage(): void
    {
        $result = $this->fluent->page(2, 20);
        
        $this->assertInstanceOf(FluentQueryBuilder::class, $result);
        // Should set limit to 20 and offset to 20 (page 2)
    }
}