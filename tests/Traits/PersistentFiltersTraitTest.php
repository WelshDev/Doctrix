<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Traits\PersistentFiltersTrait;

/**
 * Test class for PersistentFiltersTrait
 */
class PersistentFiltersTraitTest extends TestCase
{
    private TestRepository $repository;
    private EntityManagerInterface $em;
    private QueryBuilder $qb;
    
    protected function setUp(): void
    {
        // Mock EntityManager and QueryBuilder
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->qb = $this->createMock(QueryBuilder::class);
        
        // Create test repository
        $this->repository = new TestRepository($this->em);
    }
    
    /**
     * Test that withFilter returns a cloned instance
     */
    public function testWithFilterReturnsClonedInstance(): void
    {
        $filtered = $this->repository->withFilter('test', 'value');
        
        $this->assertNotSame($this->repository, $filtered);
        $this->assertInstanceOf(TestRepository::class, $filtered);
    }
    
    /**
     * Test that filters are stored correctly
     */
    public function testFiltersAreStoredCorrectly(): void
    {
        $filtered = $this->repository
            ->withFilter('status', 'active')
            ->withFilter('type', 'premium');
        
        $this->assertTrue($filtered->hasFilter('status'));
        $this->assertTrue($filtered->hasFilter('type'));
        $this->assertEquals('active', $filtered->getFilter('status'));
        $this->assertEquals('premium', $filtered->getFilter('type'));
    }
    
    /**
     * Test that original repository remains unchanged
     */
    public function testOriginalRepositoryRemainsUnchanged(): void
    {
        $filtered = $this->repository->withFilter('test', 'value');
        
        $this->assertFalse($this->repository->hasFilter('test'));
        $this->assertTrue($filtered->hasFilter('test'));
    }
    
    /**
     * Test withFilters method
     */
    public function testWithFiltersAppliesMultipleFilters(): void
    {
        $filters = [
            'status' => 'active',
            'type' => 'premium',
            'verified' => true
        ];
        
        $filtered = $this->repository->withFilters($filters);
        
        $this->assertEquals($filters, $filtered->getFilters());
        $this->assertTrue($filtered->hasFilter('status'));
        $this->assertTrue($filtered->hasFilter('type'));
        $this->assertTrue($filtered->hasFilter('verified'));
    }
    
    /**
     * Test withoutFilter removes specific filter
     */
    public function testWithoutFilterRemovesSpecificFilter(): void
    {
        $filtered = $this->repository
            ->withFilter('status', 'active')
            ->withFilter('type', 'premium')
            ->withoutFilter('status');
        
        $this->assertFalse($filtered->hasFilter('status'));
        $this->assertTrue($filtered->hasFilter('type'));
    }
    
    /**
     * Test withoutFilters clears all filters
     */
    public function testWithoutFiltersClearsAllFilters(): void
    {
        $filtered = $this->repository
            ->withFilter('status', 'active')
            ->withFilter('type', 'premium')
            ->withoutFilters();
        
        $this->assertEmpty($filtered->getFilters());
        $this->assertFalse($filtered->hasFilter('status'));
        $this->assertFalse($filtered->hasFilter('type'));
    }
    
    /**
     * Test getFilter with default value
     */
    public function testGetFilterWithDefaultValue(): void
    {
        $filtered = $this->repository->withFilter('existing', 'value');
        
        $this->assertEquals('value', $filtered->getFilter('existing'));
        $this->assertEquals('default', $filtered->getFilter('nonexistent', 'default'));
        $this->assertNull($filtered->getFilter('nonexistent'));
    }
    
    /**
     * Test filter application with convention-based method
     */
    public function testFilterApplicationWithConventionMethod(): void
    {
        $filtered = $this->repository->withFilter('status', 'active');
        
        // Mock the QueryBuilder to verify method calls
        $this->qb->expects($this->once())
            ->method('andWhere')
            ->with('t.status = :status')
            ->willReturnSelf();
        
        $this->qb->expects($this->once())
            ->method('setParameter')
            ->with('status', 'active')
            ->willReturnSelf();
        
        // Apply filters through test method
        $filtered->testApplyFilters($this->qb);
    }
    
    /**
     * Test filter application with generic handler
     */
    public function testFilterApplicationWithGenericHandler(): void
    {
        $filtered = $this->repository->withFilter('generic', 'value');
        
        // Mock the QueryBuilder to verify method calls
        $this->qb->expects($this->once())
            ->method('andWhere')
            ->with('t.generic = :generic')
            ->willReturnSelf();
        
        $this->qb->expects($this->once())
            ->method('setParameter')
            ->with('generic', 'value')
            ->willReturnSelf();
        
        // Apply filters through test method
        $filtered->testApplyFilters($this->qb);
    }
    
