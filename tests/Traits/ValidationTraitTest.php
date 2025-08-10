<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\ValidationTrait;
use WelshDev\Doctrix\Traits\EnhancedQueryTrait;
use WelshDev\Doctrix\Exceptions\DuplicateEntityException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityManagerInterface;

class ValidationTraitTest extends TestCase
{
    private $repository;
    private $queryBuilder;
    private $query;
    private $entityManager;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        $this->repository = new class($this->entityManager) {
            use ValidationTrait;
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
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        
        $this->repository->setQueryBuilder($this->queryBuilder);
    }
    
    public function testIsUniqueTrue(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');
        
        $result = $this->repository->isUnique(['email' => 'unique@example.com']);
        
        $this->assertTrue($result);
    }
    
    public function testIsUniqueFalse(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('1');
        
        $result = $this->repository->isUnique(['email' => 'existing@example.com']);
        
        $this->assertFalse($result);
    }
    
    public function testIsUniqueWithExclude(): void
    {
        $entity = new \stdClass();
        $entity->id = 123;
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');
        
        $result = $this->repository->isUnique(['email' => 'test@example.com'], $entity);
        
        $this->assertTrue($result);
    }
    
    public function testValidateUniquenessNoConflicts(): void
    {
        $entity = new \stdClass();
        $entity->email = 'new@example.com';
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');
        
        $result = $this->repository->validateUniqueness($entity, ['email']);
        
        $this->assertTrue($result);
    }
    
    public function testValidateUniquenessWithConflicts(): void
    {
        $entity = new \stdClass();
        $entity->email = 'existing@example.com';
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('1');
        
        $result = $this->repository->validateUniqueness($entity, ['email']);
        
        $this->assertFalse($result);
    }
    
    public function testEnsureUniqueSuccess(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');
        
        $result = $this->repository->ensureUnique(['email' => 'unique@example.com']);
        
        $this->assertTrue($result);
    }
    
    public function testEnsureUniqueThrowsException(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('1');
        
        $this->expectException(DuplicateEntityException::class);
        $this->expectExceptionMessage('App\Entity\TestEntity already exists with criteria: {"email":"duplicate@example.com"}');
        
        $this->repository->ensureUnique(['email' => 'duplicate@example.com']);
    }
    
    public function testFindDuplicates(): void
    {
        $duplicates = [
            ['email' => 'dup1@example.com', 'count' => 3],
            ['email' => 'dup2@example.com', 'count' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('e.email, COUNT(e.id) as duplicate_count')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('groupBy')
            ->with('e.email')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('having')
            ->with('duplicate_count > 1')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($duplicates);
        
        $result = $this->repository->findDuplicates(['email']);
        
        $this->assertSame($duplicates, $result);
    }
    
    public function testFindDuplicatesMultipleFields(): void
    {
        $duplicates = [
            ['email' => 'test@example.com', 'username' => 'testuser', 'count' => 2]
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('e.email, e.username, COUNT(e.id) as duplicate_count')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('groupBy')
            ->with('e.email, e.username')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('having')
            ->with('duplicate_count > 1')
            ->willReturnSelf();
        
        $this->query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($duplicates);
        
        $result = $this->repository->findDuplicates(['email', 'username']);
        
        $this->assertSame($duplicates, $result);
    }
    
    public function testRemoveDuplicatesKeepFirst(): void
    {
        // First query to find duplicates
        $duplicates = [
            ['email' => 'dup@example.com', 'count' => 3]
        ];
        
        // Second query to get duplicate entities
        $entities = [
            (object)['id' => 1, 'email' => 'dup@example.com'],
            (object)['id' => 2, 'email' => 'dup@example.com'],
            (object)['id' => 3, 'email' => 'dup@example.com']
        ];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls($duplicates, $entities);
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                [$entities[1]],
                [$entities[2]]
            );
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->removeDuplicates(['email'], 'first');
        
        $this->assertEquals(2, $result);
    }
    
    public function testRemoveDuplicatesKeepLast(): void
    {
        // First query to find duplicates
        $duplicates = [
            ['email' => 'dup@example.com', 'count' => 3]
        ];
        
        // Second query to get duplicate entities
        $entities = [
            (object)['id' => 1, 'email' => 'dup@example.com'],
            (object)['id' => 2, 'email' => 'dup@example.com'],
            (object)['id' => 3, 'email' => 'dup@example.com']
        ];
        
        $this->query
            ->method('getResult')
            ->willReturnOnConsecutiveCalls($duplicates, $entities);
        
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                [$entities[0]],
                [$entities[1]]
            );
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->repository->removeDuplicates(['email'], 'last');
        
        $this->assertEquals(2, $result);
    }
    
    public function testValidateDataBasic(): void
    {
        $data = [
            'email' => 'valid@example.com',
            'age' => 25,
            'status' => 'active'
        ];
        
        $rules = [
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:18'],
            'status' => ['required', 'in:active,inactive']
        ];
        
        $result = $this->repository->validateData($data, $rules);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    public function testValidateDataWithErrors(): void
    {
        $data = [
            'email' => 'invalid-email',
            'age' => 15,
            'status' => 'unknown'
        ];
        
        $rules = [
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:18'],
            'status' => ['required', 'in:active,inactive']
        ];
        
        $result = $this->repository->validateData($data, $rules);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('age', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
    }
}