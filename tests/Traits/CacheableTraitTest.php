<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\CacheableTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

class CacheableTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    private $cache;
    
    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        
        $this->repository = new class($this->cache) {
            use CacheableTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            private $qb;
            private $cachePool;
            
            public function __construct($cache)
            {
                $this->cachePool = $cache;
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
            
            public function getCachePool(): ?CacheItemPoolInterface
            {
                return $this->cachePool;
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
    
    public function testRememberCacheHit(): void
    {
        $cachedData = [['id' => 1], ['id' => 2]];
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        
        $cacheItem
            ->expects($this->once())
            ->method('get')
            ->willReturn($cachedData);
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $result = $this->repository->remember('test_key', 3600, function() {
            return [['id' => 3]]; // Should not be called
        });
        
        $this->assertSame($cachedData, $result);
    }
    
    public function testRememberCacheMiss(): void
    {
        $freshData = [['id' => 3], ['id' => 4]];
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($freshData)
            ->willReturnSelf();
        
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);
        
        $result = $this->repository->remember('test_key', 3600, function() use ($freshData) {
            return $freshData;
        });
        
        $this->assertSame($freshData, $result);
    }
    
    public function testRememberForever(): void
    {
        $data = [['id' => 1]];
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($data)
            ->willReturnSelf();
        
        $cacheItem
            ->expects($this->never())
            ->method('expiresAfter');
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);
        
        $result = $this->repository->rememberForever('eternal_key', function() use ($data) {
            return $data;
        });
        
        $this->assertSame($data, $result);
    }
    
    public function testCachedFetch(): void
    {
        $entities = [['id' => 1]];
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($entities)
            ->willReturnSelf();
        
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->cachedFetch(['status' => 'active'], 3600);
        
        $this->assertSame($entities, $result);
    }
    
    public function testCachedCount(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with(42)
            ->willReturnSelf();
        
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(1800)
            ->willReturnSelf();
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('42');
        
        $result = $this->repository->cachedCount(['status' => 'active'], 1800);
        
        $this->assertEquals(42, $result);
    }
    
    public function testForget(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('deleteItem')
            ->with('cache_key')
            ->willReturn(true);
        
        $result = $this->repository->forget('cache_key');
        
        $this->assertTrue($result);
    }
    
    public function testForgetMultiple(): void
    {
        $keys = ['key1', 'key2', 'key3'];
        
        $this->cache
            ->expects($this->once())
            ->method('deleteItems')
            ->with($keys)
            ->willReturn(true);
        
        $result = $this->repository->forgetMultiple($keys);
        
        $this->assertTrue($result);
    }
    
    public function testFlushCache(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('clear')
            ->willReturn(true);
        
        $result = $this->repository->flushCache();
        
        $this->assertTrue($result);
    }
    
    public function testGenerateCacheKey(): void
    {
        $criteria = ['status' => 'active', 'type' => 'premium'];
        
        $key = $this->repository->generateCacheKey('fetch', $criteria);
        
        $this->assertStringStartsWith('App_Entity_TestEntity_fetch_', $key);
        $this->assertStringContainsString(md5(serialize($criteria)), $key);
    }
    
    public function testWithCacheTtl(): void
    {
        $clone = $this->repository->withCacheTtl(7200);
        
        $this->assertNotSame($this->repository, $clone);
        $this->assertEquals(7200, $clone->getDefaultCacheTtl());
    }
    
    public function testWithoutCache(): void
    {
        $entities = [['id' => 1]];
        
        $this->cache
            ->expects($this->never())
            ->method('getItem');
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $clone = $this->repository->withoutCache();
        $result = $clone->cachedFetch(['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testCacheTagging(): void
    {
        $tags = ['users', 'active_users'];
        $entities = [['id' => 1]];
        
        $cacheItem = $this->createMock(CacheItemInterface::class);
        
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($entities)
            ->willReturnSelf();
        
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();
        
        if (method_exists($cacheItem, 'tag')) {
            $cacheItem
                ->expects($this->once())
                ->method('tag')
                ->with($tags)
                ->willReturnSelf();
        }
        
        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->cachedFetch(['status' => 'active'], 3600, $tags);
        
        $this->assertSame($entities, $result);
    }
}