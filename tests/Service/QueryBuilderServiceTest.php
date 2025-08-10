<?php

namespace WelshDev\Doctrix\Tests\Service;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Service\QueryBuilderService;
use WelshDev\Doctrix\Service\EnhancedQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class QueryBuilderServiceTest extends TestCase
{
    private QueryBuilderService $service;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->repository->method('getClassName')->willReturn('App\Entity\User');
        
        $this->service = new QueryBuilderService($this->entityManager);
    }
    
    public function testEnhanceWithRepository(): void
    {
        $enhanced = $this->service->enhance($this->repository);
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testEnhanceWithEntityClass(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        $enhanced = $this->service->enhance('App\Entity\User');
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testEnhanceWithCustomAlias(): void
    {
        $enhanced = $this->service->enhance($this->repository, 'custom');
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testEnhanceWithJoins(): void
    {
        $joins = [
            ['leftJoin', 'u.profile', 'p'],
            ['innerJoin', 'u.roles', 'r']
        ];
        
        $enhanced = $this->service->enhance($this->repository, 'u', $joins);
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testForMethod(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        $enhanced = $this->service->for('App\Entity\User');
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testForWithAlias(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Product')
            ->willReturn($this->repository);
        
        $enhanced = $this->service->for('App\Entity\Product', 'p');
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testForWithJoins(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Order')
            ->willReturn($this->repository);
        
        $joins = [['leftJoin', 'o.customer', 'c']];
        $enhanced = $this->service->for('App\Entity\Order', 'o', $joins);
        
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced);
    }
    
    public function testCached(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        // First call creates the enhanced builder
        $enhanced1 = $this->service->cached('App\Entity\User');
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced1);
        
        // Second call should return a clone (not the same instance)
        $enhanced2 = $this->service->cached('App\Entity\User');
        $this->assertInstanceOf(EnhancedQueryBuilder::class, $enhanced2);
        $this->assertNotSame($enhanced1, $enhanced2);
    }
    
    public function testCachedWithDifferentAlias(): void
    {
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        $enhanced1 = $this->service->cached('App\Entity\User', 'u1');
        $enhanced2 = $this->service->cached('App\Entity\User', 'u2');
        
        // Different aliases should create different cache entries
        $this->assertNotSame($enhanced1, $enhanced2);
    }
    
    public function testClearCache(): void
    {
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        // Create cached instance
        $enhanced1 = $this->service->cached('App\Entity\User');
        
        // Clear cache
        $this->service->clearCache();
        
        // Should create new instance after cache clear
        $enhanced2 = $this->service->cached('App\Entity\User');
        
        $this->assertNotSame($enhanced1, $enhanced2);
    }
    
    public function testSimple(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('us')  // Should auto-generate alias from 'User'
            ->willReturn($queryBuilder);
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        $result = $this->service->simple('App\Entity\User');
        
        $this->assertSame($queryBuilder, $result);
    }
    
    public function testSimpleWithCustomAlias(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('custom')
            ->willReturn($queryBuilder);
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\User')
            ->willReturn($this->repository);
        
        $result = $this->service->simple('App\Entity\User', 'custom');
        
        $this->assertSame($queryBuilder, $result);
    }
}