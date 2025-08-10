<?php

namespace WelshDev\Doctrix\Tests\Operators;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Operators\OperatorRegistry;
use WelshDev\Doctrix\Operators\ComparisonOperator;
use WelshDev\Doctrix\Operators\TextOperator;
use WelshDev\Doctrix\Operators\NullOperator;
use WelshDev\Doctrix\Operators\CollectionOperator;
use WelshDev\Doctrix\Interfaces\OperatorInterface;
use Doctrine\ORM\QueryBuilder;

class OperatorRegistryTest extends TestCase
{
    private OperatorRegistry $registry;
    
    protected function setUp(): void
    {
        $this->registry = new OperatorRegistry();
    }
    
    public function testGetComparisonOperator(): void
    {
        $operator = $this->registry->get('=');
        $this->assertInstanceOf(ComparisonOperator::class, $operator);
        
        $operator = $this->registry->get('eq');
        $this->assertInstanceOf(ComparisonOperator::class, $operator);
        
        $operator = $this->registry->get('>');
        $this->assertInstanceOf(ComparisonOperator::class, $operator);
        
        $operator = $this->registry->get('gte');
        $this->assertInstanceOf(ComparisonOperator::class, $operator);
    }
    
    public function testGetTextOperator(): void
    {
        $operator = $this->registry->get('like');
        $this->assertInstanceOf(TextOperator::class, $operator);
        
        $operator = $this->registry->get('contains');
        $this->assertInstanceOf(TextOperator::class, $operator);
        
        $operator = $this->registry->get('starts_with');
        $this->assertInstanceOf(TextOperator::class, $operator);
        
        $operator = $this->registry->get('ends_with');
        $this->assertInstanceOf(TextOperator::class, $operator);
    }
    
    public function testGetNullOperator(): void
    {
        $operator = $this->registry->get('is_null');
        $this->assertInstanceOf(NullOperator::class, $operator);
        
        $operator = $this->registry->get('is_not_null');
        $this->assertInstanceOf(NullOperator::class, $operator);
    }
    
    public function testGetCollectionOperator(): void
    {
        $operator = $this->registry->get('in');
        $this->assertInstanceOf(CollectionOperator::class, $operator);
        
        $operator = $this->registry->get('not_in');
        $this->assertInstanceOf(CollectionOperator::class, $operator);
        
        $operator = $this->registry->get('between');
        $this->assertInstanceOf(CollectionOperator::class, $operator);
        
        $operator = $this->registry->get('not_between');
        $this->assertInstanceOf(CollectionOperator::class, $operator);
    }
    
    public function testHasOperator(): void
    {
        $this->assertTrue($this->registry->has('='));
        $this->assertTrue($this->registry->has('like'));
        $this->assertTrue($this->registry->has('in'));
        $this->assertTrue($this->registry->has('is_null'));
        
        $this->assertFalse($this->registry->has('invalid_operator'));
        $this->assertFalse($this->registry->has(''));
    }
    
    public function testGetUnknownOperator(): void
    {
        $operator = $this->registry->get('unknown_operator');
        $this->assertNull($operator);
    }
    
    public function testRegisterCustomOperator(): void
    {
        // Create a custom operator
        $customOperator = new class implements OperatorInterface {
            public function supports(string $operator): bool
            {
                return $operator === 'custom';
            }
            
            public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
            {
                // Custom implementation
                return "$field = :$paramName";
            }
        };
        
        $this->registry->register($customOperator);
        
        $this->assertTrue($this->registry->has('custom'));
        $operator = $this->registry->get('custom');
        $this->assertSame($customOperator, $operator);
    }
    
    public function testRegisterOverridesExisting(): void
    {
        // Create a custom operator that overrides '='
        $customOperator = new class implements OperatorInterface {
            public function supports(string $operator): bool
            {
                return $operator === '=';
            }
            
            public function apply(QueryBuilder $qb, string $field, mixed $value, string $paramName): string
            {
                // Custom implementation
                return "$field = :$paramName";
            }
        };
        
        // Original should be ComparisonOperator
        $original = $this->registry->get('=');
        $this->assertInstanceOf(ComparisonOperator::class, $original);
        
        // Register custom operator
        $this->registry->register($customOperator);
        
        // Now should be our custom operator
        $operator = $this->registry->get('=');
        $this->assertSame($customOperator, $operator);
        $this->assertNotSame($original, $operator);
    }
    
    public function testOperatorAliases(): void
    {
        // Test that aliases resolve to the same operator instance
        $eq1 = $this->registry->get('=');
        $eq2 = $this->registry->get('eq');
        
        // Both should be ComparisonOperator
        $this->assertInstanceOf(ComparisonOperator::class, $eq1);
        $this->assertInstanceOf(ComparisonOperator::class, $eq2);
        
        // Test greater than aliases
        $gt1 = $this->registry->get('>');
        $gt2 = $this->registry->get('gt');
        
        $this->assertInstanceOf(ComparisonOperator::class, $gt1);
        $this->assertInstanceOf(ComparisonOperator::class, $gt2);
    }
    
    public function testAllDefaultOperators(): void
    {
        $operators = [
            // Comparison
            '=', 'eq', '!=', 'neq', '<>', '<', 'lt', '<=', 'lte', '>', 'gt', '>=', 'gte',
            // Text
            'like', 'not_like', 'contains', 'starts_with', 'ends_with',
            // Null
            'is_null', 'is_not_null',
            // Collection
            'in', 'not_in', 'between', 'not_between'
        ];
        
        foreach ($operators as $op) {
            $this->assertTrue($this->registry->has($op), "Operator '$op' should be registered");
            $this->assertNotNull($this->registry->get($op), "Operator '$op' should return an instance");
        }
    }
    
    public function testCaseSensitivity(): void
    {
        // Operators should be case-sensitive
        $this->assertTrue($this->registry->has('like'));
        $this->assertFalse($this->registry->has('LIKE'));
        $this->assertFalse($this->registry->has('Like'));
        
        $this->assertNotNull($this->registry->get('like'));
        $this->assertNull($this->registry->get('LIKE'));
    }
}