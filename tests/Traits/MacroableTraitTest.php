<?php

namespace WelshDev\Doctrix\Tests\Traits;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Traits\MacroableTrait;
use WelshDev\Doctrix\QueryBuilder\FluentQueryBuilder;
use BadMethodCallException;

class MacroableTraitTest extends TestCase
{
    private $macroable;
    
    protected function setUp(): void
    {
        // Create a test class that uses the trait
        $this->macroable = new class {
            use MacroableTrait;
            
            public function existingMethod()
            {
                return 'existing';
            }
        };
        
        // Clear any previously registered macros
        $this->macroable::clearMacros();
    }
    
    protected function tearDown(): void
    {
        // Clean up macros after each test
        if ($this->macroable) {
            $this->macroable::clearMacros();
        }
    }
    
    public function testRegisterMacro(): void
    {
        $this->macroable->registerMacro('testMethod', function() {
            return 'macro result';
        });
        
        $this->assertTrue($this->macroable::hasMacro('testMethod'));
        $this->assertEquals('macro result', $this->macroable->testMethod());
    }
    
    public function testRegisterMultipleMacros(): void
    {
        $this->macroable->registerMacros([
            'method1' => fn() => 'result1',
            'method2' => fn() => 'result2',
            'method3' => fn() => 'result3'
        ]);
        
        $this->assertTrue($this->macroable::hasMacro('method1'));
        $this->assertTrue($this->macroable::hasMacro('method2'));
        $this->assertTrue($this->macroable::hasMacro('method3'));
        
        $this->assertEquals('result1', $this->macroable->method1());
        $this->assertEquals('result2', $this->macroable->method2());
        $this->assertEquals('result3', $this->macroable->method3());
    }
    
    public function testMacroWithParameters(): void
    {
        $this->macroable->registerMacro('greet', function($name) {
            return "Hello, $name!";
        });
        
        $this->assertEquals('Hello, John!', $this->macroable->greet('John'));
        $this->assertEquals('Hello, Jane!', $this->macroable->greet('Jane'));
    }
    
    public function testMacroWithMultipleParameters(): void
    {
        $this->macroable->registerMacro('add', function($a, $b) {
            return $a + $b;
        });
        
        $this->assertEquals(5, $this->macroable->add(2, 3));
        $this->assertEquals(10, $this->macroable->add(7, 3));
    }
    
    public function testMacroAccessingThis(): void
    {
        $this->macroable->registerMacro('callExisting', function() {
            return $this->existingMethod() . ' from macro';
        });
        
        $this->assertEquals('existing from macro', $this->macroable->callExisting());
    }
    
    public function testRemoveMacro(): void
    {
        $this->macroable->registerMacro('temporary', fn() => 'temp');
        $this->assertTrue($this->macroable::hasMacro('temporary'));
        
        $this->macroable::removeMacro('temporary');
        $this->assertFalse($this->macroable::hasMacro('temporary'));
    }
    
    public function testClearMacros(): void
    {
        $this->macroable->registerMacros([
            'macro1' => fn() => '1',
            'macro2' => fn() => '2',
            'macro3' => fn() => '3'
        ]);
        
        $this->assertTrue($this->macroable::hasMacro('macro1'));
        $this->assertTrue($this->macroable::hasMacro('macro2'));
        $this->assertTrue($this->macroable::hasMacro('macro3'));
        
        $this->macroable::clearMacros();
        
        $this->assertFalse($this->macroable::hasMacro('macro1'));
        $this->assertFalse($this->macroable::hasMacro('macro2'));
        $this->assertFalse($this->macroable::hasMacro('macro3'));
    }
    
    public function testGetMacros(): void
    {
        $macro1 = fn() => '1';
        $macro2 = fn() => '2';
        
        $this->macroable->registerMacro('macro1', $macro1);
        $this->macroable->registerMacro('macro2', $macro2);
        
        $macros = $this->macroable::getMacros();
        
        $this->assertCount(2, $macros);
        $this->assertArrayHasKey('macro1', $macros);
        $this->assertArrayHasKey('macro2', $macros);
    }
    
