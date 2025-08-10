<?php
/**
 * Service Pattern Examples for Doctrix
 * 
 * This file demonstrates using Doctrix without inheritance,
 * perfect for legacy code or third-party repositories
 */

use WelshDev\Doctrix\Service\QueryBuilderService;
use WelshDev\Doctrix\Service\EnhancedQueryBuilder;
use WelshDev\Doctrix\Pagination\PaginationResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

// Example 1: Basic Service Usage
// ===============================

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QueryBuilderService $queryBuilder
    ) {}
    
    public function findActiveUsers(): array
    {
        // Enhance any repository
        $userRepo = $this->entityManager->getRepository(User::class);
        $enhanced = $this->queryBuilder->enhance($userRepo);
        
        // Now use all Doctrix features
        return $enhanced->fetch(['status' => 'active']);
    }
    
    public function findUsersByRole(string $role): array
    {
        // Or use entity class directly
        $enhanced = $this->queryBuilder->for(User::class, 'u');
        
        return $enhanced->query()
            ->where('status', 'active')
            ->whereContains('roles', $role)
            ->orderBy('created', 'DESC')
            ->get();
    }
    
    public function paginateUsers(int $page = 1): PaginationResult
    {
        return $this->queryBuilder
            ->for(User::class)
            ->paginate(
                criteria: ['active' => true],
                page: $page,
                perPage: 20
            );
    }
}

// Example 2: Enhancing Legacy Repositories
// =========================================

// Imagine this is a third-party or legacy repository you can't modify
class LegacyProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }
    
    // Some legacy methods you want to keep
    public function findBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku]);
    }
}

class ProductService
{
    public function __construct(
        private LegacyProductRepository $legacyRepo,
        private QueryBuilderService $queryBuilder
    ) {}
    
    public function enhancedProductSearch(array $filters): array
    {
        // Enhance the legacy repository
        $enhanced = $this->queryBuilder->enhance($this->legacyRepo, 'p');
        
        // Now use modern Doctrix features on legacy repo!
        $query = $enhanced->query();
        
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        
        if (!empty($filters['search'])) {
            $query->whereContains('name', $filters['search']);
        }
        
        return $query
            ->orderBy('created', 'DESC')
            ->limit(50)
            ->get();
    }
    
    public function findFeaturedProducts(): array
    {
        // Mix legacy methods with enhanced features
        $sku = 'SPECIAL-001';
        $specialProduct = $this->legacyRepo->findBySku($sku);  // Legacy method
        
        // Enhanced query
        $enhanced = $this->queryBuilder->enhance($this->legacyRepo);
        $featured = $enhanced->fetch(
            ['featured' => true],
            ['position' => 'ASC']
        );
        
        return $featured;
    }
}

// Example 3: Dynamic Repository Enhancement
// ==========================================

class DynamicQueryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QueryBuilderService $queryBuilder
    ) {}
    
    /**
     * Generic method to query any entity
     */
    public function queryEntity(
        string $entityClass,
        array $criteria = [],
        array $orderBy = null,
        int $limit = null
    ): array {
        return $this->queryBuilder
            ->for($entityClass)
            ->fetch($criteria, $orderBy, $limit);
    }
    
    /**
     * Generic pagination for any entity
     */
    public function paginateEntity(
        string $entityClass,
        array $criteria = [],
        int $page = 1,
        int $perPage = 20
    ): PaginationResult {
        return $this->queryBuilder
            ->for($entityClass)
            ->paginate($criteria, $page, $perPage);
    }
    
    /**
     * Complex query for any entity
     */
    public function complexQuery(string $entityClass): EnhancedQueryBuilder
    {
        return $this->queryBuilder->for($entityClass);
    }
    
    // Usage examples
    public function demonstrateDynamicQueries(): void
    {
        // Query any entity dynamically
        $users = $this->queryEntity(User::class, ['status' => 'active']);
        $orders = $this->queryEntity(Order::class, ['status' => 'pending']);
        $products = $this->queryEntity(Product::class, ['featured' => true]);
        
        // Paginate any entity
        $userPage = $this->paginateEntity(User::class, [], 1, 20);
        $orderPage = $this->paginateEntity(Order::class, ['status' => 'shipped'], 2, 10);
        
        // Build complex queries
        $complexUsers = $this->complexQuery(User::class)
            ->query()
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'moderator'])
            ->whereNotNull('emailVerifiedAt')
            ->orderBy('lastLogin', 'DESC')
            ->paginate(1, 15);
    }
}

// Example 4: Service with Custom Joins
// =====================================

class OrderQueryService
{
    public function __construct(
        private QueryBuilderService $queryBuilder
    ) {}
    
    public function getOrdersWithDetails(array $filters = []): array
    {
        // Configure joins when enhancing
        $enhanced = $this->queryBuilder
            ->for(Order::class, 'o')
            ->withJoins([
                ['leftJoin', 'o.customer', 'c'],
                ['leftJoin', 'o.items', 'i'],
                ['leftJoin', 'i.product', 'p'],
                ['leftJoin', 'o.shipping', 's'],
            ]);
        
        // Now query with access to joined tables
        return $enhanced->fetch([
            'o.status' => 'completed',
            'c.verified' => true,
            ['s.deliveredAt', 'is_not_null', true]
        ]);
    }
    
