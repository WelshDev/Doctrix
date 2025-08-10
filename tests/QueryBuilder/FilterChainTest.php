<?php

namespace WelshDev\Doctrix\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\QueryBuilder\FilterChain;
use Doctrine\ORM\QueryBuilder;

class FilterChainTest extends TestCase
{
    private FilterChain $filterChain;
    private QueryBuilder $queryBuilder;
    
    protected function setUp(): void
    {
        $this->filterChain = new FilterChain();
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
    }
    
    public function testApplySingleFilter(): void
    {
        $filterCalled = false;
        $filter = function(QueryBuilder $qb) use (&$filterCalled) {
            $filterCalled = true;
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
            return $qb;
        };
        
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
        
        $this->filterChain->applyFilters($this->queryBuilder, [$filter]);
        
        $this->assertTrue($filterCalled);
    }
    
    public function testApplyMultipleFilters(): void
    {
        $filter1Called = false;
        $filter2Called = false;
        
        $filter1 = function(QueryBuilder $qb) use (&$filter1Called) {
            $filter1Called = true;
            return $qb;
        };
        
        $filter2 = function(QueryBuilder $qb) use (&$filter2Called) {
            $filter2Called = true;
            return $qb;
        };
        
        $this->filterChain->applyFilters($this->queryBuilder, [$filter1, $filter2]);
        
        $this->assertTrue($filter1Called);
        $this->assertTrue($filter2Called);
    }
    
    public function testApplyEmptyFilters(): void
    {
        // Should not throw any errors
        $this->filterChain->applyFilters($this->queryBuilder, []);
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->assertTrue(true); // Test passes if no exceptions
    }
    
    public function testFilterOrder(): void
    {
        $callOrder = [];
        
        $filter1 = function(QueryBuilder $qb) use (&$callOrder) {
            $callOrder[] = 'filter1';
            return $qb;
        };
        
        $filter2 = function(QueryBuilder $qb) use (&$callOrder) {
            $callOrder[] = 'filter2';
            return $qb;
        };
        
        $filter3 = function(QueryBuilder $qb) use (&$callOrder) {
            $callOrder[] = 'filter3';
            return $qb;
        };
        
        $this->filterChain->applyFilters($this->queryBuilder, [$filter1, $filter2, $filter3]);
        
        $this->assertEquals(['filter1', 'filter2', 'filter3'], $callOrder);
    }
    
    public function testFilterReceivesQueryBuilder(): void
    {
        $receivedQb = null;
        
        $filter = function(QueryBuilder $qb) use (&$receivedQb) {
            $receivedQb = $qb;
            return $qb;
        };
        
        $this->filterChain->applyFilters($this->queryBuilder, [$filter]);
        
        $this->assertSame($this->queryBuilder, $receivedQb);
    }
    
    public function testFilterCanModifyQueryBuilder(): void
    {
        $filter = function(QueryBuilder $qb) {
            $qb->andWhere('e.active = true')
               ->orderBy('e.created', 'DESC')
               ->setMaxResults(10);
            return $qb;
        };
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('e.active = true')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.created', 'DESC')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();
        
        $this->filterChain->applyFilters($this->queryBuilder, [$filter]);
    }
    
    public function testInvalidFilterType(): void
    {
        $filters = [
            'not_a_callable',  // String is not callable
            123,               // Integer is not callable
            ['array'],         // Array is not callable
        ];
        
        // Should handle gracefully (skip non-callables)
        foreach ($filters as $filter) {
            $this->filterChain->applyFilters($this->queryBuilder, [$filter]);
        }
        
        $this->assertTrue(true); // Test passes if no exceptions
    }
    
    public function testNamedFilters(): void
    {
        $namedFilters = [
            'active' => function(QueryBuilder $qb) {
                $qb->andWhere('e.status = :status')
                   ->setParameter('status', 'active');
                return $qb;
            },
            'verified' => function(QueryBuilder $qb) {
                $qb->andWhere('e.verified = true');
                return $qb;
            }
        ];
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        // Apply named filters
        $this->filterChain->applyFilters($this->queryBuilder, array_values($namedFilters));
    }
}