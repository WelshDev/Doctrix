<?php
/**
 * Fluent Interface Examples for Doctrix
 * 
 * This file demonstrates the modern, chainable query API
 */

use WelshDev\Doctrix\BaseRepository;

class ProductRepository extends BaseRepository
{
    protected string $alias = 'p';
}

// Example 1: Basic Fluent Queries
// ================================

class BasicFluentExamples
{
    private ProductRepository $productRepo;
    
    public function basicFluent(): void
    {
        // Simple where clause
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->get();
        
        // Multiple where clauses (AND)
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->where('featured', true)
            ->where('stock', '>', 0)
            ->get();
        
        // With ordering
        $products = $this->productRepo->query()
            ->where('category', 'electronics')
            ->orderBy('price', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();
        
        // With limit and offset
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->orderBy('created', 'DESC')
            ->limit(10)
            ->offset(20)
            ->get();
        
        // Get single result
        $product = $this->productRepo->query()
            ->where('sku', 'PROD-123')
            ->first();
        
        // Count results
        $count = $this->productRepo->query()
            ->where('category', 'electronics')
            ->where('status', 'active')
            ->count();
    }
}

// Example 2: Advanced Where Clauses
// ==================================

class AdvancedWhereExamples
{
    private ProductRepository $productRepo;
    
    public function advancedWhere(): void
    {
        // Where with operators
        $products = $this->productRepo->query()
            ->where('price', '>=', 100)
            ->where('price', '<=', 500)
            ->where('discount', '!=', 0)
            ->get();
        
        // OR conditions
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->orWhere('featured', true)
            ->orWhere('hot_deal', true)
            ->get();
        // Finds: status='active' OR featured=true OR hot_deal=true
        
        // Grouped OR conditions
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->where(function($q) {
                $q->where('category', 'electronics')
                  ->orWhere('category', 'computers');
            })
            ->get();
        // Finds: status='active' AND (category='electronics' OR category='computers')
        
        // Complex nested conditions
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('featured', true)
                       ->where('stock', '>', 10);
                })
                ->orWhere(function($q2) {
                    $q2->where('hot_deal', true)
                       ->where('discount', '>', 20);
                });
            })
            ->get();
        // Finds: status='active' AND ((featured=true AND stock>10) OR (hot_deal=true AND discount>20))
    }
}

// Example 3: Special Where Methods
// =================================

class SpecialWhereExamples
{
    private ProductRepository $productRepo;
    
    public function specialWhere(): void
    {
        // Where IN
        $products = $this->productRepo->query()
            ->whereIn('category', ['electronics', 'computers', 'phones'])
            ->get();
        
        // Where NOT IN
        $products = $this->productRepo->query()
            ->whereNotIn('status', ['discontinued', 'deleted'])
            ->get();
        
        // Where NULL
        $products = $this->productRepo->query()
            ->whereNull('deletedAt')
            ->get();
        
        // Where NOT NULL
        $products = $this->productRepo->query()
            ->whereNotNull('publishedAt')
            ->get();
        
        // Where BETWEEN
        $products = $this->productRepo->query()
            ->whereBetween('price', 100, 500)
            ->get();
        
        // Where date between
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-12-31');
        $products = $this->productRepo->query()
            ->whereBetween('created', $startDate, $endDate)
            ->get();
        
        // Where LIKE
        $products = $this->productRepo->query()
            ->whereLike('name', '%laptop%')
            ->get();
        
        // Where contains (automatically adds % wildcards)
        $products = $this->productRepo->query()
            ->whereContains('description', 'wireless')
            ->get();
        
        // Where starts with (using whereLike)
        $products = $this->productRepo->query()
            ->whereLike('sku', 'ELEC-%')
            ->get();
        
        // Where ends with (using whereLike)
        $products = $this->productRepo->query()
            ->whereLike('model', '%-PRO')
            ->get();
    }
}

// Example 4: Aggregation Functions
// =================================

class AggregationExamples
{
    private ProductRepository $productRepo;
    
    public function aggregations(): void
    {
        // Count
        $totalProducts = $this->productRepo->query()
            ->where('status', 'active')
            ->count();
        
        // Sum
        $totalValue = $this->productRepo->query()
            ->where('status', 'active')
            ->sum('price');
        
        // Average
        $avgPrice = $this->productRepo->query()
            ->where('category', 'electronics')
            ->avg('price');
        
        // Maximum
        $maxPrice = $this->productRepo->query()
            ->where('status', 'active')
            ->max('price');
        
        // Minimum
        $minPrice = $this->productRepo->query()
            ->where('stock', '>', 0)
            ->min('price');
        
        // Check if any exist
        $hasProducts = $this->productRepo->query()
            ->where('featured', true)
            ->exists();
        
        // Check if results exist (using count)
        $hasActiveProducts = $this->productRepo->query()
            ->where('status', 'active')
            ->count() > 0;
    }
}

