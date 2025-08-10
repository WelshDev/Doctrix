<?php
/**
 * Repository Pattern Examples for Doctrix
 * 
 * This file demonstrates advanced repository patterns and best practices
 */

use WelshDev\Doctrix\BaseRepository;
use WelshDev\Doctrix\Traits\CacheableTrait;
use WelshDev\Doctrix\Traits\SoftDeleteTrait;
use WelshDev\Doctrix\Traits\GlobalScopesTrait;
use WelshDev\Doctrix\Pagination\PaginationResult;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

// Example 1: Repository with Named Filters
// =========================================

class UserRepository extends BaseRepository
{
    protected string $alias = 'u';
    
    protected array $joins = [
        ['leftJoin', 'u.profile', 'p'],
        ['leftJoin', 'u.roles', 'r'],
    ];
    
    /**
     * Define reusable named filters
     */
    protected function defineFilters(): array
    {
        return [
            'active' => function(QueryBuilder $qb) {
                $qb->andWhere('u.status = :status')
                   ->setParameter('status', 'active');
            },
            
            'verified' => function(QueryBuilder $qb) {
                $qb->andWhere('u.emailVerified = true')
                   ->andWhere('u.phoneVerified = true');
            },
            
            'hasProfile' => function(QueryBuilder $qb) {
                $qb->andWhere('p.id IS NOT NULL')
                   ->andWhere('p.completed = true');
            },
            
            'admin' => function(QueryBuilder $qb) {
                $qb->andWhere('r.name = :role')
                   ->setParameter('role', 'ROLE_ADMIN');
            },
            
            'recent' => function(QueryBuilder $qb) {
                $qb->andWhere('u.created > :date')
                   ->setParameter('date', new \DateTime('-30 days'));
            }
        ];
    }
    
    // Custom repository methods using filters
    public function findActiveAdmins(): array
    {
        return $this->query()
            ->applyFilter('active')
            ->applyFilter('admin')
            ->orderBy('created', 'DESC')
            ->get();
    }
    
    public function findVerifiedUsers(int $limit = 10): array
    {
        return $this->query()
            ->applyFilter('active')
            ->applyFilter('verified')
            ->applyFilter('hasProfile')
            ->limit($limit)
            ->get();
    }
}

// Example 2: Repository with Global Scopes
// =========================================

class ProductRepository extends BaseRepository
{
    use GlobalScopesTrait;
    
    protected string $alias = 'p';
    
    /**
     * Global scopes are automatically applied to ALL queries
     */
    protected function globalScopes(): array
    {
        return [
            'published' => function(QueryBuilder $qb) {
                $qb->andWhere('p.status = :status')
                   ->setParameter('status', 'published');
            },
            
            'notDeleted' => function(QueryBuilder $qb) {
                $qb->andWhere('p.deletedAt IS NULL');
            },
            
            'inStock' => function(QueryBuilder $qb) {
                if ($this->shouldApplyStockScope()) {
                    $qb->andWhere('p.stock > 0');
                }
            }
        ];
    }
    
    private function shouldApplyStockScope(): bool
    {
        // You can add logic to conditionally apply scopes
        return true;  // or check some configuration
    }
    
    // All queries will automatically exclude unpublished, deleted, and out-of-stock items
    public function findFeatured(): array
    {
        return $this->fetch(['featured' => true]);
        // Automatically adds: status='published' AND deletedAt IS NULL AND stock > 0
    }
    
    // Bypass specific global scope when needed
    public function findAllIncludingUnpublished(): array
    {
        return $this->query()
            ->withoutGlobalScope('published')
            ->get();
        // Still applies 'notDeleted' and 'inStock' scopes
    }
    
    // Bypass all global scopes
    public function findAbsolutelyAll(): array
    {
        return $this->query()
            ->withoutGlobalScopes()
            ->get();
    }
}

// Example 3: Repository with Soft Deletes
// ========================================

