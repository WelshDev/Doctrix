# Doctrix

A powerful, flexible query builder library for Doctrine ORM that provides an enhanced array-based criteria system and modern fluent interfaces for building complex queries.

## Features

### Core Query Features
- ✅ **Hybrid Approach** - Use via inheritance OR as a service
- ✅ **Clean API** - Simple `fetch()` and `fetchOne()` methods
- ✅ **Fluent Interface** - Modern, chainable API for building queries
- ✅ **Advanced Operators** - Support for `gte`, `lte`, `like`, `contains`, `between`, etc.
- ✅ **Automatic Joins** - Detects and applies joins from dot notation
- ✅ **Filter Functions** - Reusable, named filters
- ✅ **Global Scopes** - Automatically applied filters (like soft deletes)

### Data Operations
- ✅ **Pagination** - Built-in pagination with metadata and cursor support
- ✅ **Bulk Operations** - Efficient `bulkUpdate()` and `bulkDelete()` without fetching entities
- ✅ **Aggregations** - `count()`, `sum()`, `avg()`, `max()`, `min()`
- ✅ **Query Caching** - Built-in result caching support

### Error Handling & Recovery
- ✅ **Fetch or Fail** - `fetchOneOrFail()` with configurable exceptions (throw 404s directly)
- ✅ **Fetch or Create** - `fetchOneOrCreate()` and `updateOrCreate()` patterns
- ✅ **Sole Results** - `sole()` ensures exactly one result

### Memory-Efficient Processing
- ✅ **Chunk Processing** - Process large datasets with `chunk()` and `each()`
- ✅ **Lazy Loading** - Generator-based iteration with `lazy()`
- ✅ **Batch Processing** - Transaction-wrapped batches with `batchProcess()`
- ✅ **Data Transformation** - Transform entities with `map()`

### Existence & Counting
- ✅ **Existence Checks** - `exists()`, `doesntExist()`, `isEmpty()`
- ✅ **Count Checks** - `hasExactly()`, `hasAtLeast()`, `hasAtMost()`, `hasBetween()`
- ✅ **Optimized Counting** - Check existence without fetching entities

### Random Selection
- ✅ **Random Entities** - `random()` and `randomWhere()` with database-specific optimization
- ✅ **Weighted Random** - `weightedRandom()` for biased selection
- ✅ **Random Distinct** - `randomDistinct()` for unique values

### Relationship Management
- ✅ **Relationship Checks** - `has()`, `doesntHave()`, `hasCount()`
- ✅ **Complex Conditions** - `whereHas()`, `whereRelation()` with nested criteria
- ✅ **Eager Loading** - `withRelations()` to prevent N+1 queries
- ✅ **Relationship Counts** - `withCount()` for efficient counting

### Data Validation & Integrity
- ✅ **Uniqueness Checks** - `isUnique()`, `ensureUnique()`, `isUniqueCombination()`
- ✅ **Duplicate Detection** - `fetchDuplicates()` finds duplicate entries
- ✅ **Duplicate Removal** - `removeDuplicates()` with keep strategies
- ✅ **Entity Validation** - `validate()` with rule-based validation

### Advanced Features
- ✅ **Persistent Filters** - Filters that survive across paginate() and other operations
- ✅ **Request Queries** - Build secure queries from HTTP requests with validation
- ✅ **Macros** - Register reusable custom query methods
- ✅ **Query Debugging** - See SQL, parameters, execution plan, and timing
- ✅ **Works with ANY Repository** - Enhance existing/legacy repositories

## Installation

Install via Composer:

```bash
composer require welshdev/doctrix
```

## Quick Start

### Option 1: Inheritance (Simple & Clean)

```php
use WelshDev\Doctrix\BaseRepository;

class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
}

// Usage
$users = $userRepo->fetch(['status' => 'active']);
$paginated = $userRepo->paginate(['role' => 'admin'], 1, 20);
```

### Option 2: Service Approach (Flexible & Testable)

```php
use WelshDev\Doctrix\Service\QueryBuilderService;

class MyController
{
    public function index(QueryBuilderService $queryBuilder)
    {
        // Enhance any repository
        $enhanced = $queryBuilder->for(User::class);

        // Same API as inheritance approach!
        $users = $enhanced->fetch(['status' => 'active']);
        $paginated = $enhanced->paginate(['role' => 'admin'], 1, 20);
    }
}
```

## Basic Usage

### Array-Based Criteria

