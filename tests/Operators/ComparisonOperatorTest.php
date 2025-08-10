<?php

namespace WelshDev\Doctrix\Tests\Operators;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Operators\ComparisonOperator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;

class ComparisonOperatorTest extends TestCase
{
    private ComparisonOperator $operator;
    private QueryBuilder $queryBuilder;
    
    protected function setUp(): void
    {
        $this->operator = new ComparisonOperator();
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->queryBuilder->method('expr')->willReturn(new Expr());
    }
    
    public function testSupportsEqualOperators(): void
    {
        $this->assertTrue($this->operator->supports('='));
        $this->assertTrue($this->operator->supports('eq'));
    }
    
    public function testSupportsNotEqualOperators(): void
    {
        $this->assertTrue($this->operator->supports('!='));
        $this->assertTrue($this->operator->supports('neq'));
        $this->assertTrue($this->operator->supports('<>'));
    }
    
    public function testSupportsComparisonOperators(): void
    {
        $this->assertTrue($this->operator->supports('<'));
        $this->assertTrue($this->operator->supports('lt'));
        $this->assertTrue($this->operator->supports('<='));
        $this->assertTrue($this->operator->supports('lte'));
        $this->assertTrue($this->operator->supports('>'));
        $this->assertTrue($this->operator->supports('gt'));
        $this->assertTrue($this->operator->supports('>='));
        $this->assertTrue($this->operator->supports('gte'));
    }
    
    public function testDoesNotSupportInvalidOperators(): void
    {
        $this->assertFalse($this->operator->supports('like'));
        $this->assertFalse($this->operator->supports('in'));
        $this->assertFalse($this->operator->supports('between'));
        $this->assertFalse($this->operator->supports('invalid'));
    }
    
    public function testApplyEqualOperator(): void
    {
        $paramName = 'param_' . uniqid();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->stringContains('param_'), 'value')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'field', 'value', '=', 'e');
    }
    
    public function testApplyNotEqualOperator(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'field', 'value', '!=', 'e');
    }
    
    public function testApplyGreaterThanOperator(): void
    {
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->stringContains('param_'), 100)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'age', 100, '>', 'e');
    }
    
    public function testApplyWithNullValue(): void
    {
        // When value is null, should use IS NULL instead of =
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->stringContains('IS NULL'))
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('setParameter');
        
        $this->operator->apply($this->queryBuilder, 'field', null, '=', 'e');
    }
    
    public function testApplyNotEqualWithNullValue(): void
    {
        // When value is null with !=, should use IS NOT NULL
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with($this->stringContains('IS NOT NULL'))
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('setParameter');
        
        $this->operator->apply($this->queryBuilder, 'field', null, '!=', 'e');
    }
    
    public function testOperatorAliases(): void
    {
        // Test that aliases work correctly
        $operators = [
            '=' => 'eq',
            '!=' => 'neq',
            '<' => 'lt',
            '<=' => 'lte',
            '>' => 'gt',
            '>=' => 'gte'
        ];
        
        foreach ($operators as $operator => $alias) {
            $this->assertTrue($this->operator->supports($operator));
            $this->assertTrue($this->operator->supports($alias));
        }
    }
    
    public function testApplyWithDifferentTypes(): void
    {
        // Test with integer
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->anything(), 42)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'age', 42, '=', 'e');
        
        // Reset mocks
        $this->setUp();
        
        // Test with boolean
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->anything(), true)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'active', true, '=', 'e');
        
        // Reset mocks
        $this->setUp();
        
        // Test with DateTime
        $date = new \DateTime();
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->anything(), $date)
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->operator->apply($this->queryBuilder, 'created', $date, '>=', 'e');
    }
}