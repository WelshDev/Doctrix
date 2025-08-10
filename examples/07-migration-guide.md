# Migration Guide to Doctrix

This guide helps you migrate from other query builder libraries to Doctrix.

## Table of Contents
- [From Native Doctrine](#from-native-doctrine)
- [From KnpPaginatorBundle](#from-knppaginatorbundle)
- [From Custom Repository Methods](#from-custom-repository-methods)
- [From Query Builders](#from-query-builders)
- [Gradual Migration Strategy](#gradual-migration-strategy)

## From Native Doctrine

### Before (Native Doctrine)
```php
// Repository
class UserRepository extends ServiceEntityRepository
{
    public function findActiveUsers()
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('u.created', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    public function findByRole($role)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%'.$role.'%')
            ->getQuery()
            ->getResult();
    }
}
```

### After (Doctrix)
```php
// Repository
use WelshDev\Doctrix\BaseRepository;

class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    public function findActiveUsers()
    {
        return $this->fetch(
            ['status' => 'active'],
            ['created' => 'DESC']
        );
    }
    
    public function findByRole($role)
    {
        return $this->fetch([
            ['roles', 'contains', $role]
        ]);
    }
}
```

### Alternative (Using Fluent Interface)
```php
class UserRepository extends BaseRepository
{
    public function findActiveUsers()
    {
        return $this->query()
            ->where('status', 'active')
            ->orderBy('created', 'DESC')
            ->get();
    }
    
    public function findByRole($role)
    {
        return $this->query()
            ->whereContains('roles', $role)
            ->get();
    }
}
```

## From KnpPaginatorBundle

### Before (KnpPaginatorBundle)
```php
// Controller
use Knp\Component\Pager\PaginatorInterface;

class UserController extends AbstractController
{
    public function index(Request $request, PaginatorInterface $paginator)
    {
        $query = $this->userRepository
            ->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('u.created', 'DESC');
        
        $users = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );
        
        return $this->render('users/index.html.twig', [
            'users' => $users
        ]);
    }
}

// Template
{% for user in users %}
    {{ user.name }}
{% endfor %}

{{ knp_pagination_render(users) }}
```

### After (Doctrix)
```php
// Controller
class UserController extends AbstractController
{
    public function index(Request $request)
    {
        $users = $this->userRepository->paginate(
            criteria: ['status' => 'active'],
            page: $request->query->getInt('page', 1),
            perPage: 20,
            orderBy: ['created' => 'DESC']
        );
        
        return $this->render('users/index.html.twig', [
            'users' => $users
        ]);
    }
}

// Template (Doctrix PaginationResult is iterable)
{% for user in users %}
    {{ user.name }}
{% endfor %}

{% include 'pagination.html.twig' with {'pagination': users} %}
```

### Pagination Template
```twig
{# templates/pagination.html.twig #}
{% if pagination.lastPage > 1 %}
    <nav>
        <ul class="pagination">
            {% if not pagination.onFirstPage() %}
                <li><a href="?page={{ pagination.previousPage }}">Previous</a></li>
            {% endif %}
            
            {% for page in 1..pagination.lastPage %}
                <li class="{{ page == pagination.page ? 'active' : '' }}">
                    <a href="?page={{ page }}">{{ page }}</a>
                </li>
            {% endfor %}
            
            {% if not pagination.onLastPage() %}
                <li><a href="?page={{ pagination.nextPage }}">Next</a></li>
            {% endif %}
        </ul>
    </nav>
    
    <p>Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results</p>
{% endif %}
```

## From Custom Repository Methods

### Before (Custom Methods)
```php
class ProductRepository extends ServiceEntityRepository
{
    public function searchProducts($term, $category = null, $minPrice = null, $maxPrice = null)
    {
        $qb = $this->createQueryBuilder('p');
        
        if ($term) {
            $qb->andWhere('p.name LIKE :term OR p.description LIKE :term')
               ->setParameter('term', '%'.$term.'%');
        }
        
        if ($category) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }
        
        if ($minPrice) {
            $qb->andWhere('p.price >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }
        
        if ($maxPrice) {
            $qb->andWhere('p.price <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }
        
        return $qb->orderBy('p.created', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}
```

### After (Doctrix)
```php
use WelshDev\Doctrix\BaseRepository;

class ProductRepository extends BaseRepository
{
    protected string $alias = 'p';
    
    public function searchProducts($term, $category = null, $minPrice = null, $maxPrice = null)
    {
        $query = $this->query();
        
        if ($term) {
            $query->where(function($q) use ($term) {
                $q->whereContains('name', $term)
                  ->orWhereContains('description', $term);
            });
        }
        
        if ($category) {
            $query->where('category', $category);
        }
        
        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }
        
        return $query->orderBy('created', 'DESC')->get();
    }
}
```

### Even Better (Using Dynamic Building)
```php
class ProductRepository extends BaseRepository
{
    public function searchProducts(array $filters)
    {
        $criteria = [];
        
        if (!empty($filters['category'])) {
            $criteria['category'] = $filters['category'];
        }
        
        if (!empty($filters['minPrice'])) {
            $criteria[] = ['price', '>=', $filters['minPrice']];
        }
        
        if (!empty($filters['maxPrice'])) {
            $criteria[] = ['price', '<=', $filters['maxPrice']];
        }
        
        $results = $this->fetch($criteria, ['created' => 'DESC']);
        
        // Apply text search if provided
        if (!empty($filters['term'])) {
            return array_filter($results, function($product) use ($filters) {
                return stripos($product->getName(), $filters['term']) !== false
                    || stripos($product->getDescription(), $filters['term']) !== false;
            });
        }
        
        return $results;
    }
}
```

## From Query Builders

### Before (Custom Query Builder)
```php
class QueryBuilder
{
    private $qb;
    
    public function __construct($repository)
    {
        $this->qb = $repository->createQueryBuilder('e');
    }
    
    public function whereStatus($status)
    {
        $this->qb->andWhere('e.status = :status')
                 ->setParameter('status', $status);
        return $this;
    }
    
    public function whereDateBetween($field, $start, $end)
    {
        $this->qb->andWhere("e.$field BETWEEN :start AND :end")
                 ->setParameter('start', $start)
                 ->setParameter('end', $end);
        return $this;
    }
    
    public function get()
    {
        return $this->qb->getQuery()->getResult();
    }
}
```

### After (Doctrix Fluent Interface)
```php
// No need for custom query builder - Doctrix provides it!
$results = $repository->query()
    ->where('status', $status)
    ->whereBetween($field, $start, $end)
    ->get();
```

## Gradual Migration Strategy

### Step 1: Install Doctrix Alongside Existing Code
```bash
composer require welshdev/doctrix
```

### Step 2: Migrate One Repository at a Time
```php
// Start with a simple repository
use WelshDev\Doctrix\BaseRepository;

class CategoryRepository extends BaseRepository
{
    protected string $alias = 'c';
    
    // Keep existing methods for backward compatibility
    public function findActive()
    {
        // Old way (keep temporarily)
        // return $this->findBy(['active' => true]);
        
        // New way
        return $this->fetch(['active' => true]);
    }
}
```

### Step 3: Use Service Pattern for Legacy Repositories
```php
use WelshDev\Doctrix\Service\QueryBuilderService;

class LegacyService
{
    public function __construct(
        private QueryBuilderService $queryBuilder,
        private LegacyRepository $legacyRepo
    ) {}
    
    public function enhancedSearch($criteria)
    {
        // Enhance legacy repository without modifying it
        $enhanced = $this->queryBuilder->enhance($this->legacyRepo);
        
        return $enhanced->fetch($criteria);
    }
}
```

### Step 4: Update Controllers Gradually
```php
class ProductController
{
    // Old method (deprecate but keep)
    public function oldIndex(PaginatorInterface $paginator, Request $request)
    {
        // ... existing code
    }
    
    // New method
    public function index(Request $request)
    {
        $products = $this->productRepository->paginate(
            criteria: ['active' => true],
            page: $request->query->getInt('page', 1),
            perPage: 20
        );
        
        return $this->render('products/index.html.twig', [
            'products' => $products
        ]);
    }
}
```

### Step 5: Update Templates
```twig
{# Works with both old and new pagination #}
{% for product in products %}
    {{ product.name }}
{% endfor %}

{# Conditionally render pagination #}
{% if products.lastPage is defined %}
    {# Doctrix pagination #}
    {% include 'doctrix_pagination.html.twig' with {'pagination': products} %}
{% else %}
    {# Old pagination #}
    {{ knp_pagination_render(products) }}
{% endif %}
```

## Common Migration Patterns

### Pattern 1: Complex Joins
```php
// Before
$qb = $this->createQueryBuilder('o')
    ->leftJoin('o.customer', 'c')
    ->leftJoin('o.items', 'i')
    ->leftJoin('i.product', 'p')
    ->where('c.verified = true')
    ->andWhere('p.stock > 0');

// After (with configured joins)
class OrderRepository extends BaseRepository
{
    protected array $joins = [
        ['leftJoin', 'o.customer', 'c'],
        ['leftJoin', 'o.items', 'i'],
        ['leftJoin', 'i.product', 'p'],
    ];
    
    public function findVerifiedOrders()
    {
        return $this->fetch([
            'c.verified' => true,
            ['p.stock', '>', 0]
        ]);
    }
}
```

### Pattern 2: Dynamic Queries
```php
// Before
public function buildQuery($filters)
{
    $qb = $this->createQueryBuilder('e');
    
    foreach ($filters as $field => $value) {
        if ($value !== null) {
            $qb->andWhere("e.$field = :$field")
               ->setParameter($field, $value);
        }
    }
    
    return $qb->getQuery()->getResult();
}

// After
public function buildQuery($filters)
{
    // Remove null values
    $criteria = array_filter($filters, fn($v) => $v !== null);
    
    return $this->fetch($criteria);
}
```

### Pattern 3: Aggregations
```php
// Before
public function getStatistics()
{
    $total = $this->createQueryBuilder('e')
        ->select('COUNT(e.id)')
        ->getQuery()
        ->getSingleScalarResult();
    
    $sum = $this->createQueryBuilder('e')
        ->select('SUM(e.amount)')
        ->getQuery()
        ->getSingleScalarResult();
    
    return ['total' => $total, 'sum' => $sum];
}

// After
public function getStatistics()
{
    return [
        'total' => $this->query()->count(),
        'sum' => $this->query()->sum('amount')
    ];
}
```

## Testing Migration

### Test Both Implementations
```php
class RepositoryTest extends TestCase
{
    public function testMigration()
    {
        // Test old method
        $oldResults = $repo->oldFindActive();
        
        // Test new method
        $newResults = $repo->fetch(['active' => true]);
        
        // Ensure same results
        $this->assertEquals(count($oldResults), count($newResults));
        $this->assertEquals(
            array_map(fn($e) => $e->getId(), $oldResults),
            array_map(fn($e) => $e->getId(), $newResults)
        );
    }
}
```

## Benefits After Migration

1. **Cleaner Code**: Less boilerplate, more readable queries
2. **Better Performance**: Built-in query optimization
3. **Consistent API**: Same methods across all repositories
4. **Built-in Pagination**: No need for external pagination bundles
5. **Powerful Operators**: Rich set of query operators
6. **Flexible Architecture**: Choose inheritance or service pattern
7. **Easy Testing**: Simpler to mock and test
8. **Less Dependencies**: Reduce external bundle dependencies

## Need Help?

- Check the [examples](/) directory for more patterns
- Read the [documentation](../README.md)
- Open an [issue](https://github.com/WelshDev/Doctrix/issues) on GitHub