```php
// Simple criteria
$users = $repo->fetch(['status' => 'active']);

// Complex criteria with operators
$projects = $repo->fetch([
    'status' => 'active',
    ['priority', 'gte', 5],
    ['deadline', 'between', new DateTime('now'), new DateTime('+30 days')],
    ['or', [
        'urgent' => true,
        'priority' => 10
    ]]
]);

// With ordering and limits
$results = $repo->fetch(
    ['category' => 'important'],
    ['created' => 'DESC'],
    10,  // limit
    0    // offset
);
```

### Fluent Interface

```php
$users = $repo->query()
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->whereIn('role', ['admin', 'moderator'])
    ->orderBy('created', 'DESC')
    ->paginate(1, 20);
```

### Pagination

```php
// Full pagination with metadata
$result = $repo->paginate(
    criteria: ['status' => 'active'],
    page: 1,
    perPage: 20
);

echo $result->total;      // Total items
echo $result->lastPage;   // Total pages
echo $result->hasMore;    // Has next page?

// Simple pagination (for infinite scroll)
$simple = $repo->simplePaginate(['status' => 'active'], 1, 20);
// Returns: ['items' => [...], 'hasMore' => true/false]
```

## Available Operators

### Comparison
- `=`, `eq` - Equals
- `!=`, `neq` - Not equals
- `<`, `lt` - Less than
- `<=`, `lte` - Less than or equal
- `>`, `gt` - Greater than
- `>=`, `gte` - Greater than or equal

### Text
- `like` - SQL LIKE
- `not_like` - SQL NOT LIKE
- `contains` - Contains substring
- `starts_with` - Starts with string
- `ends_with` - Ends with string

### Null Checks
- `is_null` - Check for NULL
- `is_not_null` - Check for NOT NULL

### Collections
- `in` - IN clause
- `not_in` - NOT IN clause
- `between` - BETWEEN clause
- `not_between` - NOT BETWEEN clause

### Logical
- `or` - OR conditions
- `and` - AND conditions (default)
- `not` - NOT condition

## Advanced Features

### Named Filters (One-Time Application)

Named filters are pre-defined, reusable query modifications that are applied once per query:

```php
class UserRepository extends BaseRepository
{
    protected function defineFilters(): array
    {
        return [
            'active' => fn($qb) => $qb->andWhere('u.status = :status')
                ->setParameter('status', 'active'),
            'verified' => fn($qb) => $qb->andWhere('u.emailVerified = true'),
        ];
    }
}

// Usage - filters are applied once to this specific query
$users = $repo->query()
    ->applyFilter('active')
    ->applyFilter('verified')
    ->get();
```

**Note:** Named filters are cleared after each query execution. For filters that need to persist across operations like pagination (where `count()` and `fetch()` are called separately), use Persistent Filters instead (see below).

### Global Scopes

```php
class PostRepository extends BaseRepository
{
    protected function globalScopes(): array
    {
        return [
            'published' => fn($qb) => $qb->andWhere('p.published = true'),
            'not_deleted' => fn($qb) => $qb->andWhere('p.deletedAt IS NULL'),
        ];
    }
}

// Automatically excludes unpublished and deleted posts
$posts = $repo->fetch();

// Bypass specific scope
$allPosts = $repo->query()
    ->withoutGlobalScope('published')
    ->get();
```

### Query Caching

```php
$users = $repo->query()
    ->where('status', 'active')
    ->cache(3600)  // Cache for 1 hour
    ->get();
```

### Aggregations

```php
$count = $repo->query()->where('status', 'active')->count();
$sum = $repo->query()->sum('amount');
$avg = $repo->query()->avg('rating');
$max = $repo->query()->max('score');
$min = $repo->query()->min('price');
```

### Bulk Operations

```php
// Bulk update - deactivate inactive users
$affected = $repo->bulkUpdate(
    ['status' => 'inactive'],
    [['lastLogin', 'lt', new DateTime('-6 months')]]
);

// Bulk delete - remove expired sessions
$deleted = $repo->bulkDelete([
    ['expiresAt', 'lt', new DateTime()]
]);

// Conditional bulk update - only if less than 100 records
$repo->conditionalBulkUpdate(
    ['status' => 'archived'],
    ['status' => 'old'],
    fn($count) => $count < 100
);

// Safe bulk delete with dry run
$result = $repo->safeBulkDelete(['status' => 'expired'], true);
echo "Would delete {$result['count']} records\n";

// Process large dataset in batches
$total = $repo->bulkBatch(
    'update',
    ['status' => 'pending'],
    ['status' => 'processing'],
    500  // Batch size
);
```

