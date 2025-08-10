<?php

namespace WelshDev\Doctrix\Tests\Pagination;

use PHPUnit\Framework\TestCase;
use WelshDev\Doctrix\Pagination\PaginationResult;

class PaginationResultTest extends TestCase
{
    public function testConstructorCalculations(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ];
        
        $pagination = new PaginationResult($items, 100, 2, 10);
        
        $this->assertEquals($items, $pagination->items);
        $this->assertEquals(100, $pagination->total);
        $this->assertEquals(2, $pagination->page);
        $this->assertEquals(10, $pagination->perPage);
        $this->assertEquals(10, $pagination->lastPage);
        $this->assertTrue($pagination->hasMore);
        $this->assertTrue($pagination->hasPrevious);
        $this->assertEquals(3, $pagination->nextPage);
        $this->assertEquals(1, $pagination->previousPage);
        $this->assertEquals(11, $pagination->from);
        $this->assertEquals(13, $pagination->to);
    }
    
    public function testFirstPage(): void
    {
        $items = array_fill(0, 10, ['item']);
        $pagination = new PaginationResult($items, 50, 1, 10);
        
        $this->assertEquals(1, $pagination->page);
        $this->assertEquals(5, $pagination->lastPage);
        $this->assertTrue($pagination->hasMore);
        $this->assertFalse($pagination->hasPrevious);
        $this->assertEquals(2, $pagination->nextPage);
        $this->assertNull($pagination->previousPage);
        $this->assertEquals(1, $pagination->from);
        $this->assertEquals(10, $pagination->to);
        $this->assertTrue($pagination->onFirstPage());
        $this->assertFalse($pagination->onLastPage());
    }
    
    public function testLastPage(): void
    {
        $items = array_fill(0, 5, ['item']);
        $pagination = new PaginationResult($items, 25, 3, 10);
        
        $this->assertEquals(3, $pagination->page);
        $this->assertEquals(3, $pagination->lastPage);
        $this->assertFalse($pagination->hasMore);
        $this->assertTrue($pagination->hasPrevious);
        $this->assertNull($pagination->nextPage);
        $this->assertEquals(2, $pagination->previousPage);
        $this->assertEquals(21, $pagination->from);
        $this->assertEquals(25, $pagination->to);
        $this->assertFalse($pagination->onFirstPage());
        $this->assertTrue($pagination->onLastPage());
    }
    
    public function testEmptyResults(): void
    {
        $pagination = new PaginationResult([], 0, 1, 10);
        
        $this->assertEquals([], $pagination->items);
        $this->assertEquals(0, $pagination->total);
        $this->assertEquals(1, $pagination->page);
        $this->assertEquals(1, $pagination->lastPage);
        $this->assertFalse($pagination->hasMore);
        $this->assertFalse($pagination->hasPrevious);
        $this->assertNull($pagination->nextPage);
        $this->assertNull($pagination->previousPage);
        $this->assertEquals(0, $pagination->from);
        $this->assertEquals(0, $pagination->to);
        $this->assertTrue($pagination->isEmpty());
        $this->assertFalse($pagination->isNotEmpty());
    }
    
    public function testIteratorAggregate(): void
    {
        $items = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        
        $pagination = new PaginationResult($items, 3, 1, 10);
        
        // Test iteration
        $iteratedItems = [];
        foreach ($pagination as $item) {
            $iteratedItems[] = $item;
        }
        
        $this->assertEquals($items, $iteratedItems);
    }
    
    public function testCountable(): void
    {
        $items = array_fill(0, 7, ['item']);
        $pagination = new PaginationResult($items, 100, 1, 10);
        
        $this->assertCount(7, $pagination);
        $this->assertEquals(7, count($pagination));
    }
    
    public function testArrayAccess(): void
    {
        $items = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
            ['id' => 3, 'name' => 'Third'],
        ];
        
        $pagination = new PaginationResult($items, 3, 1, 10);
        
        // Test offsetExists
        $this->assertTrue(isset($pagination[0]));
        $this->assertTrue(isset($pagination[1]));
        $this->assertTrue(isset($pagination[2]));
        $this->assertFalse(isset($pagination[3]));
        
        // Test offsetGet
        $this->assertEquals(['id' => 1, 'name' => 'First'], $pagination[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Second'], $pagination[1]);
        $this->assertEquals(['id' => 3, 'name' => 'Third'], $pagination[2]);
        $this->assertNull($pagination[3]);
    }
    
    public function testArrayAccessReadOnly(): void
    {
        $pagination = new PaginationResult([['id' => 1]], 1, 1, 10);
        
        // Test offsetSet throws exception
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('PaginationResult is read-only');
        $pagination[0] = ['id' => 2];
    }
    
    public function testArrayAccessUnsetReadOnly(): void
    {
        $pagination = new PaginationResult([['id' => 1]], 1, 1, 10);
        
        // Test offsetUnset throws exception
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('PaginationResult is read-only');
        unset($pagination[0]);
    }
    
    public function testHelperMethods(): void
    {
        $items = array_fill(0, 5, ['item']);
        $pagination = new PaginationResult($items, 50, 3, 10);
        
        // Test items()
        $this->assertEquals($items, $pagination->items());
        
        // Test total()
        $this->assertEquals(50, $pagination->total());
        
        // Test currentPage()
        $this->assertEquals(3, $pagination->currentPage());
        
        // Test lastPage()
        $this->assertEquals(5, $pagination->lastPage());
        
        // Test perPage()
        $this->assertEquals(10, $pagination->perPage());
        
        // Test hasMorePages()
        $this->assertTrue($pagination->hasMorePages());
        
        // Test hasPreviousPages()
        $this->assertTrue($pagination->hasPreviousPages());
    }
    
    public function testToArray(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $pagination = new PaginationResult($items, 20, 2, 10);
        
        $array = $pagination->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('page', $array);
        $this->assertArrayHasKey('per_page', $array);
        $this->assertArrayHasKey('last_page', $array);
        $this->assertArrayHasKey('from', $array);
        $this->assertArrayHasKey('to', $array);
        $this->assertArrayHasKey('has_more', $array);
        $this->assertArrayHasKey('has_previous', $array);
        $this->assertArrayHasKey('next_page', $array);
        $this->assertArrayHasKey('previous_page', $array);
        
        $this->assertEquals($items, $array['items']);
        $this->assertEquals(20, $array['total']);
        $this->assertEquals(2, $array['page']);
    }
    
    public function testMeta(): void
    {
        $items = [['id' => 1]];
        $pagination = new PaginationResult($items, 10, 1, 5);
        
        $meta = $pagination->meta();
        
        $this->assertIsArray($meta);
        $this->assertArrayNotHasKey('items', $meta);
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('page', $meta);
        $this->assertEquals(10, $meta['total']);
        $this->assertEquals(1, $meta['page']);
    }
    
    public function testMap(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];
        
        $pagination = new PaginationResult($items, 2, 1, 10);
        
        $mapped = $pagination->map(function($item) {
            return $item['id'];
        });
        
        $this->assertInstanceOf(PaginationResult::class, $mapped);
        $this->assertEquals([1, 2], $mapped->items);
        $this->assertEquals(2, $mapped->total);
    }
    
    public function testFilter(): void
    {
        $items = [
            ['id' => 1, 'active' => true],
            ['id' => 2, 'active' => false],
            ['id' => 3, 'active' => true],
        ];
        
        $pagination = new PaginationResult($items, 3, 1, 10);
        
        $filtered = $pagination->filter(function($item) {
            return $item['active'] === true;
        });
        
        $this->assertIsArray($filtered);
        $this->assertCount(2, $filtered);
    }
    
    public function testLinks(): void
    {
        $pagination = new PaginationResult([], 100, 5, 10);
        
        $links = $pagination->links('/users', ['sort' => 'name']);
        
        $this->assertIsArray($links);
        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('previous', $links);
        $this->assertArrayHasKey('next', $links);
        $this->assertArrayHasKey('last', $links);
        
        $this->assertEquals('/users?sort=name&page=1', $links['first']);
        $this->assertEquals('/users?sort=name&page=4', $links['previous']);
        $this->assertEquals('/users?sort=name&page=6', $links['next']);
        $this->assertEquals('/users?sort=name&page=10', $links['last']);
    }
    
    public function testLinksOnFirstPage(): void
    {
        $pagination = new PaginationResult([], 50, 1, 10);
        
        $links = $pagination->links('/users');
        
        $this->assertEquals('/users?page=1', $links['first']);
        $this->assertNull($links['previous']);
        $this->assertEquals('/users?page=2', $links['next']);
        $this->assertEquals('/users?page=5', $links['last']);
    }
    
    public function testLinksOnLastPage(): void
    {
        $pagination = new PaginationResult([], 50, 5, 10);
        
        $links = $pagination->links('/users');
        
        $this->assertEquals('/users?page=1', $links['first']);
        $this->assertEquals('/users?page=4', $links['previous']);
        $this->assertNull($links['next']);
        $this->assertEquals('/users?page=5', $links['last']);
    }
    
    public function testToString(): void
    {
        $emptyPagination = new PaginationResult([], 0, 1, 10);
        $nonEmptyPagination = new PaginationResult([['id' => 1]], 1, 1, 10);
        
        $this->assertEquals('', (string) $emptyPagination);
        $this->assertEquals('non-empty', (string) $nonEmptyPagination);
    }
    
    public function testPageBoundaries(): void
    {
        // Test page less than 1
        $pagination = new PaginationResult([], 10, 0, 10);
        $this->assertEquals(1, $pagination->page);
        
        // Test negative page
        $pagination = new PaginationResult([], 10, -5, 10);
        $this->assertEquals(1, $pagination->page);
        
        // Test per page less than 1
        $pagination = new PaginationResult([], 10, 1, 0);
        $this->assertEquals(1, $pagination->perPage);
        
        // Test negative per page
        $pagination = new PaginationResult([], 10, 1, -10);
        $this->assertEquals(1, $pagination->perPage);
    }
}