    public function searchOrders(string $search): array
    {
        $enhanced = $this->queryBuilder
            ->for(Order::class, 'o')
            ->withJoins([
                ['leftJoin', 'o.customer', 'c'],
                ['leftJoin', 'o.items', 'i'],
                ['leftJoin', 'i.product', 'p'],
            ]);
        
        return $enhanced->query()
            ->where(function($q) use ($search) {
                $q->where('o.reference', 'like', "%{$search}%")
                  ->orWhere('c.name', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('p.name', 'like', "%{$search}%");
            })
            ->orderBy('o.created', 'DESC')
            ->limit(50)
            ->get();
    }
    
    public function addJoinsDynamically(): array
    {
        $enhanced = $this->queryBuilder->for(Order::class, 'o');
        
        // Add joins dynamically based on needs
        $enhanced = $enhanced->addJoin('leftJoin', 'o.customer', 'c');
        
        // You can chain multiple joins
        $enhanced = $enhanced
            ->addJoin('leftJoin', 'o.items', 'i')
            ->addJoin('leftJoin', 'i.product', 'p');
        
        return $enhanced->fetch(['o.status' => 'pending']);
    }
}

// Example 5: Cached Service Queries
// ==================================

class CachedDataService
{
    public function __construct(
        private QueryBuilderService $queryBuilder
    ) {}
    
    /**
     * Get categories with caching
     */
    public function getCategories(): array
    {
        // Use cached() method for automatic caching
        $enhanced = $this->queryBuilder->cached(Category::class, 'c');
        
        return $enhanced->query()
            ->where('active', true)
            ->whereNull('parentId')  // Top-level only
            ->orderBy('position', 'ASC')
            ->cache(3600)  // Cache for 1 hour
            ->get();
    }
    
    /**
     * Get cached settings
     */
    public function getSettings(): array
    {
        // Cached instance is reused
        $enhanced = $this->queryBuilder->cached(Setting::class, 's');
        
        return $enhanced->query()
            ->cache(86400, 'app_settings')  // Cache for 24 hours with custom key
            ->get();
    }
    
    /**
     * Clear cache when needed
     */
    public function clearCache(): void
    {
        $this->queryBuilder->clearCache();
    }
}

// Example 6: Service in Controllers
// ==================================

class ProductController
{
    public function __construct(
        private QueryBuilderService $queryBuilder
    ) {}
    
    public function index(Request $request): Response
    {
        // Get filters from request
        $filters = $request->query->all();
        
        // Build query dynamically
        $query = $this->queryBuilder->for(Product::class)->query();
        
        // Apply filters
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
            $query->where(function($q) use ($filters) {
                $q->whereContains('name', $filters['search'])
                  ->orWhereContains('description', $filters['search']);
            });
        }
        
        // Sorting
        $sort = $filters['sort'] ?? 'created';
        $direction = $filters['direction'] ?? 'DESC';
        $query->orderBy($sort, $direction);
        
        // Paginate
        $products = $query->paginate(
            page: $request->query->getInt('page', 1),
            perPage: 20
        );
        
        return $this->render('products/index.html.twig', [
            'products' => $products,
            'filters' => $filters
        ]);
    }
    
    public function show(string $slug): Response
    {
        $product = $this->queryBuilder
            ->for(Product::class)
            ->fetchOne(['slug' => $slug, 'active' => true]);
        
        if (!$product) {
            throw $this->createNotFoundException();
        }
        
        // Get related products
        $related = $this->queryBuilder
            ->for(Product::class)
            ->query()
            ->where('category', $product->getCategory())
            ->where('id', '!=', $product->getId())
            ->where('active', true)
            ->limit(4)
            ->get();
        
        return $this->render('products/show.html.twig', [
            'product' => $product,
            'related' => $related
        ]);
    }
}

// Example 7: Testing with Service Pattern
// ========================================

class ProductServiceTest extends TestCase
{
    private QueryBuilderService $queryBuilder;
    private ProductService $service;
    
    protected function setUp(): void
    {
        // Mock the QueryBuilderService for testing
        $this->queryBuilder = $this->createMock(QueryBuilderService::class);
        
        $this->service = new ProductService(
            $this->createMock(LegacyProductRepository::class),
            $this->queryBuilder
        );
    }
    
    public function testFindActiveProducts(): void
    {
        // Setup mock
        $enhancedBuilder = $this->createMock(EnhancedQueryBuilder::class);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('for')
            ->with(Product::class)
            ->willReturn($enhancedBuilder);
        
        $enhancedBuilder
            ->expects($this->once())
            ->method('fetch')
            ->with(['active' => true])
            ->willReturn([/* mock products */]);
        
        // Test
        $result = $this->service->findActiveProducts();
        
        $this->assertIsArray($result);
    }
}

// Example 8: Dependency Injection Configuration
// ==============================================

/*
# config/services.yaml

services:
    WelshDev\Doctrix\Service\QueryBuilderService:
        arguments:
            - '@doctrine.orm.entity_manager'
    
    App\Service\UserService:
        arguments:
            - '@doctrine.orm.entity_manager'
            - '@WelshDev\Doctrix\Service\QueryBuilderService'
    
    App\Service\ProductService:
        arguments:
            - '@App\Repository\LegacyProductRepository'
            - '@WelshDev\Doctrix\Service\QueryBuilderService'

    # Auto-wire the service
    WelshDev\Doctrix\Service\:
        resource: '../vendor/welshdev/doctrix/src/Service/'
        autowire: true
        autoconfigure: true
*/