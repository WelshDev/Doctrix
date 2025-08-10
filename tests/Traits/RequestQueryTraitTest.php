<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\RequestQueryTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Request\RequestQuerySchema;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\HttpFoundation\Request;

class RequestQueryTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use RequestQueryTrait;
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
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testFromRequestBasic(): void
    {
        $request = Request::create('/', 'GET', [
            'status' => 'active',
            'name' => 'John'
        ]);
        
        $entities = [
            ['id' => 1, 'status' => 'active', 'name' => 'John']
        ];
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fromRequest($request);
        
        $this->assertSame($entities, $result);
    }
    
    public function testFromRequestWithSchema(): void
    {
        $request = Request::create('/', 'GET', [
            'status' => 'active',
            'invalid_field' => 'should_be_ignored',
            'sort' => 'name',
            'order' => 'desc'
        ]);
        
        $schema = new RequestQuerySchema();
        $schema->allowFields(['status', 'name'])
               ->allowSorting(['name', 'createdAt']);
        
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.name', 'DESC')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fromRequest($request, $schema);
        
        $this->assertSame($entities, $result);
    }
    
    public function testFromRequestWithOperators(): void
    {
        $request = Request::create('/', 'GET', [
            'age[gte]' => '18',
            'age[lte]' => '65',
            'status[in]' => 'active,pending'
        ]);
        
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fromRequest($request);
        
        $this->assertSame($entities, $result);
    }
    
    public function testFromGlobals(): void
    {
        $_GET = [
            'category' => 'electronics',
            'price[lte]' => '1000'
        ];
        
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fromGlobals();
        
        $this->assertSame($entities, $result);
        
        $_GET = [];
    }
    
    public function testFromArray(): void
    {
        $params = [
            'status' => 'published',
            'author' => 'john',
            'tags[in]' => 'php,symfony'
        ];
        
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fromArray($params);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWithRequestFilters(): void
    {
        $request = Request::create('/', 'GET', [
            'active' => '1',
            'featured' => 'true'
        ]);
        
        $entities = [['id' => 1]];
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withRequestFilters($request);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWithRequestSorting(): void
    {
        $request = Request::create('/', 'GET', [
            'sort' => 'createdAt',
            'order' => 'asc'
        ]);
        
        $entities = [['id' => 1], ['id' => 2]];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.createdAt', 'ASC')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withRequestSorting($request);
        
        $this->assertSame($entities, $result);
    }
    
    public function testValidateRequestQuery(): void
    {
        $request = Request::create('/', 'GET', [
            'status' => 'active',
            'invalid_field' => 'test'
        ]);
        
        $schema = new RequestQuerySchema();
        $schema->allowFields(['status']);
        
        $validation = $this->repository->validateRequestQuery($request, $schema);
        
        $this->assertTrue($validation['valid']);
        $this->assertArrayHasKey('filtered', $validation);
        $this->assertArrayHasKey('status', $validation['filtered']);
        $this->assertArrayNotHasKey('invalid_field', $validation['filtered']);
    }
    
    public function testBuildFromRequestWithDefaults(): void
    {
        $request = Request::create('/', 'GET', [
            'status' => 'active'
        ]);
        
        $defaults = [
            'visibility' => 'public',
            'deleted' => false
        ];
        
        $this->queryBuilder
            ->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();
        
        $qb = $this->repository->buildFromRequest($request, $defaults);
        
        $this->assertSame($this->queryBuilder, $qb);
    }
    
    public function testParseRequestFilters(): void
    {
        $request = Request::create('/', 'GET', [
            'name' => 'John',
            'age[gte]' => '18',
            'tags[in]' => 'php,laravel,symfony',
            'description[like]' => '%developer%'
        ]);
        
        $filters = $this->repository->parseRequestFilters($request);
        
        $this->assertArrayHasKey('name', $filters);
        $this->assertEquals('John', $filters['name']);
        
        $this->assertArrayHasKey('age', $filters);
        $this->assertIsArray($filters['age']);
        $this->assertEquals(['gte' => '18'], $filters['age']);
        
        $this->assertArrayHasKey('tags', $filters);
        $this->assertEquals(['in' => 'php,laravel,symfony'], $filters['tags']);
        
        $this->assertArrayHasKey('description', $filters);
        $this->assertEquals(['like' => '%developer%'], $filters['description']);
    }
    
    public function testSearchFromRequest(): void
    {
        $request = Request::create('/', 'GET', [
            'q' => 'john doe'
        ]);
        
        $entities = [['id' => 1, 'name' => 'John Doe']];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'LIKE');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->searchFromRequest($request, ['name', 'email']);
        
        $this->assertSame($entities, $result);
    }
}