### Persistent Filters

Persistent filters allow you to apply filters that remain active across multiple query operations, especially useful with pagination where `count()` and `fetch()` are called separately.

```php
// Define a repository with persistent filters
class EmailRepository extends BaseRepository
{
    protected string $alias = 'e';
    
    // Create a filter method that returns a cloned instance
    public function filterByUser(User $user): self
    {
        return $this->withFilter('user', $user);
    }
    
    // Define how the filter is applied to queries
    protected function applyUserFilter(QueryBuilder $qb, User $user): void
    {
        $qb->andWhere('e.user = :user')
           ->setParameter('user', $user);
    }
}

// Usage - filter persists across pagination
$emails = $repo->filterByUser($currentUser)
    ->paginate(page: 1, perPage: 20);

// Chain multiple filters
$emails = $repo
    ->withFilter('status', 'sent')
    ->withFilter('priority', 'high')
    ->fetch();

// Remove filters
$repo = $repo->withoutFilter('status');

// Check if filter is active
if ($repo->hasFilter('user')) {
    // Filter is active
}
```

#### Convention-Based Filter Application

The `PersistentFiltersTrait` uses a naming convention to automatically find and apply filter methods:

1. Call `withFilter('filterName', $value)` to register a filter
2. Define `applyFilterNameFilter(QueryBuilder $qb, $value)` to handle the filter
3. The trait automatically calls your method when building queries

```php
class ProductRepository extends BaseRepository
{
    // Register multiple filters at once
    public function applyFilters(array $filters): self
    {
        return $this->withFilters($filters);
    }
    
    // Each filter has its own apply method
    protected function applyCategoryFilter(QueryBuilder $qb, Category $category): void
    {
        $qb->andWhere('p.category = :category')
           ->setParameter('category', $category);
    }
    
    protected function applyPriceRangeFilter(QueryBuilder $qb, array $range): void
    {
        $qb->andWhere('p.price BETWEEN :min AND :max')
           ->setParameter('min', $range['min'])
           ->setParameter('max', $range['max']);
    }
}

// Usage
$products = $repo->applyFilters([
    'category' => $electronics,
    'priceRange' => ['min' => 100, 'max' => 500]
])->paginate(1, 20);
```

#### Complex Filter Example

```php
class EmailRepository extends BaseRepository
{
    public function filterByOperative(Operative $operative): self
    {
        return $this->withFilter('operative', $operative);
    }
    
    protected function applyOperativeFilter(QueryBuilder $qb, Operative $operative): void
    {
        // Complex joins for inheritance hierarchy
        $qb->leftJoin(OperativeEmail::class, 'oe', 'WITH', 'e.id = oe.id')
           ->leftJoin(ContractEmail::class, 'ce', 'WITH', 'e.id = ce.id')
           ->leftJoin('ce.contract', 'c')
           ->andWhere(
               $qb->expr()->orX(
                   'oe.operative = :operative',
                   'c.operative = :operative'
               )
           )
           ->setParameter('operative', $operative);
    }
}
```

### Macros (Custom Query Methods)

```php
// Register reusable query methods
$repo->registerMacro('activeAdmins', function($query) {
    return $query
        ->where('status', 'active')
        ->where('role', 'admin');
});

// Register multiple macros
$repo->registerMacros([
    'verified' => fn($q) => $q->where('emailVerified', true),
    'premium' => fn($q) => $q->where('subscriptionType', 'premium'),
    'recent' => fn($q) => $q->where('createdAt', '>=', new DateTime('-30 days'))
]);

// Use macros in queries
$admins = $repo->query()->activeAdmins()->get();

// Chain multiple macros
$users = $repo->query()
    ->verified()
    ->premium()
    ->recent()
    ->paginate(1, 20);

// Parameterized macros
$repo->registerMacro('olderThan', function($query, $days) {
    return $query->where('createdAt', '<', new DateTime("-{$days} days"));
});

$oldUsers = $repo->query()->olderThan(365)->get();
```

### Query Debugging