class DocumentRepository extends BaseRepository
{
    use SoftDeleteTrait;
    
    protected string $alias = 'd';
    protected string $softDeleteField = 'deletedAt';  // Customize the field name
    
    // Soft delete methods are automatically available
    public function demonstrateSoftDeletes(): void
    {
        // Normal queries automatically exclude soft-deleted records
        $documents = $this->fetch();  // WHERE deletedAt IS NULL
        
        // Include soft-deleted records
        $allDocuments = $this->fetchWithDeleted();
        
        // Only soft-deleted records
        $trashedDocuments = $this->fetchOnlyDeleted();
        
        // With fluent interface
        $documents = $this->query()
            ->withDeleted()  // Include soft-deleted
            ->where('type', 'contract')
            ->get();
        
        // Check if a specific document is deleted
        $document = $this->fetchOneWithDeleted(['id' => 123]);
        if ($document && $this->isSoftDeleted($document)) {
            echo "Document is in trash\n";
        }
    }
}

// Example 4: Repository with Caching
// ===================================

class CategoryRepository extends BaseRepository
{
    use CacheableTrait;
    
    protected string $alias = 'c';
    protected int $defaultCacheTtl = 3600;  // 1 hour default
    
    // Cached fetch methods are automatically available
    public function demonstrateCaching(): void
    {
        // Fetch with caching (uses default TTL)
        $categories = $this->fetchCached();
        
        // Fetch with custom cache duration
        $featured = $this->fetchCached(
            criteria: ['featured' => true],
            orderBy: ['position' => 'ASC'],
            ttl: 7200  // Cache for 2 hours
        );
        
        // With custom cache key
        $topLevel = $this->fetchCachedWithKey(
            'top_level_categories',
            ['parentId' => null],
            ['name' => 'ASC']
        );
        
        // Fluent interface with caching
        $categories = $this->query()
            ->where('active', true)
            ->cache(3600)  // Cache for 1 hour
            ->get();
        
        // Cache with custom key
        $categories = $this->query()
            ->where('featured', true)
            ->cache(3600, 'featured_categories')
            ->get();
    }
    
    // Custom method with built-in caching
    public function getNavigationCategories(): array
    {
        // This result will be cached
        return $this->fetchCachedWithKey(
            'navigation_categories',
            ['showInNav' => true, 'active' => true],
            ['position' => 'ASC', 'name' => 'ASC'],
            limit: null,
            offset: null,
            ttl: 86400  // Cache for 24 hours
        );
    }
}

// Example 5: Repository with Complex Business Logic
// ==================================================

class OrderRepository extends BaseRepository
{
    use CacheableTrait;
    use SoftDeleteTrait;
    
    protected string $alias = 'o';
    
    protected array $joins = [
        ['leftJoin', 'o.customer', 'c'],
        ['leftJoin', 'o.items', 'i'],
        ['leftJoin', 'o.payments', 'p'],
    ];
    
    /**
     * Find orders that need processing
     */
    public function findOrdersToProcess(): array
    {
        return $this->query()
            ->where('status', 'paid')
            ->whereNull('processedAt')
            ->where('created', '>', new \DateTime('-24 hours'))
            ->orderBy('priority', 'DESC')
            ->orderBy('created', 'ASC')
            ->get();
    }
    
    /**
     * Find abandoned carts
     */
    public function findAbandonedCarts(\DateTime $since): array
    {
        return $this->query()
            ->where('status', 'cart')
            ->where('updated', '<', $since)
            ->whereNotNull('c.email')  // Customer has email
            ->where(function($q) {
                $q->whereNull('abandonedEmailSentAt')
                  ->orWhere('abandonedEmailSentAt', '<', new \DateTime('-7 days'));
            })
            ->limit(100)
            ->get();
    }
    
