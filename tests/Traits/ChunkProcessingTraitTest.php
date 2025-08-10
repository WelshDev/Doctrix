<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\ChunkProcessingTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;

class ChunkProcessingTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use ChunkProcessingTrait;
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
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testChunkBasic(): void
    {
        $results = [];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $this->repository->chunk([], 2, function($items) use (&$results) {
            foreach ($items as $item) {
                $results[] = $item['id'];
            }
        });
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testChunkWithEarlyStop(): void
    {
        $results = [];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                [['id' => 5], ['id' => 6]]
            );
        
        $this->repository->chunk([], 2, function($items) use (&$results) {
            foreach ($items as $item) {
                $results[] = $item['id'];
            }
            return count($results) < 4; // Stop after 4 items
        });
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testChunkByIdBasic(): void
    {
        $results = [];
        
        $this->queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $this->repository->chunkById([], 2, function($items) use (&$results) {
            foreach ($items as $item) {
                $results[] = $item['id'];
            }
        });
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testChunkByIdWithCustomColumn(): void
    {
        $results = [];
        
        $this->queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->method('orderBy')
            ->with('e.uuid')
            ->willReturnSelf();
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['uuid' => 'a'], ['uuid' => 'b']],
                [['uuid' => 'c'], ['uuid' => 'd']],
                []
            );
        
        $this->repository->chunkById([], 2, function($items) use (&$results) {
            foreach ($items as $item) {
                $results[] = $item['uuid'];
            }
        }, 'uuid');
        
        $this->assertEquals(['a', 'b', 'c', 'd'], $results);
    }
    
    public function testLazyCollectionBasic(): void
    {
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $results = [];
        foreach ($this->repository->lazyCollection([], 2) as $item) {
            $results[] = $item['id'];
        }
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testLazyCollectionWithBreak(): void
    {
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                [['id' => 5], ['id' => 6]]
            );
        
        $results = [];
        foreach ($this->repository->lazyCollection([], 2) as $item) {
            $results[] = $item['id'];
            if (count($results) >= 3) {
                break;
            }
        }
        
        $this->assertEquals([1, 2, 3], $results);
    }
    
    public function testCursor(): void
    {
        $this->query
            ->method('toIterable')
            ->willReturn(new \ArrayIterator([
                ['id' => 1],
                ['id' => 2],
                ['id' => 3]
            ]));
        
        $results = [];
        foreach ($this->repository->cursor([]) as $item) {
            $results[] = $item['id'];
        }
        
        $this->assertEquals([1, 2, 3], $results);
    }
    
    public function testEachBasic(): void
    {
        $results = [];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $this->repository->each([], function($item) use (&$results) {
            $results[] = $item['id'];
        }, 2);
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testEachWithIndex(): void
    {
        $results = [];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                []
            );
        
        $this->repository->each([], function($item, $index) use (&$results) {
            $results[$index] = $item['id'];
        }, 2);
        
        $this->assertEquals([0 => 1, 1 => 2], $results);
    }
    
    public function testEachByIdBasic(): void
    {
        $results = [];
        
        $this->queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $this->repository->eachById([], function($item) use (&$results) {
            $results[] = $item['id'];
        }, 2);
        
        $this->assertEquals([1, 2, 3, 4], $results);
    }
    
    public function testMapWithChunking(): void
    {
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'value' => 10], ['id' => 2, 'value' => 20]],
                [['id' => 3, 'value' => 30], ['id' => 4, 'value' => 40]],
                []
            );
        
        $result = $this->repository->mapWithChunking([], function($item) {
            return $item['value'] * 2;
        }, 2);
        
        $this->assertEquals([20, 40, 60, 80], $result);
    }
    
    public function testReduceWithChunking(): void
    {
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['value' => 10], ['value' => 20]],
                [['value' => 30], ['value' => 40]],
                []
            );
        
        $result = $this->repository->reduceWithChunking([], function($carry, $item) {
            return $carry + $item['value'];
        }, 0, 2);
        
        $this->assertEquals(100, $result);
    }
    
    public function testProcessInBatches(): void
    {
        $processedBatches = [];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1], ['id' => 2]],
                [['id' => 3], ['id' => 4]],
                []
            );
        
        $count = $this->repository->processInBatches([], function($batch) use (&$processedBatches) {
            $processedBatches[] = count($batch);
            return count($batch);
        }, 2);
        
        $this->assertEquals([2, 2], $processedBatches);
        $this->assertEquals(4, $count);
    }
}