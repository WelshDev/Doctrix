<?php

/**
 * Persistent Filters Example
 * 
 * This example demonstrates how to use PersistentFiltersTrait to apply filters
 * that remain active across multiple query operations, especially useful with
 * pagination where count() and fetch() are called separately.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\ORM\QueryBuilder;
use WelshDev\Doctrix\BaseRepository;

// Example entities
class User
{
    private int $id;
    private string $name;
    private string $email;
    private string $status;
    private ?Department $department;
    
    // Getters/setters...
}

class Department
{
    private int $id;
    private string $name;
    private bool $active;
    
    // Getters/setters...
}

class Email
{
    private int $id;
    private User $user;
    private string $subject;
    private string $status;
    private \DateTime $sentAt;
    
    // Getters/setters...
}

/**
 * Example 1: Basic Persistent Filter
 */
class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    /**
     * Filter by active status
     */
    public function filterActive(): self
    {
        return $this->withFilter('active', true);
    }
    
    /**
     * Filter by department
     */
    public function filterByDepartment(Department $department): self
    {
        return $this->withFilter('department', $department);
    }
    
    /**
     * Apply active filter
     */
    protected function applyActiveFilter(QueryBuilder $qb, bool $active): void
    {
        $status = $active ? 'active' : 'inactive';
        $qb->andWhere($this->alias . '.status = :status')
           ->setParameter('status', $status);
    }
    
    /**
     * Apply department filter
     */
    protected function applyDepartmentFilter(QueryBuilder $qb, Department $department): void
    {
        $qb->andWhere($this->alias . '.department = :department')
           ->setParameter('department', $department);
    }
}

/**
 * Example 2: Complex Filter with Multiple Conditions
 */
class EmailRepository extends BaseRepository
{
    protected string $alias = 'e';
    
    /**
     * Filter emails by user
     */
    public function filterByUser(User $user): self
    {
        return $this->withFilter('user', $user);
    }
    
    /**
     * Filter emails by date range
     */
    public function filterByDateRange(\DateTime $start, \DateTime $end): self
    {
        return $this->withFilter('dateRange', ['start' => $start, 'end' => $end]);
    }
    
    /**
     * Filter emails by status
     */
    public function filterByStatus(string $status): self
    {
        return $this->withFilter('status', $status);
    }
    
    /**
     * Apply user filter
     */
    protected function applyUserFilter(QueryBuilder $qb, User $user): void
    {
        $qb->andWhere($this->alias . '.user = :user')
           ->setParameter('user', $user);
    }
    
    /**
     * Apply date range filter
     */
    protected function applyDateRangeFilter(QueryBuilder $qb, array $range): void
    {
        $qb->andWhere($this->alias . '.sentAt BETWEEN :start AND :end')
           ->setParameter('start', $range['start'])
           ->setParameter('end', $range['end']);
    }
    
    /**
     * Apply status filter
     */
    protected function applyStatusFilter(QueryBuilder $qb, string $status): void
    {
        $qb->andWhere($this->alias . '.status = :status')
           ->setParameter('status', $status);
    }
}

/**
 * Example 3: Dynamic Filter Building
 */
class ProductRepository extends BaseRepository
{
    protected string $alias = 'p';
    
    /**
     * Apply search filters from array
     */
    public function search(array $filters): self
    {
        $repo = $this;
        
        // Apply each filter conditionally
        if (isset($filters['category'])) {
            $repo = $repo->withFilter('category', $filters['category']);
        }
        
        if (isset($filters['minPrice']) && isset($filters['maxPrice'])) {
            $repo = $repo->withFilter('priceRange', [
                'min' => $filters['minPrice'],
                'max' => $filters['maxPrice']
            ]);
        }
        
        if (isset($filters['inStock'])) {
            $repo = $repo->withFilter('inStock', $filters['inStock']);
        }
        
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            $repo = $repo->withFilter('tags', $filters['tags']);
        }
        
        return $repo;
    }
    
    /**
     * Apply category filter
     */
    protected function applyCategoryFilter(QueryBuilder $qb, string $category): void
    {
        $qb->andWhere($this->alias . '.category = :category')
           ->setParameter('category', $category);
    }
    
    /**
     * Apply price range filter
     */
    protected function applyPriceRangeFilter(QueryBuilder $qb, array $range): void
    {
        $qb->andWhere($this->alias . '.price BETWEEN :minPrice AND :maxPrice')
           ->setParameter('minPrice', $range['min'])
           ->setParameter('maxPrice', $range['max']);
    }
    
    /**
     * Apply stock filter
     */
    protected function applyInStockFilter(QueryBuilder $qb, bool $inStock): void
    {
        if ($inStock) {
            $qb->andWhere($this->alias . '.stock > 0');
        } else {
            $qb->andWhere($this->alias . '.stock = 0');
        }
    }
    
    /**
     * Apply tags filter
     */
    protected function applyTagsFilter(QueryBuilder $qb, array $tags): void
    {
        $qb->join($this->alias . '.tags', 't')
           ->andWhere('t.name IN (:tags)')
           ->setParameter('tags', $tags);
    }
}

