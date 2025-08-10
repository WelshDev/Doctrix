<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\SoftDeleteTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityManagerInterface;

class SoftDeleteTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    private $entityManager;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        $this->repository = new class($this->entityManager) {
            use SoftDeleteTrait;
            use EnhancedQueryTrait;
            
            protected string $alias = 'e';
            protected string $softDeleteField = 'deletedAt';
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
        };
        
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(AbstractQuery::class);
        
        $this->queryBuilder->method('expr')->willReturn(new Expr());
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testApplySoftDeleteByDefault(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IS NULL');
            }))
            ->willReturnSelf();
        
        $this->repository->buildQuery();
    }
    
    public function testWithTrashed(): void
    {
        $entities = [
            ['id' => 1, 'deletedAt' => null],
            ['id' => 2, 'deletedAt' => new \DateTime()]
        ];
        
        // Should not add soft delete constraint
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withTrashed()->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testOnlyTrashed(): void
    {
        $entities = [
            ['id' => 2, 'deletedAt' => new \DateTime()],
            ['id' => 3, 'deletedAt' => new \DateTime()]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IS NOT NULL');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->onlyTrashed()->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testSoftDelete(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        $entity->deletedAt = null;
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($entity);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->softDelete($entity);
        
        $this->assertTrue($result);
        $this->assertInstanceOf(\DateTime::class, $entity->deletedAt);
    }
    
    public function testSoftDeleteMultiple(): void
    {
        $entities = [
            (object)['id' => 1, 'deletedAt' => null],
            (object)['id' => 2, 'deletedAt' => null]
        ];
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $count = $this->repository->softDelete($entities);
        
        $this->assertEquals(2, $count);
        foreach ($entities as $entity) {
            $this->assertInstanceOf(\DateTime::class, $entity->deletedAt);
        }
    }
    
    public function testRestore(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        $entity->deletedAt = new \DateTime();
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($entity);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->restore($entity);
        
        $this->assertTrue($result);
        $this->assertNull($entity->deletedAt);
    }
    
    public function testRestoreMultiple(): void
    {
        $entities = [
            (object)['id' => 1, 'deletedAt' => new \DateTime()],
            (object)['id' => 2, 'deletedAt' => new \DateTime()]
        ];
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $count = $this->repository->restore($entities);
        
        $this->assertEquals(2, $count);
        foreach ($entities as $entity) {
            $this->assertNull($entity->deletedAt);
        }
    }
    
    public function testForceDelete(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($entity);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->forceDelete($entity);
        
        $this->assertTrue($result);
    }
    
    public function testForceDeleteMultiple(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $count = $this->repository->forceDelete($entities);
        
        $this->assertEquals(2, $count);
    }
    
    public function testIsTrashed(): void
    {
        $trashedEntity = new \stdClass();
        $trashedEntity->deletedAt = new \DateTime();
        
        $activeEntity = new \stdClass();
        $activeEntity->deletedAt = null;
        
        $this->assertTrue($this->repository->isTrashed($trashedEntity));
        $this->assertFalse($this->repository->isTrashed($activeEntity));
    }
    
    public function testPruneTrashed(): void
    {
        $oldEntities = [
            (object)['id' => 1, 'deletedAt' => new \DateTime('-40 days')],
            (object)['id' => 2, 'deletedAt' => new \DateTime('-35 days')]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($oldEntities);
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $count = $this->repository->pruneTrashed(30); // 30 days
        
        $this->assertEquals(2, $count);
    }
    
    public function testWithoutSoftDeleteScope(): void
    {
        $entities = [['id' => 1]];
        
        // Should not apply soft delete constraint
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withoutSoftDeleteScope()->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testCountTrashed(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'IS NOT NULL');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('5');
        
        $count = $this->repository->countTrashed();
        
        $this->assertEquals(5, $count);
    }
    
    public function testRestoreWhere(): void
    {
        $entities = [
            (object)['id' => 1, 'status' => 'pending', 'deletedAt' => new \DateTime()],
            (object)['id' => 2, 'status' => 'pending', 'deletedAt' => new \DateTime()]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $count = $this->repository->restoreWhere(['status' => 'pending']);
        
        $this->assertEquals(2, $count);
        foreach ($entities as $entity) {
            $this->assertNull($entity->deletedAt);
        }
    }
}