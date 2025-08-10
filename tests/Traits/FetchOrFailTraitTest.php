<?php

// Mock entity class for testing
namespace App\Entity;

class TestEntity {
    public $email;
    public $name;
    
    public function setEmail($email) {
        $this->email = $email;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
}

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\FetchOrFailTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Exceptions\EntityNotFoundException;
use WelshDev\Doctrix\Exceptions\MultipleEntitiesFoundException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
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
            
            public function createQueryBuilder(string $alias): QueryBuilder
            {
                return $this->qb;
            }
            
            public function getAlias(): string
            {
                return 'e';
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
        $this->query = $this->createMock(Query::class);
        
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
        
        $result = $this->repository->fetchOneOrFail(['id' => 1]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchOrFailThrowsEntityNotFoundException(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('TestEntity not found');
        
        $this->repository->fetchOneOrFail(['id' => 1]);
    }
    
    public function testFetchOrFailWithCustomMessage(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Custom error message');
        
        $this->repository->fetchOneOrFail(['id' => 1], null, 'Custom error message');
    }
    
    
    public function testFetchSoleSuccess(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([$entity]);
        
        $result = $this->repository->sole(['id' => 1]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchSoleThrowsEntityNotFoundException(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([]);
        
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('No results found');
        
        $this->repository->sole(['id' => 999]);
    }
    
    public function testFetchSoleThrowsMultipleEntitiesFoundException(): void
    {
        $entities = [new \stdClass(), new \stdClass()];
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $this->expectException(MultipleEntitiesFoundException::class);
        $this->expectExceptionMessage('Expected exactly one TestEntity but found 2');
        
        $this->repository->sole(['status' => 'active']);
    }
    
    
    public function testFetchOrCreateNew(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->fetchOneOrCreate(['email' => 'new@example.com'], [
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
        
        $result = $this->repository->fetchOneOrCreate(['email' => 'existing@example.com'], [
            'name' => 'Should Not Be Set'
        ]);
        
        $this->assertSame($entity, $result);
    }
    
    public function testFetchOneOrCreateWithCallback(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->fetchOneOrCreate(
            ['email' => 'new@example.com'],
            function($criteria) {
                $entity = new \App\Entity\TestEntity();
                $entity->setEmail('custom@example.com');
                $entity->setName('Custom Name');
                return $entity;
            }
        );
        
        $this->assertInstanceOf('App\Entity\TestEntity', $result);
        $this->assertEquals('custom@example.com', $result->email);
        $this->assertEquals('Custom Name', $result->name);
    }
    
    public function testFetchOneOrCreateWithCallbackExisting(): void
    {
        $entity = new \stdClass();
        $entity->email = 'existing@example.com';
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $callbackCalled = false;
        $result = $this->repository->fetchOneOrCreate(
            ['email' => 'existing@example.com'],
            function($criteria) use (&$callbackCalled) {
                $callbackCalled = true;
                return new \App\Entity\TestEntity();
            }
        );
        
        $this->assertSame($entity, $result);
        $this->assertFalse($callbackCalled, 'Callback should not be called when entity exists');
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
        
        // Entity manager should not be called for persist or flush
        // when an existing entity is found
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->never())
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
        
        // Entity manager should not be called for persist or flush
        // The consuming application will handle this
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->never())
            ->method('flush');
        
        $result = $this->repository->updateOrCreate(
            ['email' => 'new@example.com'],
            ['name' => 'New User']
        );
        
        $this->assertInstanceOf('App\Entity\TestEntity', $result);
    }
}