    public function testCallNonExistentMacroThrowsException(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('does not exist');
        
        $this->macroable->nonExistentMethod();
    }
    
    public function testGlobalMacroRegistration(): void
    {
        $class = get_class($this->macroable);
        $class::registerGlobalMacro('globalMethod', fn() => 'global result');
        
        $this->assertTrue($class::hasMacro('globalMethod'));
        $this->assertEquals('global result', $this->macroable->globalMethod());
        
        // Create another instance
        $another = new $class();
        $this->assertEquals('global result', $another->globalMethod());
    }
    
    public function testStaticMacroCalls(): void
    {
        $class = get_class($this->macroable);
        $class::registerGlobalMacro('staticMethod', fn() => 'static result');
        
        $this->assertEquals('static result', $class::staticMethod());
    }
    
    public function testMacroChaining(): void
    {
        $builder = new class {
            use MacroableTrait;
            
            private $value = 0;
            
            public function getValue()
            {
                return $this->value;
            }
        };
        
        $builder::clearMacros();
        
        $builder->registerMacro('add', function($n) {
            $this->value += $n;
            return $this;
        });
        
        $builder->registerMacro('multiply', function($n) {
            $this->value *= $n;
            return $this;
        });
        
        $result = $builder
            ->add(5)
            ->multiply(3)
            ->add(10)
            ->getValue();
        
        $this->assertEquals(25, $result); // (0 + 5) * 3 + 10 = 25
    }
    
    public function testFluentQueryBuilderMacros(): void
    {
        // Mock the FluentQueryBuilder
        $queryBuilder = $this->getMockBuilder(FluentQueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'orderBy'])
            ->getMock();
        
        $queryBuilder->expects($this->exactly(2))
            ->method('where')
            ->willReturnSelf();
        
        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->willReturnSelf();
        
        // Register a macro
        $queryBuilder::registerGlobalMacro('activeAdmins', function($query) {
            return $query
                ->where('status', 'active')
                ->where('role', 'admin')
                ->orderBy('name', 'ASC');
        });
        
        // Test the macro
        $result = $queryBuilder->activeAdmins($queryBuilder);
        $this->assertSame($queryBuilder, $result);
    }
    
    public function testMacroWithDefaultParameters(): void
    {
        $this->macroable->registerMacro('greetWithDefault', function($name = 'World') {
            return "Hello, $name!";
        });
        
        $this->assertEquals('Hello, World!', $this->macroable->greetWithDefault());
        $this->assertEquals('Hello, John!', $this->macroable->greetWithDefault('John'));
    }
    
    public function testMacroReturningClosure(): void
    {
        $this->macroable->registerMacro('getMultiplier', function($factor) {
            return function($value) use ($factor) {
                return $value * $factor;
            };
        });
        
        $double = $this->macroable->getMultiplier(2);
        $triple = $this->macroable->getMultiplier(3);
        
        $this->assertEquals(10, $double(5));
        $this->assertEquals(15, $triple(5));
    }
    
    public function testMacroOverride(): void
    {
        $this->macroable->registerMacro('test', fn() => 'first');
        $this->assertEquals('first', $this->macroable->test());
        
        $this->macroable->registerMacro('test', fn() => 'second');
        $this->assertEquals('second', $this->macroable->test());
    }
    
    public function testMacroIsolationBetweenClasses(): void
    {
        $class1 = new class {
            use MacroableTrait;
        };
        
        $class2 = new class {
            use MacroableTrait;
        };
        
        $class1::clearMacros();
        $class2::clearMacros();
        
        $class1->registerMacro('method1', fn() => 'class1');
        $class2->registerMacro('method2', fn() => 'class2');
        
        $this->assertTrue($class1::hasMacro('method1'));
        $this->assertFalse($class1::hasMacro('method2'));
        
        $this->assertFalse($class2::hasMacro('method1'));
        $this->assertTrue($class2::hasMacro('method2'));
    }
}