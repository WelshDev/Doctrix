<?php

namespace WelshDev\Doctrix\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\QueryBuilder\JoinManager;
use Doctrine\ORM\QueryBuilder;

class JoinManagerTest extends TestCase
{
    private JoinManager $joinManager;
    private QueryBuilder $queryBuilder;
    
    protected function setUp(): void
    {
        $this->joinManager = new JoinManager();
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
    }
    
    public function testApplyLeftJoin(): void
    {
        $joins = [
            ['leftJoin', 'e.profile', 'p']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.profile', 'p')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testApplyInnerJoin(): void
    {
        $joins = [
            ['innerJoin', 'e.roles', 'r']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('innerJoin')
            ->with('e.roles', 'r')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testApplyMultipleJoins(): void
    {
        $joins = [
            ['leftJoin', 'e.profile', 'p'],
            ['innerJoin', 'e.roles', 'r'],
            ['leftJoin', 'p.address', 'a']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.profile', 'p')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('innerJoin')
            ->with('e.roles', 'r')
            ->willReturnSelf();
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('p.address', 'a')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testApplyJoinWithCondition(): void
    {
        $joins = [
            ['leftJoin', 'e.profile', 'p', 'WITH', 'p.active = true']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.profile', 'p', 'WITH', 'p.active = true')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testApplyEmptyJoins(): void
    {
        $this->queryBuilder
            ->expects($this->never())
            ->method('leftJoin');
        
        $this->queryBuilder
            ->expects($this->never())
            ->method('innerJoin');
        
        $this->joinManager->applyJoins($this->queryBuilder, []);
    }
    
    public function testApplyGenericJoin(): void
    {
        $joins = [
            ['join', 'e.something', 's']
        ];
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('join')
            ->with('e.something', 's')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testDetectJoinsFromCriteria(): void
    {
        $criteria = [
            'profile.verified' => true,
            'address.city' => 'New York',
            'regular_field' => 'value'
        ];
        
        $detectedJoins = $this->joinManager->detectJoins($criteria, 'e');
        
        $expectedJoins = [
            ['leftJoin', 'e.profile', 'profile'],
            ['leftJoin', 'e.address', 'address']
        ];
        
        $this->assertEquals($expectedJoins, $detectedJoins);
    }
    
    public function testDetectNestedJoins(): void
    {
        $criteria = [
            'profile.address.city' => 'New York',
            'profile.verified' => true
        ];
        
        $detectedJoins = $this->joinManager->detectJoins($criteria, 'e');
        
        // Should detect both profile and nested address joins
        $this->assertCount(2, $detectedJoins);
        $this->assertContains(['leftJoin', 'e.profile', 'profile'], $detectedJoins);
        $this->assertContains(['leftJoin', 'profile.address', 'address'], $detectedJoins);
    }
    
    public function testNoDuplicateJoins(): void
    {
        $criteria = [
            'profile.verified' => true,
            'profile.active' => true,
            'profile.name' => 'John'
        ];
        
        $detectedJoins = $this->joinManager->detectJoins($criteria, 'e');
        
        // Should only have one join for profile despite multiple fields
        $this->assertCount(1, $detectedJoins);
        $this->assertEquals([['leftJoin', 'e.profile', 'profile']], $detectedJoins);
    }
    
    public function testApplyJoinOnce(): void
    {
        $joins = [
            ['leftJoin', 'e.profile', 'p'],
            ['leftJoin', 'e.profile', 'p'],  // Duplicate
        ];
        
        // Should only call leftJoin once for the same join
        $this->queryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('e.profile', 'p')
            ->willReturnSelf();
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
    
    public function testInvalidJoinFormat(): void
    {
        $joins = [
            ['invalidType', 'e.profile']  // Missing alias
        ];
        
        // Should handle gracefully (skip invalid joins)
        $this->queryBuilder
            ->expects($this->never())
            ->method($this->anything());
        
        $this->joinManager->applyJoins($this->queryBuilder, $joins);
    }
}