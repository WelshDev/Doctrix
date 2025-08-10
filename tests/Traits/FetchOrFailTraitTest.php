<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\FetchOrFailTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Exceptions\EntityNotFoundException;
use WelshDev\Doctrix\Exceptions\MultipleEntitiesFoundException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityManagerInterface;

class FetchOrFailTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    private $entityManager;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        $this->repository = new class($this->entityManager) {
            use FetchOrFailTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $qb;
            private $em;
            
            public function __construct($em)
            {
                $this->em = $em;
            }
            
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
            
            public function getEntityManager()
            {
                return $this->em;
            }
            
            public function getClassName(): string
            {
                return 'App\Entity\TestEntity';
            }
        };
        
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(AbstractQuery::class);
        
        $this->queryBuilder->method('expr')->willReturn(new Expr());
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testFetchOrFailSuccess(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->fetchOrFail(['id' => 1]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchOrFailThrowsEntityNotFoundException(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('App\Entity\TestEntity not found with criteria: {"id":1}');
        
        $this->repository->fetchOrFail(['id' => 1]);
    }
    
    public function testFetchOrFailWithCustomMessage(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Custom error message');
        
        $this->repository->fetchOrFail(['id' => 1], 'Custom error message');
    }
    
    public function testFetchManyOrFailSuccess(): void
    {
        $entities = [new \stdClass(), new \stdClass()];
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->fetchManyOrFail(['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testFetchManyOrFailThrowsException(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([]);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('No App\Entity\TestEntity found with criteria: {"status":"inactive"}');
        
        $this->repository->fetchManyOrFail(['status' => 'inactive']);
    }
    
    public function testFetchSoleSuccess(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([$entity]);
        
        $result = $this->repository->fetchSole(['id' => 1]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchSoleThrowsEntityNotFoundException(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([]);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('App\Entity\TestEntity not found with criteria: {"id":999}');
        
        $this->repository->fetchSole(['id' => 999]);
    }
    
    public function testFetchSoleThrowsMultipleEntitiesFoundException(): void
    {
        $entities = [new \stdClass(), new \stdClass()];
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $this->expectException(MultipleEntitiesFoundException::class);
        $this->expectExceptionMessage('Expected exactly one App\Entity\TestEntity but found 2 with criteria: {"status":"active"}');
        
        $this->repository->fetchSole(['status' => 'active']);
    }
    
    public function testFindOrFailSuccess(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with('App\Entity\TestEntity', 1)
            ->willReturn($entity);
        
        $result = $this->repository->findOrFail(1);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFindOrFailThrowsException(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with('App\Entity\TestEntity', 999)
            ->willReturn(null);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('App\Entity\TestEntity not found with ID: 999');
        
        $this->repository->findOrFail(999);
    }
    
    public function testFetchOrCreateNew(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->fetchOrCreate(['email' => 'new@example.com'], [
            'name' => 'New User'
        ]);
        
        $this->assertInstanceOf('App\Entity\TestEntity', $result);
    }
    
    public function testFetchOrCreateExisting(): void
    {
        $entity = new \stdClass();
        $entity->email = 'existing@example.com';
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->fetchOrCreate(['email' => 'existing@example.com'], [
            'name' => 'Should Not Be Set'
        ]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchOrNewNew(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->fetchOrNew(['email' => 'new@example.com'], [
            'name' => 'New User'
        ]);
        
        $this->assertInstanceOf('App\Entity\TestEntity', $result);
    }
    
    public function testFetchOrNewExisting(): void
    {
        $entity = new \stdClass();
        $entity->email = 'existing@example.com';
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $result = $this->repository->fetchOrNew(['email' => 'existing@example.com'], [
            'name' => 'Should Not Be Set'
        ]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testUpdateOrCreateUpdate(): void
    {
        $entity = new \stdClass();
        $entity->email = 'existing@example.com';
        $entity->name = 'Old Name';
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($entity);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->updateOrCreate(
            ['email' => 'existing@example.com'],
            ['name' => 'New Name']
        );
        
        $this->assertSame($entity, $result);
        $this->assertEquals('New Name', $entity->name);
    }
    
    public function testUpdateOrCreateCreate(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf('App\Entity\TestEntity'));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->updateOrCreate(
            ['email' => 'new@example.com'],
            ['name' => 'New User']
        );
        
        $this->assertInstanceOf('App\Entity\TestEntity', $result);
    }
}