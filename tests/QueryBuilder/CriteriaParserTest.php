<?php

namespace WelshDev\Doctrix\Tests\QueryBuilder;

use WelshDev\Doctrix\Tests\BaseTestCase;
use WelshDev\Doctrix\QueryBuilder\CriteriaParser;
use WelshDev\Doctrix\Operators\OperatorRegistry;
use Doctrine\ORM\Query\Expr;

class CriteriaParserTest extends BaseTestCase
{
    private CriteriaParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CriteriaParser(new OperatorRegistry());
    }
    
    public function testParseEmptyCriteria(): void
    {
        $result = $this->parser->parse([], $this->queryBuilder, 'e');
        $this->assertNull($result);
    }
    
    public function testParseSimpleEqualityCriteria(): void
    {
        $criteria = ['status' => 'active'];
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Comparison::class, $result);
    }
    
    public function testParseMultipleEqualityCriteria(): void
    {
        $criteria = [
            'status' => 'active',
            'role' => 'admin'
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
    
    public function testParseOperatorCriteria(): void
    {
        $criteria = [
            ['age', 'gte', 18],
            ['age', 'lte', 65]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
    
    public function testParseOrCondition(): void
    {
        $criteria = [
            ['or', [
                'status' => 'active',
                'role' => 'admin'
            ]]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Orx::class, $result);
    }
    
    public function testParseNestedConditions(): void
    {
        $criteria = [
            'type' => 'user',
            ['or', [
                ['status' => 'active'],
                ['and', [
                    'verified' => true,
                    'premium' => true
                ]]
            ]]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
    
    public function testParseInOperator(): void
    {
        $criteria = [
            ['status', 'in', ['active', 'pending', 'verified']]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Func::class, $result);
    }
    
    public function testParseBetweenOperator(): void
    {
        $criteria = [
            ['age', 'between', [18, 65]]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        // BETWEEN creates an AND expression
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
    
    public function testParseIsNullOperator(): void
    {
        $criteria = [
            ['deletedAt', 'is_null', true]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertEquals('e.deletedAt IS NULL', (string) $result);
    }
    
    public function testParseIsNotNullOperator(): void
    {
        $criteria = [
            ['verifiedAt', 'is_not_null', true]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertEquals('e.verifiedAt IS NOT NULL', (string) $result);
    }
    
    public function testParseLikeOperator(): void
    {
        $criteria = [
            ['name', 'like', '%john%']
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Comparison::class, $result);
    }
    
    public function testParseContainsOperator(): void
    {
        $criteria = [
            ['email', 'contains', 'gmail']
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Comparison::class, $result);
    }
    
    public function testParseStartsWithOperator(): void
    {
        $criteria = [
            ['name', 'starts_with', 'John']
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Comparison::class, $result);
    }
    
    public function testParseNotOperator(): void
    {
        $criteria = [
            ['not', ['status' => 'banned']]
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        // NOT is implemented as != in most cases
        $this->assertInstanceOf(Expr\Comparison::class, $result);
    }
    
    public function testParseDottedFieldNames(): void
    {
        $criteria = [
            'profile.verified' => true
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Comparison::class, $result);
        // Should preserve the dotted notation
        $this->assertStringContainsString('profile.verified', (string) $result);
    }
    
    public function testParseComplexMixedCriteria(): void
    {
        $criteria = [
            'status' => 'active',
            ['age', '>=', 18],
            ['role', 'in', ['user', 'admin']],
            ['or', [
                'verified' => true,
                ['credits', '>', 100]
            ]],
            ['email', 'not_like', '%spam%']
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
    
    public function testApplyCriteria(): void
    {
        $criteria = ['status' => 'active'];
        
        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with($this->anything(), 'active')
            ->willReturnSelf();
        
        $this->parser->applyCriteria($this->queryBuilder, $criteria, 'e');
    }
    
    public function testApplyCriteriaWithMultipleParameters(): void
    {
        $criteria = [
            'status' => 'active',
            ['age', '>=', 18]
        ];
        
        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->parser->applyCriteria($this->queryBuilder, $criteria, 'e');
    }
    
    public function testInvalidOperator(): void
    {
        $criteria = [
            ['field', 'invalid_operator', 'value']
        ];
        
        // Should handle gracefully, likely treating as equality
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        $this->assertNotNull($result);
    }
    
    public function testNullValues(): void
    {
        $criteria = [
            'field' => null
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        // Null should be converted to IS NULL
        $this->assertStringContainsString('IS NULL', (string) $result);
    }
    
    public function testBooleanValues(): void
    {
        $criteria = [
            'active' => true,
            'deleted' => false
        ];
        
        $result = $this->parser->parse($criteria, $this->queryBuilder, 'e');
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(Expr\Andx::class, $result);
    }
}