    /**
     * Get order statistics for a date range
     */
    public function getOrderStats(\DateTime $from, \DateTime $to): array
    {
        $qb = $this->buildQuery();
        
        $qb->select([
            'DATE(o.created) as date',
            'COUNT(o.id) as total_orders',
            'SUM(o.total) as total_revenue',
            'AVG(o.total) as avg_order_value',
            'COUNT(DISTINCT c.id) as unique_customers'
        ])
        ->where('o.created BETWEEN :from AND :to')
        ->andWhere('o.status != :status')
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->setParameter('status', 'cancelled')
        ->groupBy('date')
        ->orderBy('date', 'ASC');
        
        return $qb->getQuery()->getArrayResult();
    }
    
    /**
     * Complex search with multiple filters
     */
    public function searchOrders(array $filters): PaginationResult
    {
        $query = $this->query();
        
        // Customer filter
        if (!empty($filters['customer'])) {
            $query->where(function($q) use ($filters) {
                $q->whereContains('c.name', $filters['customer'])
                  ->orWhereContains('c.email', $filters['customer'])
                  ->orWhere('o.reference', $filters['customer']);
            });
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->where('o.created', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('o.created', '<=', $filters['date_to']);
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('o.status', $filters['status']);
            } else {
                $query->where('o.status', $filters['status']);
            }
        }
        
        // Amount range
        if (!empty($filters['min_amount'])) {
            $query->where('o.total', '>=', $filters['min_amount']);
        }
        
        if (!empty($filters['max_amount'])) {
            $query->where('o.total', '<=', $filters['max_amount']);
        }
        
        // Payment method
        if (!empty($filters['payment_method'])) {
            $query->where('p.method', $filters['payment_method']);
        }
        
        // Sorting
        $sortField = $filters['sort'] ?? 'created';
        $sortDir = $filters['direction'] ?? 'DESC';
        $query->orderBy("o.{$sortField}", $sortDir);
        
        // Paginate results
        return $query->paginate(
            page: $filters['page'] ?? 1,
            perPage: $filters['per_page'] ?? 20
        );
    }
}

// Example 6: Repository with Filter Functions
// ============================================

class PostRepository extends BaseRepository
{
    protected string $alias = 'p';
    
    /**
     * Filter by author (can be chained)
     */
    public function byAuthor(User $author): self
    {
        $this->addFilterFunction(function(QueryBuilder $qb) use ($author) {
            $qb->andWhere('p.author = :author')
               ->setParameter('author', $author);
        });
        
        return $this;
    }
    
    /**
     * Filter by category
     */
    public function inCategory(Category $category): self
    {
        $this->addFilterFunction(function(QueryBuilder $qb) use ($category) {
            $qb->join('p.categories', 'c')
               ->andWhere('c.id = :category')
               ->setParameter('category', $category->getId());
        });
        
        return $this;
    }
    
    /**
     * Filter by tag
     */
    public function taggedWith(string $tag): self
    {
        $this->addFilterFunction(function(QueryBuilder $qb) use ($tag) {
            $qb->join('p.tags', 't')
               ->andWhere('t.name = :tag')
               ->setParameter('tag', $tag);
        });
        
        return $this;
    }
    
    /**
     * Filter to published posts only
     */
    public function published(): self
    {
        $this->addFilterFunction(function(QueryBuilder $qb) {
            $qb->andWhere('p.status = :status')
               ->andWhere('p.publishedAt <= :now')
               ->setParameter('status', 'published')
               ->setParameter('now', new \DateTime());
        });
        
        return $this;
    }
    
    // Usage: Chain filters together
    public function demonstrateFilterFunctions(User $author, Category $category): array
    {
        return $this
            ->byAuthor($author)
            ->inCategory($category)
            ->published()
            ->fetch([], ['publishedAt' => 'DESC']);
        
        // Or combine with array criteria
        return $this
            ->byAuthor($author)
            ->published()
            ->fetch(
                ['featured' => true],
                ['views' => 'DESC']
            );
    }
}