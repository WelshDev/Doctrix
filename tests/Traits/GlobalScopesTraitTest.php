<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\GlobalScopesTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class GlobalScopesTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    
    protected function setUp(): void
    {
        $this->repository = new class {
            use GlobalScopesTrait;
            use EnhancedQueryTrait;
            
            private $qb;
            private $testScopes = [];
            
            public function __construct()
            {
                $this->alias = 'e';
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
                return $this->alias;
            }
            
            public function setTestScopes(array $scopes)
            {
                $this->testScopes = $scopes;
            }
            
            protected function globalScopes(): array
            {
                return $this->testScopes;
            }
        };
        
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        
        $this->queryBuilder->method('expr')->willReturn(new Expr());
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testAddGlobalScope(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
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
        
        $this->repository->buildQuery();
    }
    
    public function testRemoveGlobalScope(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
        $this->repository->removeGlobalScope('active');
        
        // Should not apply the scope
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->repository->buildQuery();
    }
    
    public function testWithoutGlobalScope(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
        $entities = [['id' => 1], ['id' => 2]];
        
        // Should not apply the scope
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withoutGlobalScope('active')->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testWithoutGlobalScopes(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
        $this->repository->addGlobalScope('published', function($qb) {
            $qb->andWhere('e.publishedAt IS NOT NULL');
        });
        
        $entities = [['id' => 1], ['id' => 2], ['id' => 3]];
        
        // Should not apply any scopes
        $this->queryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withoutGlobalScopes()->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testWithOnlyGlobalScopes(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', 'active');
        });
        
        $this->repository->addGlobalScope('published', function($qb) {
            $qb->andWhere('e.publishedAt IS NOT NULL');
        });
        
        $this->repository->addGlobalScope('verified', function($qb) {
            $qb->andWhere('e.verified = true');
        });
        
        $entities = [['id' => 1]];
        
        // Should only apply 'active' and 'verified' scopes
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->withConsecutive(
                ['e.status = :status'],
                ['e.verified = true']
            )
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->withOnlyGlobalScopes(['active', 'verified'])->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testHasGlobalScope(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status');
        });
        
        $this->assertTrue($this->repository->hasGlobalScope('active'));
        $this->assertFalse($this->repository->hasGlobalScope('nonexistent'));
    }
    
    public function testGetGlobalScopes(): void
    {
        $activeScope = function($qb) {
            $qb->andWhere('e.status = :status');
        };
        
        $publishedScope = function($qb) {
            $qb->andWhere('e.publishedAt IS NOT NULL');
        };
        
        $this->repository->addGlobalScope('active', $activeScope);
        $this->repository->addGlobalScope('published', $publishedScope);
        
        $scopes = $this->repository->getGlobalScopes();
        
        $this->assertCount(2, $scopes);
        $this->assertArrayHasKey('active', $scopes);
        $this->assertArrayHasKey('published', $scopes);
        $this->assertSame($activeScope, $scopes['active']);
        $this->assertSame($publishedScope, $scopes['published']);
    }
    
    public function testApplyGlobalScopes(): void
    {
        $this->repository->setTestScopes([
            'default_active' => function($qb) {
                $qb->andWhere('e.active = true');
            },
            'default_visible' => function($qb) {
                $qb->andWhere('e.visible = true');
            }
        ]);
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->withConsecutive(
                ['e.active = true'],
                ['e.visible = true']
            )
            ->willReturnSelf();
        
        $this->repository->buildQuery();
    }
    
    public function testScopeMethod(): void
    {
        $scopeFunction = function($qb, $value) {
            $qb->andWhere('e.category = :category')
               ->setParameter('category', $value);
        };
        
        $this->repository->addGlobalScope('category', $scopeFunction);
        
        $entities = [['id' => 1, 'category' => 'electronics']];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('e.category = :category')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('category', 'electronics')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($entities);
        
        $result = $this->repository->scope('category', 'electronics')->fetch();
        
        $this->assertSame($entities, $result);
    }
    
    public function testClearGlobalScopes(): void
    {
        $this->repository->addGlobalScope('active', function($qb) {
            $qb->andWhere('e.status = :status');
        });
        
        $this->repository->addGlobalScope('published', function($qb) {
            $qb->andWhere('e.publishedAt IS NOT NULL');
        });
        
        $this->repository->clearGlobalScopes();
        
        $scopes = $this->repository->getGlobalScopes();
        
        $this->assertEmpty($scopes);
    }
    
    public function testGlobalScopeWithParameters(): void
    {
        $this->repository->addGlobalScope('minAge', function($qb) {
            $qb->andWhere('e.age >= :min_age')
               ->setParameter('min_age', 18);
        });
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('e.age >= :min_age')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('min_age', 18)
            ->willReturnSelf();
        
        $this->repository->buildQuery();
    }
    
    public function testConditionalGlobalScope(): void
    {
        $shouldApply = true;
        
        $this->repository->addGlobalScope('conditional', function($qb) use (&$shouldApply) {
            if ($shouldApply) {
                $qb->andWhere('e.condition = true');
            }
        });
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('e.condition = true')
            ->willReturnSelf();
        
        $this->repository->buildQuery();
        
        // Now test when condition is false
        $shouldApply = false;
        
        $newQb = $this->createMock(QueryBuilder::class);
        $newQb->method('expr')->willReturn(new Expr());
        $newQb->expects($this->never())->method('andWhere');
        
        $this->repository->setQueryBuilder($newQb);
        $this->repository->buildQuery();
    }
}