    /**
     * Test complex filter values (arrays, objects)
     */
    public function testComplexFilterValues(): void
    {
        $dateRange = [
            'start' => new \DateTime('2024-01-01'),
            'end' => new \DateTime('2024-12-31')
        ];
        
        $filtered = $this->repository->withFilter('dateRange', $dateRange);
        
        $this->assertTrue($filtered->hasFilter('dateRange'));
        $this->assertEquals($dateRange, $filtered->getFilter('dateRange'));
    }
    
    /**
     * Test filter chaining
     */
    public function testFilterChaining(): void
    {
        $result = $this->repository
            ->withFilter('a', 1)
            ->withFilter('b', 2)
            ->withFilter('c', 3)
            ->withoutFilter('b')
            ->withFilter('d', 4);
        
        $filters = $result->getFilters();
        
        $this->assertCount(3, $filters);
        $this->assertEquals(1, $filters['a']);
        $this->assertArrayNotHasKey('b', $filters);
        $this->assertEquals(3, $filters['c']);
        $this->assertEquals(4, $filters['d']);
    }
    
    /**
     * Test immutability through multiple operations
     */
    public function testImmutabilityThroughOperations(): void
    {
        $base = $this->repository;
        $filtered1 = $base->withFilter('filter1', 'value1');
        $filtered2 = $filtered1->withFilter('filter2', 'value2');
        $filtered3 = $filtered2->withoutFilter('filter1');
        
        // Each should be a different instance
        $this->assertNotSame($base, $filtered1);
        $this->assertNotSame($filtered1, $filtered2);
        $this->assertNotSame($filtered2, $filtered3);
        
        // Each should have different filters
        $this->assertCount(0, $base->getFilters());
        $this->assertCount(1, $filtered1->getFilters());
        $this->assertCount(2, $filtered2->getFilters());
        $this->assertCount(1, $filtered3->getFilters());
    }
    
    /**
     * Test that filters persist across buildQuery calls
     */
    public function testFiltersPersistAcrossBuildQueryCalls(): void
    {
        $filtered = $this->repository->withFilter('persistent', 'test');
        
        // Simulate multiple buildQuery calls (as in pagination)
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($filtered->hasFilter('persistent'));
            $this->assertEquals('test', $filtered->getFilter('persistent'));
        }
    }
    
    /**
     * Test null and false filter values
     */
    public function testNullAndFalseFilterValues(): void
    {
        $filtered = $this->repository
            ->withFilter('nullFilter', null)
            ->withFilter('falseFilter', false)
            ->withFilter('zeroFilter', 0)
            ->withFilter('emptyString', '');
        
        $this->assertTrue($filtered->hasFilter('nullFilter'));
        $this->assertTrue($filtered->hasFilter('falseFilter'));
        $this->assertTrue($filtered->hasFilter('zeroFilter'));
        $this->assertTrue($filtered->hasFilter('emptyString'));
        
        $this->assertNull($filtered->getFilter('nullFilter'));
        $this->assertFalse($filtered->getFilter('falseFilter'));
        $this->assertEquals(0, $filtered->getFilter('zeroFilter'));
        $this->assertEquals('', $filtered->getFilter('emptyString'));
    }
}

/**
 * Test repository implementation
 */
class TestRepository
{
    use PersistentFiltersTrait;
    
    private EntityManagerInterface $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->persistentFilters = [];
    }
    
    /**
     * Test method to expose applyPersistentFilters for testing
     */
    public function testApplyFilters(QueryBuilder $qb): void
    {
        $this->applyPersistentFilters($qb);
    }
    
    /**
     * Convention-based filter method
     */
    protected function applyStatusFilter(QueryBuilder $qb, string $status): void
    {
        $qb->andWhere('t.status = :status')
           ->setParameter('status', $status);
    }
    
    /**
     * Generic filter handler
     */
    protected function applyFilter(QueryBuilder $qb, string $name, $value): void
    {
        $qb->andWhere("t.{$name} = :{$name}")
           ->setParameter($name, $value);
    }
    
    /**
     * Complex filter for date range
     */
    protected function applyDateRangeFilter(QueryBuilder $qb, array $range): void
    {
        $qb->andWhere('t.date BETWEEN :start AND :end')
           ->setParameter('start', $range['start'])
           ->setParameter('end', $range['end']);
    }
}