```php
// Debug query without executing
$repo->query()
    ->where('status', 'active')
    ->debug();

// Debug with execution (shows timing and memory)
$repo->query()
    ->where('status', 'active')
    ->debug('text', true);

// Output formats: 'text', 'html', 'json', 'array'
$debugInfo = $repo->query()
    ->where('role', 'admin')
    ->debug('array', true);

echo "Query took: {$debugInfo['execution_time_ms']} ms\n";
echo "Memory used: {$debugInfo['memory_used_mb']} MB\n";

// Get results with debug output
$users = $repo->query()
    ->where('verified', true)
    ->getWithDebug('text');

// Debug shows:
// ✓ SQL query with formatting
// ✓ Bound parameters
// ✓ Execution time & memory usage
// ✓ Query execution plan (MySQL/PostgreSQL/SQLite)
// ✓ Result count
```

### Request-Based Queries

```php
// Define searchable/sortable fields in repository
class UserRepository extends BaseRepository {
    protected function defineRequestSchema(): RequestQuerySchema {
        return RequestQuerySchema::preset('basic')
            ->searchable(['name', 'email', 'status', 'role'])
            ->sortable(['createdAt', 'name', 'lastLogin'])
            ->defaults(['status' => 'active'])
            ->maxLimit(100);
    }
}

// Build query from request parameters
// GET /users?status=active&role=admin&sort=-createdAt&page=1&limit=20
$users = $repo->fromRequest($request)->get();
$paginated = $repo->paginateFromRequest($request);

// Custom schema per action
$schema = RequestQuerySchema::preset('strict')
    ->searchable(['name', 'email'])
    ->field('age')->numeric(0, 120)
    ->field('role')->enum(['admin', 'user', 'guest'])
    ->require('status');

$users = $repo->fromRequest($request, $schema)->get();

// Advanced control with helper
$results = $repo->requestQuery()
    ->allowFields(['name', 'email', 'country'])
    ->withDefaults(['verified' => true])
    ->override(['deleted' => false])
    ->fromRequest($request)
    ->get();
```

## Quick Method Reference

### Fetching Data
```php
$repo->fetch($criteria, $orderBy, $limit, $offset)  // Get multiple
$repo->fetchOne($criteria)                           // Get single
$repo->fetchOneOrFail($criteria, $exception)        // Get or throw
$repo->fetchOneOrCreate($criteria, $values, $callback) // Get or create
$repo->updateOrCreate($criteria, $values)           // Update or create
$repo->sole($criteria)                              // Exactly one or throw
```

### Checking Existence
```php
$repo->exists($criteria)                            // Check if exists
$repo->doesntExist($criteria)                       // Check if not exists
$repo->hasExactly($count, $criteria)               // Exact count
$repo->hasAtLeast($min, $criteria)                 // Minimum count
$repo->hasAtMost($max, $criteria)                  // Maximum count
```

### Processing Large Datasets
```php
$repo->chunk($size, $callback)                      // Process in chunks
$repo->each($callback, $chunkSize)                 // Process individually
$repo->lazy($chunkSize)                            // Generator iteration
$repo->map($callback, $chunkSize)                  // Transform entities
```

### Working with Relationships
```php
$repo->has('orders')->fetch()                       // Has relationship
$repo->has('orders', '>', 5)->fetch()              // Count condition
$repo->doesntHave('orders')->fetch()               // Missing relationship
$repo->whereHas('orders', 'status', 'pending')     // Filter by related
$repo->withRelations(['orders', 'profile'])        // Eager load
```

### Data Validation
```php
$repo->isUnique('email', $value, $excludeId)       // Check uniqueness
$repo->ensureUnique('email', $value)               // Throw if not unique
$repo->fetchDuplicates(['email'])                  // Find duplicates
$repo->removeDuplicates(['email'], 'first')        // Remove duplicates
```

### Random Selection
```php
$repo->random($count)                               // Random entities
$repo->randomWhere($criteria, $count)              // Random with criteria
$repo->weightedRandom(['priority' => 'ASC'], 5)    // Weighted selection
```

## Using Traits

Mix and match traits for additional functionality:

```php
use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Traits\CacheableTrait;
use WelshDev\Doctrix\Traits\SoftDeleteTrait;

class ProductRepository extends BaseRepository
{
    use CacheableTrait;
    use SoftDeleteTrait;

    protected string $alias = 'p';
    protected string $softDeleteField = 'deletedAt';
}

// Automatically excludes soft-deleted records
$products = $repo->fetch();

// Include soft-deleted records
$allProducts = $repo->fetchWithDeleted();

// Only soft-deleted records
$deletedProducts = $repo->fetchOnlyDeleted();
```