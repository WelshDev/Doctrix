<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\RelationshipCheckTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class RelationshipCheckTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    private $entityManager;
    private $metadata;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        
        $this->repository = new class($this->entityManager) {
            use RelationshipCheckTrait;
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
        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('addSelect')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
        
        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($this->metadata);
    }
    
    public function testHasRelationTrue(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        $entity->posts = new \ArrayObject([1, 2, 3]);
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $this->metadata
            ->expects($this->once())
            ->method('hasAssociation')
            ->with('posts')
            ->willReturn(true);
        
        $result = $this->repository->hasRelation(['id' => 1], 'posts');
        
        $this->assertTrue($result);
    }
    
    public function testHasRelationFalse(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        $entity->posts = new \ArrayObject();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $this->metadata
            ->expects($this->once())
            ->method('hasAssociation')
            ->with('posts')
            ->willReturn(true);
        
        $result = $this->repository->hasRelation(['id' => 1], 'posts');
        
        $this->assertFalse($result);
    }
    
    public function testHasRelationEntityNotFound(): void
    {
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        
        $result = $this->repository->hasRelation(['id' => 999], 'posts');
        
        $this->assertFalse($result);
    }
    
    public function testDoesntHaveRelationTrue(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        $entity->posts = new \ArrayObject();
        
        $this->query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);
        
        $this->metadata
            ->expects($this->once())
            ->method('hasAssociation')
            ->with('posts')
            ->willReturn(true);
        
        $result = $this->repository->doesntHaveRelation(['id' => 1], 'posts');
        
        $this->assertTrue($result);
    }
    
    public function testWithRelationBasic(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.posts', 'posts')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('addSelect')
            ->with('posts')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withRelation('posts', ['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testWithRelationsMultiple(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('leftJoin')
            ->withConsecutive(
                ['e.posts', 'posts'],
                ['e.comments', 'comments']
            )
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('addSelect')
            ->withConsecutive(['posts'], ['comments'])
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withRelations(['posts', 'comments'], ['status' => 'active']);
        
        $this->assertSame($entities, $result);
    }
    
    public function testCountRelation(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(posts.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.posts', 'posts')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('15');
        
        $result = $this->repository->countRelation(['id' => 1], 'posts');
        
        $this->assertEquals(15, $result);
    }
    
    public function testWhereHasBasic(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $subQb = $this->createMock(QueryBuilder::class);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'EXISTS');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->whereHas('posts', function($qb) {
            return $qb;
        });
        
        $this->assertSame($entities, $result);
    }
    
    public function testWhereDoesntHaveBasic(): void
    {
        $entities = [
            (object)['id' => 3],
            (object)['id' => 4]
        ];
        
        $subQb = $this->createMock(QueryBuilder::class);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->callback(function($arg) {
                return str_contains($arg, 'NOT EXISTS');
            }))
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->whereDoesntHave('posts', function($qb) {
            return $qb;
        });
        
        $this->assertSame($entities, $result);
    }
    
    public function testLoadRelationSingle(): void
    {
        $entity = new \stdClass();
        $entity->id = 1;
        
        $relatedEntity = new \stdClass();
        $relatedEntity->id = 10;
        
        $this->metadata
            ->expects($this->once())
            ->method('getAssociationMapping')
            ->with('posts')
            ->willReturn(['targetEntity' => 'App\Entity\Post']);
        
        $relatedRepo = $this->createMock('Doctrine\ORM\EntityRepository');
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Post')
            ->willReturn($relatedRepo);
        
        $relatedRepo
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([$relatedEntity]);
        
        $this->repository->loadRelation($entity, 'posts');
        
        $this->assertIsArray($entity->posts);
        $this->assertSame([$relatedEntity], $entity->posts);
    }
    
    public function testLoadRelationMultiple(): void
    {
        $entities = [
            (object)['id' => 1],
            (object)['id' => 2]
        ];
        
        $relatedEntities = [
            (object)['id' => 10],
            (object)['id' => 11]
        ];
        
        $this->metadata
            ->expects($this->once())
            ->method('getAssociationMapping')
            ->with('posts')
            ->willReturn(['targetEntity' => 'App\Entity\Post']);
        
        $relatedRepo = $this->createMock('Doctrine\ORM\EntityRepository');
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Post')
            ->willReturn($relatedRepo);
        
        $relatedRepo
            ->expects($this->once())
            ->method('findBy')
            ->willReturn($relatedEntities);
        
        $this->repository->loadRelation($entities, 'posts');
        
        foreach ($entities as $entity) {
            $this->assertIsArray($entity->posts);
        }
    }
}