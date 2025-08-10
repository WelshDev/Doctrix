<?php

namespace WelshDev\Doctrix\Tests;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

abstract class BaseTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;
    protected QueryBuilder $queryBuilder;
    protected ManagerRegistry $registry;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        
        // Setup default behaviors
        $this->queryBuilder->method('expr')->willReturn(new Expr());
        $this->queryBuilder->method('getRootAliases')->willReturn(['e']);
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('orWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('orderBy')->willReturnSelf();
        $this->queryBuilder->method('addOrderBy')->willReturnSelf();
        $this->queryBuilder->method('setMaxResults')->willReturnSelf();
        $this->queryBuilder->method('setFirstResult')->willReturnSelf();
        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('innerJoin')->willReturnSelf();
        $this->queryBuilder->method('join')->willReturnSelf();
        
        $query = $this->createMock(AbstractQuery::class);
        $this->queryBuilder->method('getQuery')->willReturn($query);
    }
    
    protected function createMockQuery(mixed $result = []): AbstractQuery
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);
        $query->method('getOneOrNullResult')->willReturn($result[0] ?? null);
        $query->method('getSingleScalarResult')->willReturn(count($result));
        
        return $query;
    }
    
    protected function assertExpressionEquals(string $expected, $expr): void
    {
        if ($expr instanceof Expr\Comparison) {
            $actual = $expr->getLeftExpr() . ' ' . $expr->getOperator() . ' ' . $expr->getRightExpr();
            $this->assertEquals($expected, $actual);
        } else {
            $this->assertEquals($expected, (string) $expr);
        }
    }
}