// Example 5: Query Modifiers
// ===========================

class QueryModifierExamples
{
    private ProductRepository $productRepo;
    
    public function modifiers(): void
    {
        // Using limit and offset
        $products = $this->productRepo->query()
            ->where('status', 'active')
            ->limit(10)
            ->offset(20)
            ->get();
        
        // With ordering
        $products = $this->productRepo->query()
            ->where('featured', true)
            ->orderBy('position', 'ASC')
            ->orderBy('created', 'DESC')
            ->limit(5)
            ->get();
        
        // For custom selections, group by, etc., get the QueryBuilder
        $qb = $this->productRepo->query()
            ->where('status', 'active')
            ->getQueryBuilder();
        
        // Now use Doctrine's native methods
        $qb->select('p.category, COUNT(p.id) as total')
           ->groupBy('p.category')
           ->having('COUNT(p.id) > :min')
           ->setParameter('min', 5);
        
        $results = $qb->getQuery()->getResult();
    }
}

// Example 6: Chaining and Conditional Queries
// ============================================

class ChainingExamples
{
    private ProductRepository $productRepo;
    
    public function dynamicQueries(array $filters): void
    {
        // Build query dynamically based on filters
        $query = $this->productRepo->query();
        
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        
        if (!empty($filters['search'])) {
            $query->whereContains('name', $filters['search']);
        }
        
        if (!empty($filters['in_stock'])) {
            $query->where('stock', '>', 0);
        }
        
        // Conditional ordering
        $sortField = $filters['sort'] ?? 'created';
        $sortDirection = $filters['direction'] ?? 'DESC';
        $query->orderBy($sortField, $sortDirection);
        
        // Execute the query
        $products = $query->get();
        
        // Alternative: Build conditionally without when()
        $query = $this->productRepo->query();
        
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (!empty($filters['featured'])) {
            $query->where('featured', true);
        }
        
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->whereContains('name', $filters['search'])
                  ->orWhere(function($q2) use ($filters) {
                      $q2->whereContains('description', $filters['search']);
                  });
            });
        }
        
        $products = $query->get();
    }
}

// Example 7: Raw Expressions and DQL
// ===================================

class RawQueryExamples
{
    private ProductRepository $productRepo;
    
    public function rawQueries(): void
    {
        // Get the underlying Doctrine QueryBuilder
        $qb = $this->productRepo->query()
            ->where('status', 'active')
            ->getQueryBuilder();
        
        // Add raw DQL
        $qb->andWhere('p.price * p.discount / 100 > :min_discount')
           ->setParameter('min_discount', 10);
        
        // Execute
        $products = $qb->getQuery()->getResult();
        
        // Working with raw DQL through QueryBuilder
        $qb = $this->productRepo->query()
            ->where('status', 'active')
            ->getQueryBuilder();
        
        // Add complex raw conditions
        $qb->andWhere('p.views / p.clicks > :ratio')
           ->setParameter('ratio', 0.1);
        
        $products = $qb->getQuery()->getResult();
        
        // Another example with date functions
        $qb = $this->productRepo->query()
            ->where('featured', true)
            ->getQueryBuilder();
        
        $qb->andWhere('DATEDIFF(CURRENT_DATE(), p.created) < :days')
           ->setParameter('days', 30);
        
        $products = $qb->getQuery()->getResult();
    }
}

// Example 8: Query Debugging
// ===========================

class DebuggingExamples
{
    private ProductRepository $productRepo;
    
    public function debugQueries(): void
    {
        $query = $this->productRepo->query()
            ->where('status', 'active')
            ->where('price', '>', 100)
            ->whereIn('category', ['electronics', 'computers'])
            ->orderBy('created', 'DESC');
        
        // Get the SQL query
        $sql = $query->toSql();
        echo "SQL: " . $sql . "\n";
        
        // Get the query parameters
        $params = $query->getParameters();
        print_r($params);
        
        // Get the Doctrine QueryBuilder for inspection
        $qb = $query->getQueryBuilder();
        echo "DQL: " . $qb->getDQL() . "\n";
        
        // Debug and execute
        $products = $query->get();
        echo "Found " . count($products) . " products\n";
    }
}