// Usage Examples
function demonstratePersistentFilters($container)
{
    $em = $container->get('doctrine.orm.entity_manager');
    
    // Example 1: Basic filtering with pagination
    echo "=== Basic Filtering ===\n";
    $userRepo = $em->getRepository(User::class);
    
    // Filter persists across pagination
    $activeUsers = $userRepo
        ->filterActive()
        ->paginate(page: 1, perPage: 20);
    
    echo "Total active users: " . $activeUsers->total() . "\n";
    echo "Users on page 1: " . count($activeUsers->items) . "\n";
    
    // Example 2: Chaining multiple filters
    echo "\n=== Chaining Filters ===\n";
    $department = $em->find(Department::class, 1);
    
    $filteredUsers = $userRepo
        ->filterActive()
        ->filterByDepartment($department)
        ->fetch();
    
    echo "Active users in department: " . count($filteredUsers) . "\n";
    
    // Example 3: Complex email filtering
    echo "\n=== Complex Email Filtering ===\n";
    $emailRepo = $em->getRepository(Email::class);
    $currentUser = $em->find(User::class, 1);
    
    $userEmails = $emailRepo
        ->filterByUser($currentUser)
        ->filterByStatus('sent')
        ->filterByDateRange(
            new \DateTime('-30 days'),
            new \DateTime('now')
        )
        ->paginate(page: 1, perPage: 10);
    
    echo "User's sent emails (last 30 days): " . $userEmails->total() . "\n";
    
    // Example 4: Dynamic filter building
    echo "\n=== Dynamic Filter Building ===\n";
    $productRepo = $em->getRepository(Product::class);
    
    // Simulate search filters from request
    $searchFilters = [
        'category' => 'electronics',
        'minPrice' => 100,
        'maxPrice' => 500,
        'inStock' => true,
        'tags' => ['wireless', 'bluetooth']
    ];
    
    $searchResults = $productRepo
        ->search($searchFilters)
        ->paginate(page: 1, perPage: 20);
    
    echo "Search results: " . $searchResults->total() . " products found\n";
    
    // Example 5: Filter management
    echo "\n=== Filter Management ===\n";
    
    // Check if filter is active
    $filtered = $userRepo->filterActive();
    if ($filtered->hasFilter('active')) {
        echo "Active filter is applied\n";
    }
    
    // Get filter value
    $activeValue = $filtered->getFilter('active');
    echo "Active filter value: " . ($activeValue ? 'true' : 'false') . "\n";
    
    // Remove filter
    $unfiltered = $filtered->withoutFilter('active');
    echo "Filter removed: " . ($unfiltered->hasFilter('active') ? 'No' : 'Yes') . "\n";
    
    // Clear all filters
    $cleared = $filtered
        ->filterByDepartment($department)
        ->withoutFilters();
    echo "All filters cleared: " . (empty($cleared->getFilters()) ? 'Yes' : 'No') . "\n";
    
    // Example 6: Immutability demonstration
    echo "\n=== Immutability ===\n";
    
    $original = $userRepo;
    $filtered1 = $original->filterActive();
    $filtered2 = $filtered1->filterByDepartment($department);
    
    echo "Original has filters: " . (empty($original->getFilters()) ? 'No' : 'Yes') . "\n";
    echo "Filtered1 has 'active': " . ($filtered1->hasFilter('active') ? 'Yes' : 'No') . "\n";
    echo "Filtered1 has 'department': " . ($filtered1->hasFilter('department') ? 'Yes' : 'No') . "\n";
    echo "Filtered2 has both: " . (count($filtered2->getFilters()) === 2 ? 'Yes' : 'No') . "\n";
    
    // Example 7: Using with other repository methods
    echo "\n=== Integration with Other Methods ===\n";
    
    // Works with all fetch methods
    $exists = $userRepo->filterActive()->exists();
    echo "Active users exist: " . ($exists ? 'Yes' : 'No') . "\n";
    
    $count = $userRepo->filterActive()->count();
    echo "Active user count: " . $count . "\n";
    
    $random = $userRepo->filterActive()->random(5);
    echo "Random active users: " . count($random) . "\n";
    
    // Works with chunk processing
    $processed = 0;
    $userRepo->filterActive()->chunk(100, function($users) use (&$processed) {
        $processed += count($users);
        // Process batch of users
    });
    echo "Processed active users in chunks: " . $processed . "\n";
}

// Example output formatting
function formatExample($title, $code, $output)
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n\n";
    echo "CODE:\n";
    echo "```php\n$code\n```\n\n";
    echo "OUTPUT:\n";
    echo "$output\n";
}

// Demonstrate key concepts
echo <<<'CONCEPTS'
================================================================================
                        PERSISTENT FILTERS KEY CONCEPTS
================================================================================

1. IMMUTABILITY
   - Each filter method returns a NEW repository instance
   - Original repository remains unchanged
   - Allows for branching filter logic

2. PERSISTENCE
   - Filters remain active across ALL operations
   - Especially important for paginate() which calls count() and fetch()
   - No need to reapply filters for each operation

3. CONVENTION-BASED
   - withFilter('name', $value) registers a filter
   - applyNameFilter($qb, $value) applies the filter
   - Automatic discovery based on naming convention

4. CHAINABLE
   - All filter methods return self for chaining
   - Can combine multiple filters fluently
   - Works seamlessly with other repository methods

5. MANAGEABLE
   - Check filter existence with hasFilter()
   - Get filter values with getFilter()
   - Remove specific filters with withoutFilter()
   - Clear all filters with withoutFilters()

================================================================================
CONCEPTS;