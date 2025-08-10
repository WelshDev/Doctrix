<?php

namespace WelshDev\Doctrix\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\BaseRepository;

/**
 * Integration test to verify all traits work together
 */
class AllTraitsTest extends TestCase
{
    private $repository;
    private $entityManager;
    
    protected function setUp(): void
    {
        // Create mock entity manager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Create mock query builder
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturnSelf();
        $queryBuilder->method('getResult')->willReturn([]);
        $queryBuilder->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('getSingleScalarResult')->willReturn(0);
        
        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        
        // Create test repository
        $this->repository = new class($this->entityManager) extends BaseRepository {
            private $em;
            
            public function __construct($em)
            {
                $this->em = $em;
                $this->alias = 'test';
            }
            
            public function getEntityManager()
            {
                return $this->em;
            }
            
            public function getEntityName()
            {
                return 'TestEntity';
            }
            
            public function createQueryBuilder($alias)
            {
                return $this->em->createQueryBuilder();
            }
        };
    }
    
    public function testFetchOrFailTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'fetchOneOrFail'));
        $this->assertTrue(method_exists($this->repository, 'fetchOneOrCreate'));
        $this->assertTrue(method_exists($this->repository, 'updateOrCreate'));
        $this->assertTrue(method_exists($this->repository, 'sole'));
    }
    
    public function testChunkProcessingTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'chunk'));
        $this->assertTrue(method_exists($this->repository, 'each'));
        $this->assertTrue(method_exists($this->repository, 'lazy'));
        $this->assertTrue(method_exists($this->repository, 'batchProcess'));
        $this->assertTrue(method_exists($this->repository, 'map'));
    }
    
    public function testExistenceCheckTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'exists'));
        $this->assertTrue(method_exists($this->repository, 'doesntExist'));
        $this->assertTrue(method_exists($this->repository, 'hasExactly'));
        $this->assertTrue(method_exists($this->repository, 'hasAtLeast'));
        $this->assertTrue(method_exists($this->repository, 'hasAtMost'));
    }
    
    public function testRandomSelectionTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'random'));
        $this->assertTrue(method_exists($this->repository, 'randomWhere'));
        $this->assertTrue(method_exists($this->repository, 'randomOrNull'));
        $this->assertTrue(method_exists($this->repository, 'weightedRandom'));
    }
    
    public function testRelationshipCheckTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'has'));
        $this->assertTrue(method_exists($this->repository, 'doesntHave'));
        $this->assertTrue(method_exists($this->repository, 'hasCount'));
        $this->assertTrue(method_exists($this->repository, 'hasAnyRelation'));
        $this->assertTrue(method_exists($this->repository, 'hasAllRelations'));
    }
    
    public function testValidationTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'isUnique'));
        $this->assertTrue(method_exists($this->repository, 'ensureUnique'));
        $this->assertTrue(method_exists($this->repository, 'isUniqueCombination'));
        $this->assertTrue(method_exists($this->repository, 'fetchDuplicates'));
        $this->assertTrue(method_exists($this->repository, 'removeDuplicates'));
    }
    
    public function testRequestQueryTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'fromRequest'));
        $this->assertTrue(method_exists($this->repository, 'paginateFromRequest'));
    }
    
    public function testBulkOperationsTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'bulkUpdate'));
        $this->assertTrue(method_exists($this->repository, 'bulkDelete'));
    }
    
    public function testMacroableTraitMethods()
    {
        $this->assertTrue(method_exists($this->repository, 'macro'));
        $this->assertTrue(method_exists($this->repository, 'hasMacro'));
    }
}